#!/usr/bin/env bash
# ====================================================================
# SCRIPT DE EMPACOTAMENTO PARA PRODUÇÃO — TIMESHEET
# Criado por: Igor Pita
# ====================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
DIST_DIR="$ROOT_DIR/dist-package"

cd "$ROOT_DIR"

echo "📦 Iniciando empacotamento do Timesheet para Produção..."

# 1. Obter versão do info.xml
VERSION=$(sed -n 's/.*<version>\(.*\)<\/version>.*/\1/p' appinfo/info.xml | head -n 1 || echo "0.2.8")
PACKAGE_NAME="timesheet-v${VERSION}.tar.gz"

# 2. Compilar assets frontend de produção
echo "⚙️ Compilando assets via Vite..."
npm run build

# 3. Preparar diretório de saída
mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/$PACKAGE_NAME"

# 4. Criar pacote tar.gz contendo apenas arquivos de runtime do Nextcloud
echo "🗜️ Criando arquivo compactado $PACKAGE_NAME..."
tar -czf "$DIST_DIR/$PACKAGE_NAME" \
    appinfo \
    lib \
    templates \
    img \
    js \
    css \
    README.md

# 5. Copiar script das views do Metabase para fácil acesso
cp docker/metabase_views.sql "$DIST_DIR/metabase_views.sql"

echo ""
echo "===================================================================="
echo "✅ Pacote de Produção gerado com sucesso!"
echo "Arquivo: $DIST_DIR/$PACKAGE_NAME"
echo "Tamanho: $(du -h "$DIST_DIR/$PACKAGE_NAME" | cut -f1)"
echo "SQL Metabase: $DIST_DIR/metabase_views.sql"
echo "===================================================================="
echo ""
echo "🚀 Instruções rápidas para implantação:"
echo "1. Enviar para o servidor:"
echo "   scp $DIST_DIR/$PACKAGE_NAME usuario@servidor:/tmp/"
echo ""
echo "2. No servidor Nextcloud (instalação direta ou Docker):"
echo "   sudo mkdir -p /var/www/html/custom_apps/oab_timesheet"
echo "   sudo tar -xzf /tmp/$PACKAGE_NAME -C /var/www/html/custom_apps/oab_timesheet/"
echo "   sudo chown -R www-data:www-data /var/www/html/custom_apps/oab_timesheet/"
echo "   sudo -u www-data php /var/www/html/occ app:enable oab_timesheet"
echo "   sudo -u www-data php /var/www/html/occ maintenance:theme:update"
echo ""
echo "3. No PostgreSQL (para o Metabase):"
echo "   psql -U nextcloud -d nextcloud -f $DIST_DIR/metabase_views.sql"
echo "===================================================================="
