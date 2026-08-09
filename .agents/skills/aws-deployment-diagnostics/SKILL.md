---
name: aws-deployment-diagnostics
description: Specialized instructions and tools for diagnosing, repairing, and deploying the Green Forensics application to AWS Elastic Beanstalk and AWS RDS.
---

# AWS Deployment & Diagnostics Guide

Use this skill when analyzing AWS Elastic Beanstalk deployment failures, 500 Internal Server Errors, database connectivity issues with AWS RDS, or packaging errors.

## 1. Elastic Beanstalk Requirements
- **Bundle Packaging**: `waste-eggshell-aws.zip` must include `composer.json`, `.htaccess`, `.ebextensions/`, `config.php`, `database.sql`, and all PHP source code files. Exclude `.git`.
- **PHP Platform**: Configured for PHP 8.1 / 8.2 / 8.3 running on 64-bit Amazon Linux 2023.

## 2. Environment Variables Checklist
Ensure the following variables are configured in AWS Elastic Beanstalk Environment Properties or `.env`:
- `APP_ENV`: `production`
- `DB_HOST`: AWS RDS Endpoint (e.g. `green-forensics-db.c726xa00fdpw.ap-southeast-1.rds.amazonaws.com`)
- `DB_DATABASE`: `green_forensics`
- `DB_USERNAME` / `RDS_USERNAME`: `admin`
- `DB_PASSWORD` / `RDS_PASSWORD`: RDS Password
- `TRACCAR_GATEWAY_URL`: `http://192.168.1.14:8082`
- `TRACCAR_CLOUD_TOKEN`: Traccar Cloud Relay token

## 3. Database Migration & Provisioning
- `config.php` automatically performs `CREATE DATABASE IF NOT EXISTS` and executes table setup (`users`, `fingerprint_tests`, `sms_logs`, `account_unlock_requests`, etc.) on first boot.
- If schema errors occur, verify `database.sql` and run queries against RDS.
