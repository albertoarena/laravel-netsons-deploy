#!/bin/bash
set -euo pipefail

# ============================================================================
# setup-ssh.sh — Configure SSH agent with key and known hosts
#
# Required environment variables:
#   SSH_PRIVATE_KEY     — Private key content
#   SSH_KNOWN_HOSTS     — Known hosts entry for the server
#   SSH_KEY_PASSPHRASE  — Key passphrase (optional, can be empty)
# ============================================================================

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [SSH] $*"
}

log "Setting up SSH agent..."

mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Write the private key
echo "${SSH_PRIVATE_KEY}" > ~/.ssh/deploy_key
chmod 600 ~/.ssh/deploy_key

# Write known hosts
echo "${SSH_KNOWN_HOSTS}" >> ~/.ssh/known_hosts
chmod 644 ~/.ssh/known_hosts

# Start SSH agent
eval "$(ssh-agent -s)"

# Add key with or without passphrase
if [ -n "${SSH_KEY_PASSPHRASE:-}" ]; then
    log "Adding SSH key with passphrase..."
    DISPLAY=:0 SSH_ASKPASS_REQUIRE=force SSH_ASKPASS="$(command -v echo)" \
        sshpass -p "${SSH_KEY_PASSPHRASE}" ssh-add ~/.ssh/deploy_key 2>/dev/null || {
        # Fallback: use expect-style approach
        log "Trying alternative passphrase method..."
        expect -c "
            spawn ssh-add ~/.ssh/deploy_key
            expect \"Enter passphrase\"
            send \"${SSH_KEY_PASSPHRASE}\r\"
            expect eof
        " 2>/dev/null || {
            log "Warning: Could not add key with passphrase automatically"
            ssh-add ~/.ssh/deploy_key
        }
    }
else
    ssh-add ~/.ssh/deploy_key
fi

log "SSH agent configured successfully."
