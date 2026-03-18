<?php

return [
    'app_name'   => getenv('APP_NAME') ?: 'DDSafe',
    'app_secret' => getenv('APP_SECRET') ?: 'change-me-with-a-long-random-string-here',
    'personal_codes_enabled' => filter_var(getenv('PERSONAL_CODES_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'session_timeout_seconds' => (int)(getenv('SESSION_TIMEOUT_SECONDS') ?: 900),
    'log_max_entries' => (int)(getenv('LOG_MAX_ENTRIES') ?: 500),

    'backup_scheduler' => [
        'enabled' => filter_var(getenv('BACKUP_SCHEDULER_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'schedules' => getenv('BACKUP_SCHEDULES') ?: 'daily,weekly,monthly',
        'run_hour' => (int)(getenv('BACKUP_RUN_HOUR') ?: 2),
        'weekly_day' => (int)(getenv('BACKUP_WEEKLY_DAY') ?: 7),
        'monthly_day' => (int)(getenv('BACKUP_MONTHLY_DAY') ?: 1),
        'export_mode' => getenv('BACKUP_EXPORT_MODE') ?: 'encrypted',
        'include_secrets' => filter_var(getenv('BACKUP_INCLUDE_SECRETS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'passphrase' => getenv('BACKUP_PASSPHRASE') ?: '',
        'output_dir' => getenv('BACKUP_OUTPUT_DIR') ?: '/backups',
        'retention_daily' => (int)(getenv('BACKUP_RETENTION_DAILY') ?: 14),
        'retention_weekly' => (int)(getenv('BACKUP_RETENTION_WEEKLY') ?: 8),
        'retention_monthly' => (int)(getenv('BACKUP_RETENTION_MONTHLY') ?: 12),
        'check_interval_seconds' => (int)(getenv('BACKUP_CHECK_INTERVAL_SECONDS') ?: 300),
    ],

    'default_admin' => [
        'email'    => getenv('APP_ADMIN_EMAIL')    ?: 'admin@2fa-manager.local',
        'password' => getenv('APP_ADMIN_PASSWORD') ?: 'admin123',
        'name'     => getenv('APP_ADMIN_NAME')     ?: 'Administrateur',
    ],

    'extension' => [
        'app_url' => getenv('EXTENSION_APP_URL') ?: 'http://localhost:8080',
    ],

    'pocketbase' => [
        'url'            => getenv('POCKETBASE_URL') ?: 'http://localhost:8090',
        'admin_email'    => getenv('PB_ADMIN_EMAIL') ?: 'admin@admin.com',
        'admin_password' => getenv('PB_ADMIN_PASSWORD') ?: 'Admin12345!',
    ],

    'oidc' => [
        'enabled'        => filter_var(getenv('OIDC_ENABLED'), FILTER_VALIDATE_BOOLEAN),
        'provider_url'   => getenv('OIDC_PROVIDER_URL') ?: '',
        'client_id'      => getenv('OIDC_CLIENT_ID') ?: '',
        'client_secret'  => getenv('OIDC_CLIENT_SECRET') ?: '',
        'redirect_uri'   => getenv('OIDC_REDIRECT_URI') ?: '',
        'scopes'         => getenv('OIDC_SCOPES') ?: 'openid profile email',
        'username_claim' => getenv('OIDC_USERNAME_CLAIM') ?: 'preferred_username',
        'button_label'   => getenv('OIDC_BUTTON_LABEL') ?: 'Se connecter via SSO',
    ],

    'ldap' => [
        'enabled'       => filter_var(getenv('LDAP_ENABLED'), FILTER_VALIDATE_BOOLEAN),
        'host'          => getenv('LDAP_HOST') ?: '',
        'port'          => (int)(getenv('LDAP_PORT') ?: 389),
        'base_dn'       => getenv('LDAP_BASE_DN') ?: '',
        'domain'        => getenv('LDAP_DOMAIN') ?: '',
        'use_ssl'       => filter_var(getenv('LDAP_USE_SSL'), FILTER_VALIDATE_BOOLEAN),
        'bind_dn'       => getenv('LDAP_BIND_DN') ?: '',
        'bind_password' => getenv('LDAP_BIND_PASSWORD') ?: '',
        'search_filter' => getenv('LDAP_SEARCH_FILTER') ?: '(sAMAccountName={username})',
    ],
];
