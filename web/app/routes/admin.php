<?php

/** @var string $path */
/** @var string $method */
/** @var \App\TenantManager $tenantManager */
/** @var \App\PocketBaseClient $pb */
/** @var array $currentUser */

use App\PermissionManager;

// Réservé aux administrateurs de l'application
if (empty($currentUser['is_app_admin'])) {
    flash('danger', 'Accès refusé.');
    header('Location: /');
    exit;
}

// ── Créer un utilisateur ─────────────────────────────────────────
if ($path === '/admin/users/create' && $method === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $isAdmin  = !empty($_POST['is_app_admin']);

    if ($email === '' || $password === '') {
        flash('danger', 'Email et mot de passe sont obligatoires.');
        header('Location: /admin/users');
        exit;
    }

    try {
        $pb->createRecord('users', [
            'email'           => $email,
            'password'        => $password,
            'passwordConfirm' => $password,
            'name'            => $name,
            'is_app_admin'    => $isAdmin,
            'allow_personal_otp' => false,
            'is_ad_user'      => false,
            'ad_username'     => '',
        ]);
        flash('success', 'Utilisateur créé.');
    } catch (\Exception $e) {
        flash('danger', 'Erreur : ' . $e->getMessage());
    }
    header('Location: /admin/users');
    exit;
}

// ── Basculer le statut admin ─────────────────────────────────────
if ($path === '/admin/users/toggle-admin' && $method === 'POST') {
    $uid     = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $isAdmin = !empty($_POST['is_app_admin']);
    if ($uid) {
        $pb->updateRecord('users', $uid, ['is_app_admin' => $isAdmin]);
        flash('success', 'Statut administrateur mis à jour.');
    }
    header('Location: /admin/users');
    exit;
}

// ── Basculer la permission OTP personnel ────────────────────────
if ($path === '/admin/users/toggle-personal-otp' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $allowPersonalOtp = !empty($_POST['allow_personal_otp']);

    if ($uid) {
        $pb->updateRecord('users', $uid, ['allow_personal_otp' => $allowPersonalOtp]);
        if ($uid === (string)($currentUser['id'] ?? '')) {
            $_SESSION['user']['allow_personal_otp'] = $allowPersonalOtp;
        }
        flash(
            'success',
            $allowPersonalOtp
                ? 'OTP personnels autorisés pour cet utilisateur.'
                : 'OTP personnels désactivés pour cet utilisateur.'
        );
    }

    header('Location: /admin/users');
    exit;
}

// ── Supprimer un utilisateur ─────────────────────────────────────
if ($path === '/admin/users/delete' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    if ($uid && $uid !== $currentUser['id']) {
        $pb->deleteRecord('users', $uid);
        flash('success', 'Utilisateur supprimé.');
    }
    header('Location: /admin/users');
    exit;
}

// ── Modifier un utilisateur ──────────────────────────────────────
if ($path === '/admin/users/edit' && $method === 'POST') {
    $uid      = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($uid && $email !== '') {
        $data = ['email' => $email, 'name' => $name];
        if ($password !== '') {
            $data['password']        = $password;
            $data['passwordConfirm'] = $password;
        }
        try {
            $pb->updateRecord('users', $uid, $data);
            flash('success', 'Utilisateur modifié.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users');
    exit;
}

// ── Gérer les appartenances utilisateur aux collections ─────────
if ($path === '/admin/users/tenants/add' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'viewer'));

    if (!PermissionManager::isValidRole($role) || $role === 'owner') {
        $role = 'viewer';
    }

    if ($uid && $tid) {
        try {
            $tenantManager->addMemberById($tid, $uid, $role);
            flash('success', 'Collection ajoutée à l\'utilisateur.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

if ($path === '/admin/users/tenants/update' && $method === 'POST') {
    $mid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'viewer'));
    $uid = '';

    if ($mid) {
        $membership = $tenantManager->getMembershipById($mid);
        $currentRole = (string)($membership['role'] ?? '');
        $uid = (string)($membership['user'] ?? '');

        if ($currentRole === 'owner') {
            flash('danger', 'Le rôle propriétaire ne peut pas être modifié ici.');
            header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
            exit;
        }

        if (!PermissionManager::isValidRole($role) || $role === 'owner') {
            $role = 'viewer';
        }

        try {
            $tenantManager->updateMemberRole($mid, $role);
            flash('success', 'Rôle collection mis à jour.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

if ($path === '/admin/users/tenants/remove' && $method === 'POST') {
    $mid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $uid = '';
    if ($mid) {
        $membership = $tenantManager->getMembershipById($mid);
        $uid = (string)($membership['user'] ?? '');
        if (($membership['role'] ?? '') === 'owner') {
            flash('danger', 'Le propriétaire ne peut pas être retiré ici.');
            header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
            exit;
        }

        try {
            $tenantManager->removeMember($mid);
            flash('success', 'Utilisateur retiré de la collection.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

// ── Lister les utilisateurs (par défaut) ────────────────────────
if ($path === '/admin/users') {
    $pageTitle = 'Gestion des utilisateurs';
    $openTenantUserId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['open_tenant_user'] ?? '');
    $allUsers  = $tenantManager->getAllUsers();
    $allTenants = $tenantManager->getAllTenants();
    $membershipsByUser = [];
    foreach ($allUsers as $u) {
        $uid = (string)($u['id'] ?? '');
        if ($uid === '') {
            continue;
        }
        $membershipsByUser[$uid] = $tenantManager->getUserMemberships($uid);
    }
    require __DIR__ . '/../templates/admin-users.php';
    return;
}

// ── Corbeille : lister les codes supprimés ──────────────────────
if ($path === '/admin/trash' && $method === 'GET') {
    $pageTitle   = 'Corbeille';
    $trashedCodes = $otpManager->getTrashedCodes();
    $trashedTenants = $tenantManager->getTrashedTenants();
    require __DIR__ . '/../templates/admin-trash.php';
    return;
}

// ── Corbeille : restaurer un code ────────────────────────────────
if ($path === '/admin/trash/restore' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $otpManager->restore($id);
        flash('success', 'Code OTP restauré.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille : suppression définitive ──────────────────────────
if ($path === '/admin/trash/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $otpManager->permanentDelete($id);
        flash('success', 'Code OTP définitivement supprimé.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille collections : restaurer ───────────────────────────
if ($path === '/admin/trash/tenant/restore' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $tenantManager->restore($id);
        flash('success', 'Collection restaurée.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille collections : suppression définitive ──────────────
if ($path === '/admin/trash/tenant/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $tenantManager->permanentDelete($id);
        flash('success', 'Collection définitivement supprimée.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille : tout vider ──────────────────────────────────────
if ($path === '/admin/trash/empty' && $method === 'POST') {
    $trashedCodes = $otpManager->getTrashedCodes();
    foreach ($trashedCodes as $code) {
        $otpManager->permanentDelete($code['id']);
    }
    $trashedTenants = $tenantManager->getTrashedTenants();
    foreach ($trashedTenants as $tenant) {
        $tenantManager->permanentDelete((string)$tenant['id']);
    }
    flash('success', 'Corbeille vidée.');
    header('Location: /admin/trash');
    exit;
}

http_response_code(404);
$pageTitle = 'Page introuvable';
require __DIR__ . '/../templates/404.php';
