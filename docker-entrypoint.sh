#!/bin/bash
set -e

# Use PORT from environment, default to 8080 if not set
PORT="${PORT:-8080}"

echo "Starting Apache on port ${PORT}..."

# Substitute the actual PORT into Apache's ports configuration
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Ensure only mpm_prefork is active — disable all conflicting MPM modules
a2dismod mpm_event  2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_itk    2>/dev/null || true
a2enmod  mpm_prefork

# Verify no conflicting MPMs are loaded
echo "Active MPM modules:"
apache2ctl -M 2>/dev/null | grep mpm || true

# Start Apache in the foreground
exec apache2-foreground
