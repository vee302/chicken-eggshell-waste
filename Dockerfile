FROM php:8.2-apache

# Install system dependencies and python
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    python-is-python3 \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Set up Python virtual environment to avoid PEP 668 externally-managed-environment errors
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install Python packages inside virtual environment
RUN pip install --no-cache-dir numpy opencv-python-headless

# Disable all conflicting MPM modules at build time and enable only mpm_prefork
RUN a2dismod mpm_event mpm_worker mpm_itk 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# Copy and register the entrypoint script (PORT substitution happens at runtime)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html/

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads/fingerprints \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

# Expose port (metadata only — actual port is set at runtime via PORT env var)
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
