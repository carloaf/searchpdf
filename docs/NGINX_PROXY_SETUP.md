# Configuração do Nginx Proxy

## Visão Geral

Este documento descreve a configuração do nginx como proxy reverso para permitir que os sistemas **SAGA** e **SearchPDF** coexistam no mesmo servidor, acessíveis pela porta 80.

## Arquitetura

```
Internet (porta 80)
       ↓
   nginx_proxy
       ↓
   ┌───────┴────────┐
   ↓                ↓
SAGA (/)      SearchPDF (/searchpdf/)
```

## URLs de Acesso

- **SAGA**: http://10.166.72.36/
- **SearchPDF**: http://10.166.72.36/searchpdf/

## Estrutura de Diretórios

```
/workspace/
├── nginx-proxy/
│   ├── docker-compose.yml
│   └── nginx.conf
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
    upstream saga {
        server saga_app_dev:80;
    }
    
    upstream searchpdf {
        server searchpdf_app:80;
    }
    
    server {
        listen 80;
        server_name _;
        
        # SAGA - Raiz do site
        location / {
            proxy_pass http://saga;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
        
        # SearchPDF - /searchpdf
        location /searchpdf/ {
            # Remove o prefixo /searchpdf mas mantém o resto do caminho
            # Isso permite que o Apache sirva arquivos estáticos corretamente
            rewrite ^/searchpdf(.*)$ $1 break;
            proxy_pass http://searchpdf;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
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
  nginx_proxy:
    image: nginx:alpine
    container_name: nginx_proxy
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
    networks:
      - saga_network
      - searchpdf_network

networks:
  saga_network:
    external: true
    name: saga_saga_network
  searchpdf_network:
    external: true
    name: searchpdf_default
```

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
nginx_proxy     Up                       0.0.0.0:80->80/tcp
saga_app_dev    Up (healthy)             80/tcp
searchpdf_app   Up                       80/tcp
searchpdf_db    Up                       0.0.0.0:3306->3306/tcp
```

## Gerenciamento

### Iniciar o nginx proxy

```bash
cd /workspace/nginx-proxy
docker-compose up -d
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

# SearchPDF
curl -I http://localhost/searchpdf/
```

## Notas Importantes

1. **Firewall**: Apenas a porta 80 está aberta no firewall da rede. As portas 8080, 8081, etc. estão bloqueadas.

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
