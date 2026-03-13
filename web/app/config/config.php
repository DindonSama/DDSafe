<?php

return [
    'app_name'   => getenv('APP_NAME') ?: 'DDSafe',
    'app_secret' => getenv('APP_SECRET') ?: 'change-me-with-a-long-random-string-here',
    'personal_codes_enabled' => filter_var(getenv('PERSONAL_CODES_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),

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
