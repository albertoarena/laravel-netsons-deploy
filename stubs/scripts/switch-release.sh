#!/bin/bash
set -euo pipefail

# ============================================================================
# switch-release.sh — Activate a release and update the proxy index.php
#
# Required environment variables:
#   SSH_HOST        — SSH hostname
#   SSH_PORT        — SSH port (default: 65100)
#   SSH_USER        — SSH username
#   DEPLOY_PATH     — Remote deploy path relative to home (e.g. public_html)
#   RELEASE_DIR     — Release timestamp directory name
#   PHP_BINARY      — Remote PHP binary path (e.g. /usr/local/bin/ea-php84)
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [SWITCH] $*"
}

SSH_PORT="${SSH_PORT:-65100}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

log "Switching to release ${RELEASE_DIR}..."

${SSH_CMD} bash -s <<REMOTE
set -euo pipefail
DEPLOY_BASE=~/${DEPLOY_PATH}
RELEASE_PATH=\${DEPLOY_BASE}/releases/${RELEASE_DIR}

# Verify release directory exists
if [ ! -d "\${RELEASE_PATH}" ]; then
    echo "ERROR: Release directory does not exist: \${RELEASE_PATH}"
    exit 1
fi

# Update the current symlink
ln -sfn \${RELEASE_PATH} \${DEPLOY_BASE}/current
echo "Symlink updated: current -> releases/${RELEASE_DIR}"

# Generate proxy index.php in the document root
cat > \${DEPLOY_BASE}/index.php <<'PROXY'
<?php

/**
 * Proxy index.php — routes requests to the active Laravel release.
 */

\$releasePath = __DIR__ . '/current';

// Check for maintenance mode
if (file_exists(\$releasePath . '/storage/framework/maintenance.php')) {
    require \$releasePath . '/storage/framework/maintenance.php';
}

// Bootstrap Laravel from the active release
require \$releasePath . '/public/index.php';
PROXY

echo "Proxy index.php updated."

# Copy public assets to document root public/ directory
if [ -d "\${RELEASE_PATH}/public/build" ]; then
    mkdir -p \${DEPLOY_BASE}/public/build
    cp -r \${RELEASE_PATH}/public/build/* \${DEPLOY_BASE}/public/build/
    echo "Public build assets copied."
fi

echo "Release ${RELEASE_DIR} is now active."
REMOTE

log "Release switch complete."
