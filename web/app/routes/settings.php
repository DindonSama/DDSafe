<?php

/** @var string $path */
/** @var string $method */
/** @var \App\Auth $auth */
/** @var \App\PocketBaseClient $pb */
/** @var array $config */
/** @var array $currentUser */

if ($path === '/settings' && $method === 'GET') {
    $pageTitle = 'Paramètres';
    $extensionDownloadUrl = '/extension/download';
    $extensionPageUrl = '/extension';
    $extensionAppUrl = trim((string)($config['extension']['app_url'] ?? 'http://localhost:8080'));
    $isLocalUser = empty($currentUser['is_ad_user']) && empty($currentUser['is_oidc_user']);
    require __DIR__ . '/../templates/settings.php';
    return;
}

if ($path === '/settings/password' && $method === 'POST') {
    $isLocalUser = empty($currentUser['is_ad_user']) && empty($currentUser['is_oidc_user']);
    if (!$isLocalUser) {
        flash('danger', 'Le mot de passe est géré par votre fournisseur d\'authentification (LDAP/OIDC).');
        header('Location: /settings');
        exit;
    }

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        flash('danger', 'Veuillez remplir tous les champs de mot de passe.');
        header('Location: /settings');
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        flash('danger', 'La confirmation du nouveau mot de passe ne correspond pas.');
        header('Location: /settings');
        exit;
    }

    if (strlen($newPassword) < 8) {
        flash('danger', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
        header('Location: /settings');
        exit;
    }

    try {
        // Verify current password using a temporary user-auth client.
        $verifyClient = new \App\PocketBaseClient($config['pocketbase']['url']);
        $verifyClient->authUser((string)($currentUser['email'] ?? ''), $currentPassword);

        $auth->ensureAdminToken();
        $pb->updateRecord('users', (string)$currentUser['id'], [
            'password' => $newPassword,
            'passwordConfirm' => $newPassword,
        ]);

        flash('success', 'Mot de passe modifié avec succès.');
    } catch (\Exception) {
        flash('danger', 'Mot de passe actuel invalide ou mise à jour impossible.');
    }

    header('Location: /settings');
    exit;
}

http_response_code(404);
$pageTitle = 'Page introuvable';
require __DIR__ . '/../templates/404.php';
