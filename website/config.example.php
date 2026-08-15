<?php
/**
 * Copy this file to website/.env (preferred) or export the same keys as real
 * environment variables. Never commit real credentials.
 *
 * Equivalent website/.env content (see .env.example at the repository root):
 *
 *   APP_ENV=production
 *   APP_DEBUG=false
 *   APP_BASE_URL=https://school.example.com
 *   DB_HOST=127.0.0.1
 *   DB_PORT=3306
 *   DB_NAME=school_saas
 *   DB_USER=school_saas_app          # least-privilege user, never root
 *   DB_PASSWORD=REPLACE_WITH_REAL_SECRET
 *   APP_ORIGINS=                      # empty = no browser CORS (native clients need none)
 *   SESSION_IDLE_TIMEOUT=1800
 *   SESSION_ABSOLUTE_TIMEOUT=43200
 *
 * All values below are FAKE placeholders kept only for documentation.
 */
declare(strict_types=1);

return [
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,
    'APP_BASE_URL' => 'https://school.example.com',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'school_saas',
    'DB_USER' => 'school_saas_app',
    'DB_PASSWORD' => 'REPLACE_WITH_REAL_SECRET',
    'APP_ORIGINS' => '',
];
