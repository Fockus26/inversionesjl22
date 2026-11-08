#!/bin/sh
# ssh-askpass.sh — hands ssh the password from PULL_SSH_PASSWORD (.env.local) when the host
# requires key AND password (GoDaddy Managed WordPress: "publickey ... partial success").
# Used by pull-site.sh through SSH_ASKPASS; the password never goes on a command line.
printf '%s\n' "$PULL_SSH_PASSWORD"
