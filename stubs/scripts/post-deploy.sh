#!/bin/bash
set -euo pipefail

# ============================================================================
# post-deploy.sh — Shared post-deployment steps
#
# Sets up symlinks for shared resources (.env, storage), runs migrations,
# and rebuilds caches.
#
# Required environment variables:
#   SSH_HOST        — SSH hostname
#   SSH_PORT        — SSH port (default: 65100)
#   SSH_USER        — SSH username
#   DEPLOY_PATH     — Remote deploy path relative to home (e.g. public_html)
#   RELEASE_DIR     — Release timestamp directory name
#   PHP_BINARY      — Remote PHP binary path (e.g. /usr/local/bin/ea-php84)
#
# Optional environment variables:
#   RUN_MIGRATIONS  — Run migrations (default: true)
#   RUN_SEEDERS     — Run seeders on first deploy (default: false)
#   SEEDER_CLASSES  — Comma-separated seeder class names
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [POST] $*"
}

SSH_PORT="${SSH_PORT:-65100}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"
RUN_SEEDERS="${RUN_SEEDERS:-false}"
SEEDER_CLASSES="${SEEDER_CLASSES:-}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

log "Running post-deploy steps..."

${SSH_CMD} bash -s <<REMOTE
set -euo pipefail
DEPLOY_BASE=~/${DEPLOY_PATH}
RELEASE_PATH=\${DEPLOY_BASE}/releases/${RELEASE_DIR}

cd \${RELEASE_PATH}

# ── Shared directory setup ──────────────────────────────────────────────
echo "Setting up shared resources..."

# Create shared directory if it doesn't exist
mkdir -p \${DEPLOY_BASE}/shared/storage

# Initialize shared .env from release if no shared .env exists
if [ ! -f \${DEPLOY_BASE}/shared/.env ]; then
    if [ -f \${RELEASE_PATH}/.env.example ]; then
        cp \${RELEASE_PATH}/.env.example \${DEPLOY_BASE}/shared/.env
        echo "Created shared .env from .env.example"
    fi
fi

# Symlink .env
rm -f \${RELEASE_PATH}/.env
ln -sfn \${DEPLOY_BASE}/shared/.env \${RELEASE_PATH}/.env

# Symlink storage
rm -rf \${RELEASE_PATH}/storage
ln -sfn \${DEPLOY_BASE}/shared/storage \${RELEASE_PATH}/storage

# Ensure storage directory structure exists
mkdir -p \${DEPLOY_BASE}/shared/storage/app/public
mkdir -p \${DEPLOY_BASE}/shared/storage/framework/cache/data
mkdir -p \${DEPLOY_BASE}/shared/storage/framework/sessions
mkdir -p \${DEPLOY_BASE}/shared/storage/framework/views
mkdir -p \${DEPLOY_BASE}/shared/storage/logs

# ── Migrations ──────────────────────────────────────────────────────────
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running migrations..."
    ${PHP_BINARY} artisan migrate --force --no-interaction
fi

# ── First deploy seeders ────────────────────────────────────────────────
if [ -f \${DEPLOY_BASE}/.first_deploy ] && [ "${RUN_SEEDERS}" = "true" ]; then
    echo "First deploy detected — running seeders..."
    IFS=',' read -ra SEEDERS <<< "${SEEDER_CLASSES}"
    for seeder in "\${SEEDERS[@]}"; do
        if [ -n "\${seeder}" ]; then
            echo "Running seeder: \${seeder}"
            ${PHP_BINARY} artisan db:seed --class="\${seeder}" --force --no-interaction
        fi
    done
    rm -f \${DEPLOY_BASE}/.first_deploy
fi

# ── Cache management ────────────────────────────────────────────────────
echo "Clearing and rebuilding caches..."
${PHP_BINARY} artisan cache:clear
${PHP_BINARY} artisan config:cache
${PHP_BINARY} artisan route:cache
${PHP_BINARY} artisan view:cache
${PHP_BINARY} artisan event:cache

# Restart queue workers if available
${PHP_BINARY} artisan queue:restart 2>/dev/null || true

echo "Post-deploy steps complete."
REMOTE

log "Post-deploy finished."
