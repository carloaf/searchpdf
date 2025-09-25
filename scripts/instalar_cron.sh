#!/bin/bash
# Script para instalar o cron job de indexação automática do SearchPDF

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CRON_TEMPLATE="$SCRIPT_DIR/crontab_indexador"
CRON_TAG="indexacao_automatica.sh"

if [[ ! -f "$CRON_TEMPLATE" ]]; then
    echo "ERRO: Arquivo de template do cron não encontrado em $CRON_TEMPLATE" >&2
    exit 1
fi

chmod +x "$SCRIPT_DIR/indexacao_automatica.sh"

TEMP_CRON="$(mktemp)"
CLEAN_CRON="$(mktemp)"
trap 'rm -f "$TEMP_CRON" "$CLEAN_CRON"' EXIT

# Exporta entradas existentes, se houver
if crontab -l > "$TEMP_CRON" 2>/dev/null; then
    grep -v "$CRON_TAG" "$TEMP_CRON" > "$CLEAN_CRON"
else
    : > "$CLEAN_CRON"
fi

cat "$CRON_TEMPLATE" >> "$CLEAN_CRON"
crontab "$CLEAN_CRON"

mkdir -p "$PROJECT_ROOT/logs"

SCRIPT_PATH="$PROJECT_ROOT/scripts/indexacao_automatica.sh"
LOG_PATH="$PROJECT_ROOT/logs/"

cat <<EOM
Cron job instalado com sucesso!
Horários: Segunda a sexta, das 08h às 18h, a cada 2 horas.
Script executado: $SCRIPT_PATH
Logs em: $LOG_PATH

Crontab atual:
$(crontab -l)
EOM
