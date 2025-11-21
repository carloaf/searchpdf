# Guia Rápido de Deploy

## 🚀 Deploy Rápido no Servidor

### Via Script Automatizado

```bash
# 1. Copiar arquivos para o servidor (se ainda não copiou)
tar -czf searchpdf_update.tar.gz www/ scripts/ docs/

# 2. Transferir via Teleport
tsh scp searchpdf_update.tar.gz suporte@VM-7CTA-11DSUP-ARRANCHAMENTO-HOMOLOGACAO:/workspace/searchpdf/

# 3. No servidor, extrair
tsh ssh suporte@VM-7CTA-11DSUP-ARRANCHAMENTO-HOMOLOGACAO
cd /workspace/searchpdf
tar -xzf searchpdf_update.tar.gz

# 4. Executar script de deploy
./scripts/deploy_upload_system.sh
```

### Manualmente (passo a passo)

```bash
# 1. Criar tabelas no banco
docker exec -i searchpdf_db mariadb -u searchpdf_user -p'user_password' searchpdf_db < www/sql/create_auth_tables.sql

# 2. Configurar permissões
docker exec searchpdf_app chown -R www-data:www-data /var/www/html/public/uploads
docker exec searchpdf_app chmod -R 755 /var/www/html/public/uploads

# 3. Reiniciar aplicação
docker restart searchpdf_app

# 4. Testar
curl -I http://localhost:8080/login
```

## 🔑 Acessar o Sistema

### Via Navegador Local (Túnel SSH)

```bash
# Terminal local
tsh ssh suporte@VM-7CTA-11DSUP-ARRANCHAMENTO-HOMOLOGACAO -L 8080:localhost:8080

# Navegador
http://localhost:8080/login
```

### Credenciais Padrão

- **Admin**: `admin` / `admin123`
- **Uploader**: `uploader` / `uploader123`

## 📝 Testar Upload

1. Login → `/upload`
2. Selecionar PDF (máx 50MB)
3. Escolher: Categoria (BI/BA), Ano, Mês
4. Enviar

## 🐛 Verificar Logs

```bash
# Logs da aplicação
docker logs searchpdf_app --tail 100

# Logs do banco
docker logs searchpdf_db --tail 50

# Uploads realizados
docker exec searchpdf_db mariadb -u searchpdf_user -p'user_password' searchpdf_db -e "SELECT * FROM upload_log ORDER BY upload_date DESC LIMIT 10;"
```

## ⚠️ Troubleshooting

### Erro 500
```bash
docker logs searchpdf_app --tail 50
```

### Tabelas não criadas
```bash
docker exec searchpdf_db mariadb -u searchpdf_user -p'user_password' searchpdf_db -e "SHOW TABLES;"
```

### Permissão negada no upload
```bash
docker exec searchpdf_app chown -R www-data:www-data /var/www/html/public/uploads
```
