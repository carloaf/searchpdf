# Sistema de Upload de PDFs com Autenticação

## 📋 Visão Geral

Sistema completo de upload de arquivos PDF com autenticação de usuários, controle de permissões e auditoria.

## 🔐 Funcionalidades Implementadas

### 1. **Autenticação de Usuários**
- Login com usuário e senha
- Senhas criptografadas com bcrypt (cost 12)
- Sessões PHP seguras
- Middleware de autenticação

### 2. **Controle de Permissões (Roles)**
- **Admin**: Acesso total ao sistema
- **Uploader**: Pode fazer upload de arquivos
- **Viewer**: Apenas visualização (padrão)

### 3. **Upload Protegido**
- Apenas usuários autenticados com role `admin` ou `uploader`
- Validação de tipo: apenas PDFs
- Validação de tamanho: máximo 50MB
- Sanitização de nomes de arquivo
- Verificação de duplicatas

### 4. **Organização de Arquivos**
Estrutura: `uploads/{CATEGORIA} {ANO}/{MÊS}/arquivo.pdf`

Exemplo:
```
uploads/
├── BI 2025/
│   ├── Janeiro/
│   │   └── documento.pdf
│   ├── Fevereiro/
│   └── ...
└── BA 2025/
    ├── Janeiro/
    └── ...
```

### 5. **Auditoria**
- Log de todos os uploads (sucesso e falha)
- Registro de IP, data/hora, usuário
- Histórico de uploads por usuário

## 🗄️ Estrutura do Banco de Dados

### Tabelas Criadas

1. **`users`**: Usuários do sistema
2. **`upload_log`**: Log de uploads
3. **`user_sessions`**: Controle de sessões (opcional)

## 🚀 Deploy no Servidor

### **Passo 1: Criar as Tabelas**

```bash
# Conectar ao container do banco
docker exec -it searchpdf_db mariadb -u searchpdf_user -p searchpdf_db

# Ou via arquivo SQL
docker exec -i searchpdf_db mariadb -u searchpdf_user -p'user_password' searchpdf_db < www/sql/create_auth_tables.sql
```

### **Passo 2: Verificar Permissões do Diretório**

```bash
# Garantir que o Apache pode escrever no diretório uploads
docker exec searchpdf_app chown -R www-data:www-data /var/www/html/public/uploads
docker exec searchpdf_app chmod -R 755 /var/www/html/public/uploads
```

### **Passo 3: Configurar PHP Upload Limits (Opcional)**

Se necessário aumentar o limite de upload, edite o `php.ini` ou crie um arquivo de configuração:

```bash
# Dentro do container
docker exec -it searchpdf_app bash

cat > /usr/local/etc/php/conf.d/uploads.ini <<EOF
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
EOF

# Reiniciar o container
docker restart searchpdf_app
```

## 🔑 Credenciais Padrão

**⚠️ IMPORTANTE: Alterar em produção!**

### Admin
- Usuário: `admin`
- Senha: `admin123`

### Uploader
- Usuário: `uploader`
- Senha: `uploader123`

## 🌐 Rotas Implementadas

| Rota | Método | Autenticação | Permissão | Descrição |
|------|--------|--------------|-----------|-----------|
| `/` | GET | **Não** | Público | **Página principal de busca (acesso público)** |
| `/login` | GET/POST | Não | - | Página de login (também via AJAX no modal) |
| `/logout` | GET | Sim | Qualquer | Fazer logout |
| `/upload` | GET | Sim | admin/uploader | Página de upload + reindex |
| `/upload` | POST | Sim | admin/uploader | API de upload |
| `/auth/check` | GET | Não | - | Verificar status de auth (API) |

### 🔓 Mudanças Importantes

- **Página principal (`/`) agora é pública** - qualquer pessoa pode buscar PDFs
- **Login é opcional** - apenas necessário para upload/administração
- **Botão de login** aparece como ícone de chave (🔑) na página principal
- **Reindex** foi movido para a página de upload (/upload)

## 📁 Arquivos Criados/Modificados

### Novos Arquivos

```
www/
├── sql/
│   └── create_auth_tables.sql          # Script SQL das tabelas
├── src/
│   ├── controller/
│   │   ├── AuthController.php          # Controller de autenticação
│   │   └── UploadController.php        # Controller de upload
│   ├── model/
│   │   └── UserModel.php               # Model de usuários
│   └── view/
│       ├── base.twig                   # Template base
│       ├── login.twig                  # Página de login
│       └── upload.twig                 # Página de upload
└── libs/
    ├── AuthMiddleware.php              # Middleware de autenticação
    └── RoleMiddleware.php              # Middleware de autorização
```

### Arquivos Modificados

```
www/public/index.php                    # Adicionadas rotas de auth e upload
```

## 🧪 Testando o Sistema

### 1. **Acessar o Sistema (Público)**

```bash
# No servidor remoto via Teleport
tsh ssh suporte@VM-7CTA-11DSUP-ARRANCHAMENTO-HOMOLOGACAO -L 8080:localhost:8080

# No navegador local (acesso público - busca)
http://localhost:8080/
```

### 2. **Fazer Login (Admin/Uploader)**

Na página principal, clique no **botão com ícone de chave (🔑)** ao lado das estatísticas.

Um modal de login aparecerá. Use as credenciais padrão:
- **Usuário**: `admin`
- **Senha**: `admin123`

### 3. **Acessar Upload**

Após login bem-sucedido, você será redirecionado automaticamente para: `http://localhost:8080/upload`

### 4. **Testar Upload**

