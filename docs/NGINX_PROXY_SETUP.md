# Configuração do Nginx Proxy

## Visão Geral

Este documento descreve a configuração do nginx como proxy reverso para permitir que os sistemas **SAGA** e **SearchPDF** coexistam no mesmo servidor, acessíveis pelas portas 80 e 443.

## Arquitetura

```
Navegador (HTTPS)
       ↓
Proxy externo 10.166.72.33 (pr1207.7cta.eb.mil.br)
  - termina TLS com certificado *.11dsup.eb.mil.br
  - encaminha sipbo.11dsup.eb.mil.br → 10.166.72.36:443
       ↓
nginx_proxy (10.166.72.36)
  - :443 → SearchPDF (sipbo.11dsup.eb.mil.br)
  - :80  → SAGA (/) e SearchPDF (/searchpdf/)
       ↓
   ┌───────┴────────┐
   ↓                ↓
SAGA (/)      SearchPDF (/searchpdf/ ou sipbo)
```

## URLs de Acesso

- **SearchPDF (DNS/HTTPS)**: https://sipbo.11dsup.eb.mil.br/
- **SAGA**: http://10.166.72.36/
- **SearchPDF (fallback via IP)**: http://10.166.72.36/searchpdf/

> **Importante:** `sipbo.11dsup.eb.mil.br` resolve para `10.166.72.33` (o proxy
> externo `pr1207.7cta.eb.mil.br`), **não** para `10.166.72.36`. O proxy externo
> termina o HTTPS do cliente e encaminha para `10.166.72.36:443`. Por isso o
> `nginx_proxy` precisa escutar também na porta 443.

## Estrutura de Diretórios

```
/workspace/
├── nginx-proxy/
│   ├── docker-compose.yml
│   ├── nginx.conf
│   ├── generate-cert.sh
│   └── certs/            (gerado por generate-cert.sh, não versionado)
├── saga/
│   └── (arquivos do SAGA)
└── searchpdf/
    └── (arquivos do SearchPDF)
```

## Configuração do Nginx

### nginx-proxy/nginx.conf

```nginx
events {
    worker_connections 1024;
}

http {
    # MIME types essenciais
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - [$time_local] "$request" $status '
                    'host="$host" proto="$http_x_forwarded_proto"';

    access_log /dev/stdout main;

    upstream saga {
        server saga_app_dev:80;
    }

    upstream searchpdf {
        server searchpdf_app:80;
    }

    # SearchPDF via DNS https://sipbo.11dsup.eb.mil.br (porta 443)
    # O proxy externo (10.166.72.33) termina o TLS do cliente e encaminha
    # para 10.166.72.36:443. Sem este listener o acesso via HTTPS falha
    # com "empty reply" (o proxy externo recebe connection refused).
    server {
        listen 443 ssl;
        server_name sipbo.11dsup.eb.mil.br;

        ssl_certificate     /etc/nginx/certs/sipbo.crt;
        ssl_certificate_key /etc/nginx/certs/sipbo.key;
        ssl_protocols       TLSv1.2 TLSv1.3;

        location / {
            proxy_pass http://searchpdf;
            proxy_set_header Host sipbo.11dsup.eb.mil.br;
            proxy_set_header X-Forwarded-Host sipbo.11dsup.eb.mil.br;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto https;
        }
    }

    # SearchPDF via DNS http://sipbo.11dsup.eb.mil.br (porta 80 - fallback)
    server {
        listen 80;
        server_name sipbo.11dsup.eb.mil.br;

        location / {
            proxy_pass http://searchpdf;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
        }
    }

    # SAGA via IP (padrão) + SearchPDF via /searchpdf/ (fallback)
    # http://10.166.72.36/ → SAGA
    # http://10.166.72.36/searchpdf/ → SearchPDF
    server {
        listen 80 default_server;
        server_name _;

        location / {
            proxy_pass http://saga;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
        }

        location /searchpdf/ {
            rewrite ^/searchpdf(.*)$ $1 break;
            proxy_pass http://searchpdf;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
            proxy_set_header X-Forwarded-Prefix /searchpdf;
        }

        location = /searchpdf {
            return 301 /searchpdf/;
        }
    }
}
```

### nginx-proxy/docker-compose.yml

```yaml
version: '3.8'

services:
  nginx-proxy:
    image: nginx:alpine
    container_name: nginx_proxy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - ./certs:/etc/nginx/certs:ro
    networks:
      - saga_network
      - searchpdf_default

networks:
  saga_network:
    external:
      name: saga_saga_network
  searchpdf_default:
    external: true
```

### Certificado autoassinado (backend 443)

O listener 443 usa um certificado autoassinado apenas para o **backend**. O
certificado que o usuário vê no navegador continua sendo o do proxy externo
(`*.11dsup.eb.mil.br`). Gere o certificado antes de subir o proxy:

```bash
cd /workspace/nginx-proxy
chmod +x generate-cert.sh
./generate-cert.sh
```

O script cria `certs/sipbo.crt` e `certs/sipbo.key` (válido por 10 anos por
padrão). O diretório `certs/` não é versionado no Git.

