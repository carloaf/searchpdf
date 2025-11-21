#!/bin/bash
#
# Script para corrigir permissões do sistema de upload
# Autor: Augusto
# Data: Novembro 2025
#

echo "=== Corrigindo Permissões do Sistema de Upload ==="

# 1. Ajustar permissões do diretório public
echo "1. Ajustando permissões do diretório public..."
docker exec searchpdf_app chown www-data:www-data /var/www/html/public
docker exec searchpdf_app chmod 755 /var/www/html/public

# 2. Ajustar permissões do diretório uploads
echo "2. Ajustando permissões do diretório uploads..."
docker exec searchpdf_app chown -R www-data:www-data /var/www/html/public/uploads
docker exec searchpdf_app chmod -R 755 /var/www/html/public/uploads

# 3. Criar/verificar arquivo de configuração PHP para uploads
echo "3. Configurando limites de upload do PHP..."
docker exec searchpdf_app sh -c 'cat > /usr/local/etc/php/conf.d/uploads.ini << EOF
upload_max_filesize = 50M
post_max_size = 55M
max_execution_time = 300
memory_limit = 256M
EOF'

# 4. Reiniciar o container para aplicar configurações
echo "4. Reiniciando container da aplicação..."
docker restart searchpdf_app

# 5. Aguardar o container iniciar
echo "5. Aguardando container inicializar..."
sleep 5

# 6. Verificar status
echo ""
echo "=== Status Final ==="
echo "Permissões:"
docker exec searchpdf_app ls -ld /var/www/html/public /var/www/html/public/uploads

echo ""
echo "Configurações PHP:"
docker exec searchpdf_app php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"

echo ""
echo "✓ Correções aplicadas com sucesso!"
echo "Teste o upload acessando: http://10.133.9.84/upload"
