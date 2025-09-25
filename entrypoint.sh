#!/bin/bash
set -e

# Inicia o serviço Cron em segundo plano
echo "Iniciando o serviço Cron..."
cron

# Executa o comando principal do container (Apache) em primeiro plano.
# O "$@" passa quaisquer argumentos que o CMD possa ter.
echo "Iniciando o Apache..."
exec "$@"
