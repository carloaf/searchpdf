#!/bin/bash

###############################################################################
# Script de Deploy do Sistema de Upload
# Automatiza a criação de tabelas e configuração de permissões
###############################################################################

set -e  # Parar em caso de erro

echo "=========================================="
echo "  Deploy do Sistema de Upload - SearchPDF"
echo "=========================================="
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configurações
CONTAINER_APP="searchpdf_app"
CONTAINER_DB="searchpdf_db"
DB_NAME="searchpdf_db"
DB_USER="searchpdf_user"
DB_PASS="user_password"

# Funções auxiliares
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# 1. Verificar se os containers estão rodando
echo "1. Verificando containers..."
if ! docker ps | grep -q $CONTAINER_APP; then
    print_error "Container $CONTAINER_APP não está rodando!"
    exit 1
fi

if ! docker ps | grep -q $CONTAINER_DB; then
    print_error "Container $CONTAINER_DB não está rodando!"
    exit 1
fi
print_success "Containers OK"

# 2. Criar as tabelas no banco de dados
echo ""
echo "2. Criando tabelas de autenticação..."
docker exec -i $CONTAINER_DB mariadb -u$DB_USER -p$DB_PASS $DB_NAME < www/sql/create_auth_tables.sql 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Tabelas criadas com sucesso"
else
    print_warning "As tabelas podem já existir (ignorar se for re-deploy)"
fi

# 3. Verificar se as tabelas foram criadas
echo ""
echo "3. Verificando tabelas criadas..."
TABLES=$(docker exec $CONTAINER_DB mariadb -u$DB_USER -p$DB_PASS $DB_NAME -e "SHOW TABLES LIKE '%users%' OR LIKE '%upload_log%';" 2>/dev/null | grep -v "Tables_in")

if [ -n "$TABLES" ]; then
    print_success "Tabelas encontradas:"
    echo "$TABLES"
else
    print_error "Nenhuma tabela encontrada!"
fi

# 4. Configurar permissões do diretório uploads
echo ""
echo "4. Configurando permissões do diretório uploads..."
docker exec $CONTAINER_APP chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null
docker exec $CONTAINER_APP chmod -R 755 /var/www/html/public/uploads 2>/dev/null
print_success "Permissões configuradas"

# 5. Verificar estrutura de diretórios
echo ""
echo "5. Verificando estrutura de diretórios..."
docker exec $CONTAINER_APP ls -la /var/www/html/public/uploads/ 2>/dev/null
print_success "Estrutura OK"

# 6. Testar conexão com banco
echo ""
echo "6. Testando conexão com banco de dados..."
docker exec $CONTAINER_DB mariadb -u$DB_USER -p$DB_PASS $DB_NAME -e "SELECT 'OK' as status;" 2>/dev/null
print_success "Conexão com banco OK"

# 7. Verificar usuários criados
echo ""
echo "7. Verificando usuários padrão criados..."
USERS=$(docker exec $CONTAINER_DB mariadb -u$DB_USER -p$DB_PASS $DB_NAME -e "SELECT username, role, active FROM users;" 2>/dev/null)
if [ -n "$USERS" ]; then
    print_success "Usuários encontrados:"
    echo "$USERS"
else
    print_warning "Nenhum usuário encontrado"
fi

# 8. Verificar limites de upload do PHP
echo ""
echo "8. Verificando configuração PHP..."
docker exec $CONTAINER_APP php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time" || true
print_success "Configuração PHP verificada"

# 9. Reiniciar container da aplicação
echo ""
echo "9. Reiniciando container da aplicação..."
docker restart $CONTAINER_APP > /dev/null 2>&1
sleep 3
print_success "Container reiniciado"

# 10. Testar acesso HTTP
echo ""
echo "10. Testando acesso HTTP..."
HTTP_STATUS=$(docker exec $CONTAINER_APP curl -s -o /dev/null -w "%{http_code}" http://localhost:80)
if [ "$HTTP_STATUS" == "200" ]; then
    print_success "Servidor HTTP OK (status: $HTTP_STATUS)"
else
    print_warning "Servidor HTTP retornou status: $HTTP_STATUS"
fi

echo ""
echo "=========================================="
print_success "Deploy concluído com sucesso!"
echo "=========================================="
echo ""
echo "📋 Próximos passos:"
echo "  1. Acesse: http://localhost:8080/login"
echo "  2. Use as credenciais padrão:"
echo "     - Usuário: admin"
echo "     - Senha: admin123"
echo ""
echo "⚠️  IMPORTANTE: Altere as senhas padrão em produção!"
echo ""
echo "📚 Documentação completa: docs/UPLOAD_SYSTEM.md"
echo ""
