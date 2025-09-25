#!/bin/bash
# Script de execução do indexador SearchPDF via cron
# Executa o indexador PHP e registra logs de execução

set -euo pipefail

PROJECT_ROOT="/home/augusto/workspace/searchpdf"
SCRIPT_NAME="$(basename "$0")"
LOG_DIR="$PROJECT_ROOT/logs"
RUNTIME_LOG="$LOG_DIR/cron_indexador.log"
INDEXER_OUTPUT="$LOG_DIR/indexador_output.log"
PHP_BIN="${SEARCHPDF_PHP_BIN:-$(command -v php || true)}"
DOCKER_CONTAINER="${SEARCHPDF_PHP_CONTAINER:-searchpdf_app}"

mkdir -p "$LOG_DIR"

log_message() {
    local message="$1"
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$message" | tee -a "$RUNTIME_LOG"
}

PHP_CMD=()
INDEXER_SCRIPT=""

if [[ -n "$PHP_BIN" ]]; then
    PHP_CMD=("$PHP_BIN")
    INDEXER_SCRIPT="$PROJECT_ROOT/www/indexer.php"
    export UPLOADS_DIR="$PROJECT_ROOT/www/public/uploads"
else
    if command -v docker >/dev/null 2>&1; then
        if docker ps --format '{{.Names}}' | grep -Fxq "$DOCKER_CONTAINER"; then
            PHP_CMD=(docker exec "$DOCKER_CONTAINER" php)
            INDEXER_SCRIPT="/var/www/html/indexer.php"
            unset UPLOADS_DIR || true
        else
            log_message "ERRO: PHP não encontrado e o container '$DOCKER_CONTAINER' não está em execução. Ajuste SEARCHPDF_PHP_BIN ou SEARCHPDF_PHP_CONTAINER."
            exit 1
        fi
    else
        log_message "ERRO: PHP não encontrado e Docker indisponível. Configure SEARCHPDF_PHP_BIN ou instale o PHP localmente."
        exit 1
    fi
fi

log_message "Iniciando execução do cron ($SCRIPT_NAME)."

export PATH="/usr/local/bin:/usr/bin:/bin:$PATH"

# Executa o indexador e registra o resultado
if "${PHP_CMD[@]}" "$INDEXER_SCRIPT" >> "$INDEXER_OUTPUT" 2>&1; then
    log_message "Indexação concluída com sucesso. Saída registrada em $INDEXER_OUTPUT."
    exit 0
else
    status=$?
    log_message "ERRO: Indexação retornou código $status. Verifique $INDEXER_OUTPUT para detalhes."
    exit $status
fi
