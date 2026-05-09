#!/bin/bash
set -euo pipefail

# ============================================================================
# deploy-ftp.sh — FTP deployment strategy
#
# This script handles the server-side preparation for FTP deployment.
# The actual FTP upload is handled by SamKirkland/FTP-Deploy-Action.
#
# Required environment variables:
#   SSH_HOST        — SSH hostname
#   SSH_PORT        — SSH port (default: 65100)
#   SSH_USER        — SSH username
#   DEPLOY_PATH     — Remote deploy path relative to home (e.g. public_html)
#   RELEASE_DIR     — Release timestamp directory name (e.g. 20240101120000)
#   PHP_BINARY      — Remote PHP binary path (e.g. /usr/local/bin/ea-php84)
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [FTP] $*"
}

SSH_PORT="${SSH_PORT:-65100}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

log "Preparing release directory on server..."

# Create release directory, copy from current release if exists
${SSH_CMD} bash -s <<REMOTE
set -euo pipefail
cd ~/${DEPLOY_PATH}

# Create releases directory if it doesn't exist
mkdir -p releases

# Create new release directory
mkdir -p releases/${RELEASE_DIR}

# Copy current release content if symlink exists
if [ -L current ]; then
    echo "Copying current release as base..."
    cp -a current/. releases/${RELEASE_DIR}/
fi

echo "Release directory ready: releases/${RELEASE_DIR}"
REMOTE

log "Server preparation complete. Ready for FTP upload."
