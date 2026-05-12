#!/bin/bash
set -euo pipefail

# ============================================================================
# deploy-git.sh — Git deployment strategy
#
# Clones the repository on the server via SSH using HTTPS, then receives
# built assets via SCP.
#
# Required environment variables:
#   SSH_HOST        — SSH hostname
#   SSH_PORT        — SSH port (default: 65100)
#   SSH_USER        — SSH username
#   DEPLOY_PATH     — Remote deploy path relative to home (e.g. public_html)
#   RELEASE_DIR     — Release timestamp directory name (e.g. 20240101120000)
#   GIT_REPO        — Git repository HTTPS URL
#   GIT_BRANCH      — Git branch to deploy (default: main)
#   GIT_TOKEN       — GitHub token for private repos (optional)
#   PHP_BINARY      — Remote PHP binary path (e.g. /usr/local/bin/ea-php84)
#   COMPOSER_PATH   — Remote Composer path (default: /opt/cpanel/composer/bin/composer)
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [GIT] $*"
}

SSH_PORT="${SSH_PORT:-65100}"
GIT_BRANCH="${GIT_BRANCH:-main}"
COMPOSER_PATH="${COMPOSER_PATH:-/opt/cpanel/composer/bin/composer}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

# Build clone URL — inject token for private repos (HTTPS)
if [ -n "${GIT_TOKEN:-}" ]; then
    CLONE_URL=$(echo "${GIT_REPO}" | sed "s|https://github.com/|https://x-access-token:${GIT_TOKEN}@github.com/|")
else
    CLONE_URL="${GIT_REPO}"
fi

log "Deploying via git clone on server..."

# Clone repository into release directory (pass clone URL as argument to avoid it in the heredoc)
${SSH_CMD} bash -s <<REMOTE
set -euo pipefail
cd ~/${DEPLOY_PATH}

# Create releases directory if needed
mkdir -p releases

# Clone the repository into the release directory
echo "Cloning repository (branch: ${GIT_BRANCH})..."
git clone --branch ${GIT_BRANCH} --single-branch --depth 1 "${CLONE_URL}" releases/${RELEASE_DIR}

cd releases/${RELEASE_DIR}

# Install Composer dependencies (no dev)
echo "Installing Composer dependencies..."
${PHP_BINARY} ${COMPOSER_PATH} install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Remove unnecessary files
rm -rf .git .github tests .gitignore .gitattributes

echo "Git deployment complete for release: ${RELEASE_DIR}"
REMOTE

log "Server-side git clone complete."

# Upload built assets (CSS/JS) via SCP if they exist
if [ -d "public/build" ]; then
    log "Uploading built assets via SCP..."
    scp -P "${SSH_PORT}" -r public/build "${SSH_USER}@${SSH_HOST}:~/${DEPLOY_PATH}/releases/${RELEASE_DIR}/public/"
    log "Built assets uploaded."
fi
