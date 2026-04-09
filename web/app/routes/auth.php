<?php

/** @var string $method */
/** @var \App\Auth $auth */
/** @var array $config */
/** @var \App\SecurityLogger $securityLogger */

if ($method === 'GET') {
    if ($auth->isAuthenticated()) {
        header('Location: /');
        exit;
    }
    $pageTitle  = 'Connexion';
    $ldapEnabled = $config['ldap']['enabled'];
    $oidcEnabled = $config['oidc']['enabled'] ?? false;
    $oidcButtonLabel = $config['oidc']['button_label'] ?? 'Se connecter via SSO';
    $rememberedIdentity = trim($_COOKIE['remember_login'] ?? '');
    $defaultLoginType = $ldapEnabled ? 'ldap' : 'local';
    $rememberedLoginType = trim($_COOKIE['remember_login_type'] ?? $defaultLoginType);
    if (!in_array($rememberedLoginType, ['local', 'ldap'], true)) {
        $rememberedLoginType = $defaultLoginType;
    }
    if ($rememberedLoginType === 'ldap' && !$ldapEnabled) {
        $rememberedLoginType = 'local';
    }
    require __DIR__ . '/../templates/login.php';
    return;
}

// POST /login
$loginType = $_POST['login_type'] ?? (!empty($config['ldap']['enabled']) ? 'ldap' : 'local');
$identity  = trim($_POST['identity'] ?? '');
$password  = $_POST['password'] ?? '';
$rememberIdentity = !empty($_POST['remember_identity']);

if ($identity === '' || $password === '') {
    $securityLogger->logAuthFailure($identity, $loginType, 'missing_fields');
    flash('danger', 'Veuillez remplir tous les champs.');
    header('Location: /login');
    exit;
}

$success = false;

if ($loginType === 'ldap' && $config['ldap']['enabled']) {
    $ldap     = new \App\LdapAuth($config['ldap']);
    $ldapUser = $ldap->authenticate($identity, $password);
    if ($ldapUser) {
        $success = $auth->loginWithLdap($ldapUser);
    }
} else {
    $success = $auth->login($identity, $password);
}

if ($success) {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();

    if ($rememberIdentity) {
        $rememberedType = ($loginType === 'ldap' && $config['ldap']['enabled']) ? 'ldap' : 'local';
        setcookie('remember_login', $identity, [
            'expires'  => time() + (365 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('remember_login_type', $rememberedType, [
            'expires'  => time() + (365 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie('remember_login', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('remember_login_type', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    flash('success', 'Connexion réussie !');
    header('Location: /');
} else {
    $securityLogger->logAuthFailure($identity, $loginType, 'invalid_credentials');
    flash('danger', 'Identifiants incorrects.');
    header('Location: /login');
}
exit;