1. Selecione um arquivo PDF
2. Escolha categoria (BI/BA), ano e mês
3. Clique em "Enviar Arquivo"
4. Aguarde a mensagem de confirmação — o PDF é indexado imediatamente e o histórico exibe o status **Indexado**
5. Verifique o histórico de uploads

### 5. **Verificar Arquivo no Sistema**

```bash
# No servidor
docker exec searchpdf_app ls -lah /var/www/html/public/uploads/BI\ 2025/Janeiro/
```

## 🔄 Integração com Indexação

### Indexação Automática (após upload)

A indexação é executada **imediatamente** ao final de cada upload, na mesma requisição. O `UploadController` chama o `indexer.php` em modo de arquivo único:

```bash
php /var/www/html/indexer.php --file="/var/www/html/public/uploads/BI 2026/Novembro/arquivo.pdf"
```

Nesse modo o indexador:

1. Valida que o caminho é um PDF dentro do diretório de uploads;
2. Extrai o texto (`pdftotext`) e o total de páginas (`pdfinfo`);
3. Insere ou atualiza o registro em `pdf_index` (forçando a reindexação do arquivo enviado);
4. Retorna a quantidade de páginas indexadas.

O status do upload em `upload_log` passa de `success` para `indexed` quando a indexação conclui com sucesso. A resposta JSON do upload inclui `indexed`, `pages` e `index_error`, e a página de upload exibe o resultado sem exigir o clique em **"Sincronizar / Indexar PDFs"**.

Se o PDF não tiver texto extraível (por exemplo, digitalizado sem OCR), o upload continua válido, mas a interface avisa que a indexação automática falhou.

### Indexação Manual

O botão **"Sincronizar / Indexar PDFs"** na página `/upload` continua disponível para varrer todo o diretório de uploads e indexar arquivos pendentes, executando o script `run-indexer.php`.

### Execução via CLI

Sem argumentos, o indexador varre todo o diretório de uploads:

```bash
php indexer.php
```

Com `--file=<caminho>`, indexa apenas o arquivo informado (usado pelo upload):

```bash
php indexer.php --file="/var/www/html/public/uploads/BI 2026/Novembro/arquivo.pdf"
```

### Remoção de registros órfãos

Na varredura completa (sem `--file`), o indexador também remove de `pdf_index` os registros cujos PDFs não existem mais no disco e informa a quantidade:

```
Registros órfãos removidos: 16
```

Isso mantém o gráfico de distribuição de documentos (`/stats/document-distribution`) e os resultados de busca alinhados com os arquivos realmente presentes em `uploads/`. O modo `--file` nunca remove registros.

## 📊 Monitoramento

### Ver Logs de Upload

```bash
# No banco de dados
docker exec -it searchpdf_db mariadb -u searchpdf_user -p

USE searchpdf_db;

-- Últimos 20 uploads
SELECT u.username, ul.filename, ul.upload_date, ul.status 
FROM upload_log ul
JOIN users u ON ul.user_id = u.id
ORDER BY ul.upload_date DESC
LIMIT 20;

-- Uploads por usuário
SELECT u.username, COUNT(*) as total_uploads
FROM upload_log ul
JOIN users u ON ul.user_id = u.id
GROUP BY u.username;
```

### Logs do Apache

```bash
docker logs searchpdf_app --tail 100
```

## 🔒 Segurança

### Recomendações para Produção

1. **Alterar senhas padrão** imediatamente
2. **Usar HTTPS** para comunicação segura
3. **Configurar CSP** (Content Security Policy)
4. **Limitar tentativas de login** (implementar rate limiting)
5. **Revisar permissões** de diretórios regularmente
6. **Backup do banco** incluindo tabela `users`
7. **Logs de auditoria** para acessos não autorizados

### Criar Novo Usuário (via PHP)

```php
// Via código ou criar um script admin
$userData = [
    'username' => 'novo.usuario',
    'password' => 'senha_segura_aqui',
    'full_name' => 'Nome Completo',
    'email' => 'usuario@exemplo.com',
    'role' => 'uploader',
    'active' => true
];

$userId = \Model\UserModel::createUser($userData);
```

## 🐛 Troubleshooting

### Erro: "Nenhum arquivo foi enviado"

- Verificar limites do PHP (`upload_max_filesize`, `post_max_size`)
- Verificar permissões do diretório `/var/www/html/public/uploads`

### Erro: "Acesso negado"

- Verificar se o usuário tem role `admin` ou `uploader`
- Verificar se a sessão está ativa

### Erro: "Erro ao criar diretório"

```bash
# Ajustar permissões
docker exec searchpdf_app chown -R www-data:www-data /var/www/html/public/uploads
docker exec searchpdf_app chmod -R 755 /var/www/html/public/uploads
```

### Sessão não persiste

- Verificar configuração de sessões no PHP
- Verificar se `session_start()` é chamado corretamente

## 📚 Próximos Passos (Opcionais)

1. **Dashboard administrativo** para gerenciar usuários
2. **API REST** para upload via scripts externos
3. **Integração com LDAP/Active Directory**
4. **Upload múltiplo** (vários arquivos de uma vez)
5. **Preview de PDF** antes do upload
6. **Validação de conteúdo** (verificar se é realmente um PDF)
7. **Notificações** por email após upload
8. **Gestão de quotas** por usuário

## 📝 Notas Finais

- O sistema foi desenvolvido seguindo as melhores práticas de segurança
- Todas as senhas são armazenadas com hash bcrypt
- Os arquivos são validados antes do upload
- Logs completos para auditoria
- Interface responsiva e intuitiva

---

**Desenvolvido por**: Augusto  
**Data**: Novembro 2025  
**Versão**: 1.0
