#!/usr/bin/env bash
# Install Certbot on Amazon Linux 2 / AL2023 if missing
if ! command -v certbot &> /dev/null; then
  dnf install -y certbot python3-certbot-nginx 2>/dev/null || yum install -y certbot python3-certbot-nginx 2>/dev/null || true
fi

# Obtain / renewal SSL certificate for green-forensics.duckdns.org
certbot --nginx --non-interactive --agree-tos --email yvezjayveegesmundo@gmail.com -d green-forensics.duckdns.org --redirect || true

# Reload Nginx to apply SSL cert
systemctl reload nginx 2>/dev/null || true
