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

# 4. Assinatura oficial do App (Nextcloud App Store) caso chave e certificado existam
if [ -f "$ROOT_DIR/oab_timesheet.key" ] && [ -f "$ROOT_DIR/oab_timesheet.crt" ]; then
    echo "🔐 Chave privada e certificado Nextcloud encontrados. Assinando aplicativo..."
    
    # Criar staging limpo para assinatura
    STAGING_DIR="/tmp/nc_app_sign_staging"
    rm -rf "$STAGING_DIR"
    mkdir -p "$STAGING_DIR/oab_timesheet"

    export COPYFILE_DISABLE=1
    tar --exclude='._*' --exclude='.DS_Store' -cf - \
        appinfo \
        lib \
        templates \
        img \
        js \
        css \
        README.md | tar -xf - -C "$STAGING_DIR/oab_timesheet"

    # Se container local estiver ativo, assinar via occ docker
    if docker ps | grep -q "oab_timesheet_nextcloud"; then
        docker exec oab_timesheet_nextcloud rm -rf /tmp/staging_sign
        docker exec oab_timesheet_nextcloud mkdir -p /tmp/staging_sign
        docker cp "$STAGING_DIR/oab_timesheet" oab_timesheet_nextcloud:/tmp/staging_sign/
        docker cp "$ROOT_DIR/oab_timesheet.key" oab_timesheet_nextcloud:/tmp/staging_sign/
        docker cp "$ROOT_DIR/oab_timesheet.crt" oab_timesheet_nextcloud:/tmp/staging_sign/
        docker exec oab_timesheet_nextcloud chown -R www-data:www-data /tmp/staging_sign
        docker exec -u www-data oab_timesheet_nextcloud php occ integrity:sign-app \
            --path=/tmp/staging_sign/oab_timesheet \
            --privateKey=/tmp/staging_sign/oab_timesheet.key \
            --certificate=/tmp/staging_sign/oab_timesheet.crt

        # Copiar signature.json gerado de volta para o repositório e staging
        docker cp oab_timesheet_nextcloud:/tmp/staging_sign/oab_timesheet/appinfo/signature.json "$ROOT_DIR/appinfo/signature.json"
        cp "$ROOT_DIR/appinfo/signature.json" "$STAGING_DIR/oab_timesheet/appinfo/signature.json"
        
        # Gerar o tar.gz oficial para a Nextcloud App Store (com pasta raiz oab_timesheet/)
        (cd "$STAGING_DIR" && tar --exclude='._*' --exclude='.DS_Store' -czf "$DIST_DIR/oab_timesheet.tar.gz" oab_timesheet)
        shasum -a 256 "$DIST_DIR/oab_timesheet.tar.gz" > "$DIST_DIR/oab_timesheet.tar.gz.sha256"
        RELEASE_SIG=$(openssl dgst -sha512 -sign "$ROOT_DIR/oab_timesheet.key" "$DIST_DIR/oab_timesheet.tar.gz" | openssl base64 -A)
        echo "$RELEASE_SIG" > "$DIST_DIR/oab_timesheet.tar.gz.sig"
        echo "✅ Pacote oficial assinado para Nextcloud App Store: $DIST_DIR/oab_timesheet.tar.gz"
        echo "🔑 Assinatura da Release para colar na loja:"
        echo "$RELEASE_SIG"
        echo ""
        docker exec oab_timesheet_nextcloud rm -rf /tmp/staging_sign
    fi
    rm -rf "$STAGING_DIR"
fi

# 5. Criar pacote tar.gz direto para instalação manual (sem arquivos ocultos do macOS)
echo "🗜️ Criando arquivo compactado $PACKAGE_NAME..."
export COPYFILE_DISABLE=1
tar --exclude='._*' --exclude='.DS_Store' -czf "$DIST_DIR/$PACKAGE_NAME" \
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
