<?php

/** @var string $method */
/** @var string $path */
/** @var \App\Auth $auth */
/** @var array $config */

if (!($config['oidc']['enabled'] ?? false)) {
    http_response_code(404);
    $pageTitle = 'Page introuvable';
    require __DIR__ . '/../templates/404.php';
    return;
}

$oidc    = new \App\OIDCAuth($config['oidc']);
$subPath = substr($path, strlen('/auth/oidc'));

// ── GET /auth/oidc — redirect vers le fournisseur SSO ──────────────────────
if ($method === 'GET' && ($subPath === '' || $subPath === '/')) {
    try {
        $url = $oidc->getAuthorizationUrl();
        header('Location: ' . $url);
    } catch (\Exception $e) {
        error_log('OIDC redirect error: ' . $e->getMessage());
        flash('danger', 'Impossible de contacter le fournisseur SSO. Veuillez réessayer.');
        header('Location: /login');
    }
    exit;
}

// ── GET /auth/oidc/callback — retour du fournisseur SSO ───────────────────
if ($method === 'GET' && $subPath === '/callback') {
    // Erreur retournée par le fournisseur
    if (isset($_GET['error'])) {
        $desc = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
        flash('danger', 'Erreur SSO : ' . $desc);
        header('Location: /login');
        exit;
    }

    $code  = $_GET['code']  ?? '';
    $state = $_GET['state'] ?? '';

    if ($code === '' || $state === '') {
        flash('danger', 'Réponse SSO invalide (paramètres manquants).');
        header('Location: /login');
        exit;
    }

    try {
        $oidcUser = $oidc->handleCallback($code, $state);
        $success  = $auth->loginWithOidc($oidcUser);

        if ($success) {
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            flash('success', 'Connexion SSO réussie !');
            header('Location: /');
        } else {
            flash('danger', 'Votre compte SSO n\'est pas autorisé. Contactez un administrateur.');
            header('Location: /login');
        }
    } catch (\Exception $e) {
        error_log('OIDC callback error: ' . $e->getMessage());
        flash('danger', 'Erreur lors de la connexion SSO. Veuillez réessayer.');
        header('Location: /login');
    }
    exit;
}

http_response_code(404);
$pageTitle = 'Page introuvable';
require __DIR__ . '/../templates/404.php';