## Configuração do SearchPDF

### www/config/settings.php

```php
// URL base onde está a aplicação
$settings['url_base'] = 'http://10.166.72.36/searchpdf';

// Caminho relativo - vazio porque o nginx remove o prefixo
$settings['path_relative'] = '';
```

### docker-compose.yml

O `searchpdf_app` **não** expõe mais a porta 8080 externamente, apenas internamente para comunicação com o nginx:

```yaml
services:
  app:
    image: searchpdf-app:latest
    container_name: searchpdf_app
    restart: unless-stopped
    # Porta 80 exposta apenas internamente
    volumes:
      - ./www:/var/www/html
      - ./uploads:/var/www/html/public/uploads
```

## Containers Rodando

```bash
$ docker ps
NAMES           STATUS                   PORTS
nginx_proxy     Up                       0.0.0.0:80->80/tcp, 0.0.0.0:443->443/tcp
saga_app_dev    Up (healthy)             80/tcp
searchpdf_app   Up                       80/tcp
searchpdf_db    Up                       0.0.0.0:3306->3306/tcp
```

## Gerenciamento

### Iniciar o nginx proxy

```bash
cd /workspace/nginx-proxy

# 1. Gerar o certificado autoassinado do backend 443 (uma única vez)
./generate-cert.sh

# 2. Subir/recriar o proxy
docker-compose up -d

# 3. Validar a configuração
docker exec nginx_proxy nginx -t
```

### Reiniciar o nginx proxy

```bash
docker restart nginx_proxy
```

### Ver logs do nginx

```bash
docker logs nginx_proxy
docker logs -f nginx_proxy  # follow mode
```

### Testar configuração do nginx

```bash
docker exec nginx_proxy nginx -t
```

### Recarregar configuração (sem reiniciar)

```bash
docker exec nginx_proxy nginx -s reload
```

## Troubleshooting

### `https://sipbo.11dsup.eb.mil.br/` retorna "empty reply" ou página em branco

O DNS `sipbo.11dsup.eb.mil.br` aponta para o proxy externo `10.166.72.33`
(`pr1207.7cta.eb.mil.br`), que termina o TLS e encaminha para
`10.166.72.36:443`. Esse sintoma ocorre quando o `nginx_proxy` **não** está
escutando na porta 443:

```bash
# Confirmar que a porta 443 está aberta no servidor
curl -kI https://10.166.72.36/ -H 'Host: sipbo.11dsup.eb.mil.br'
```

Se retornar `Failed to connect` / `Empty reply`, siga os passos:

```bash
cd /workspace/nginx-proxy
./generate-cert.sh          # gera certs/sipbo.crt e sipbo.key
docker-compose up -d        # recria o proxy com a porta 443
docker exec nginx_proxy nginx -t
```

Depois valide novamente. Se continuar falhando, verifique com a equipe de
infraestrutura se o proxy externo (`pr1207.7cta.eb.mil.br`) está encaminhando
`Host: sipbo.11dsup.eb.mil.br` para `10.166.72.36:443` (e não para outra porta
ou outro IP).

### Verificar se os containers estão na rede correta

```bash
docker network inspect saga_saga_network
docker network inspect searchpdf_default
```

### Testar conectividade interna

```bash
# Do nginx para o SAGA
docker exec nginx_proxy ping saga_app_dev

# Do nginx para o SearchPDF
docker exec nginx_proxy ping searchpdf_app
```

### Verificar logs de erro

```bash
docker logs nginx_proxy --tail 50
docker logs searchpdf_app --tail 50
docker logs saga_app_dev --tail 50
```

### Testar rotas individualmente

```bash
# SAGA
curl -I http://localhost/

# SearchPDF (fallback via IP)
curl -I http://localhost/searchpdf/

# SearchPDF via DNS no backend 443 (simula o encaminhamento do proxy externo)
curl -kI https://localhost/ -H 'Host: sipbo.11dsup.eb.mil.br'
```

## Notas Importantes

1. **Firewall**: As portas 80 e 443 devem estar liberadas no firewall da rede para o `nginx_proxy`. As portas 8080, 8081, etc. estão bloqueadas.

2. **Redes Docker**: O nginx precisa estar conectado a ambas as redes (`saga_saga_network` e `searchpdf_default`) para se comunicar com os containers.

3. **Base Path**: O SearchPDF está configurado para funcionar com o prefixo `/searchpdf` através da configuração `path_relative` no Slim Framework.

4. **Redirecionamento**: Acessar `http://10.166.72.36/searchpdf` (sem barra final) redireciona automaticamente para `http://10.166.72.36/searchpdf/`.

5. **Ordem de Inicialização**: 
   - Iniciar `searchpdf_db` e `saga_db` primeiro
   - Depois `searchpdf_app` e `saga_app_dev`
   - Por último `nginx_proxy`

## Performance

- Tempo de resposta típico: ~100-150ms
- Status HTTP esperado: 200 OK
- O nginx adiciona overhead mínimo (<10ms) ao tempo de resposta
