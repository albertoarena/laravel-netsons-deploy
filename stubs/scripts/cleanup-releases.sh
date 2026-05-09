#!/bin/bash
set -euo pipefail

# ============================================================================
# cleanup-releases.sh — Remove old releases beyond the keep count
#
# Required environment variables:
#   SSH_HOST        — SSH hostname
#   SSH_PORT        — SSH port (default: 65100)
#   SSH_USER        — SSH username
#   DEPLOY_PATH     — Remote deploy path relative to home (e.g. public_html)
#   RELEASES_KEEP   — Number of releases to keep (default: 5)
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [CLEANUP] $*"
}

SSH_PORT="${SSH_PORT:-65100}"
RELEASES_KEEP="${RELEASES_KEEP:-5}"
SSH_CMD="ssh -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

log "Cleaning up old releases (keeping ${RELEASES_KEEP})..."

${SSH_CMD} bash -s <<REMOTE
set -euo pipefail
RELEASES_DIR=~/${DEPLOY_PATH}/releases

if [ ! -d "\${RELEASES_DIR}" ]; then
    echo "No releases directory found, skipping cleanup."
    exit 0
fi

cd \${RELEASES_DIR}

# Count releases
TOTAL=\$(ls -1d */ 2>/dev/null | wc -l)
echo "Total releases: \${TOTAL}"

if [ "\${TOTAL}" -le "${RELEASES_KEEP}" ]; then
    echo "No releases to clean up."
    exit 0
fi

# Remove oldest releases beyond keep count
REMOVE_COUNT=\$(( TOTAL - ${RELEASES_KEEP} ))
echo "Removing \${REMOVE_COUNT} old release(s)..."

ls -1d */ | sort | head -n \${REMOVE_COUNT} | while read -r dir; do
    echo "Removing: \${dir}"
    rm -rf "\${dir}"
done

echo "Cleanup complete. Remaining releases: ${RELEASES_KEEP}"
REMOTE

log "Cleanup finished."
