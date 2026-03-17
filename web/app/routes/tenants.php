<?php

/** @var string $path */
/** @var string $method */
/** @var \App\TenantManager $tenantManager */
/** @var \App\OTPManager $otpManager */
/** @var \App\Auth $auth */
/** @var array $currentUser */

use App\PermissionManager;

$canCreateTenant = !empty($currentUser['is_app_admin']);

// ── Créer une collection ─────────────────────────────────────────
if ($path === '/tenants/create' && $method === 'GET') {
    if (!$canCreateTenant) {
        flash('danger', 'Vous n\'avez pas la permission de créer une collection.');
        header('Location: /tenants');
        exit;
    }

    $pageTitle = 'Créer une collection';
    $userTenants = $tenantManager->getUserTenants($currentUser['id']);
    require __DIR__ . '/../templates/tenants.php';
    return;
}

if ($path === '/tenants/create' && $method === 'POST') {
    if (!$canCreateTenant) {
        flash('danger', 'Vous n\'avez pas la permission de créer une collection.');
        header('Location: /tenants');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        flash('danger', 'Le nom est obligatoire.');
        header('Location: /tenants/create');
        exit;
    }
    $tenantManager->create($name, $desc, $currentUser['id']);
    flash('success', 'Collection créée avec succès.');
    header('Location: /tenants');
    exit;
}

// ── Gérer une collection ─────────────────────────────────────────
if ($path === '/tenants/manage' && $method === 'GET') {
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['id'] ?? '');
    if (!$tid) { header('Location: /tenants'); exit; }

    $tenant  = $tenantManager->getById($tid);
    if (!$tenant) { flash('danger', 'Collection introuvable.'); header('Location: /tenants'); exit; }
    $isCreator = (($tenant['created_by'] ?? '') === ($currentUser['id'] ?? ''));

    // Vérifier la permission de consultation
    if (!$auth->canDoInTenant($tid, 'view_tenant') && !$isCreator) {
        flash('danger', 'Accès refusé.');
        header('Location: /tenants');
        exit;
    }

    $role    = $auth->getUserTenantRole($tid);
    if (!$role && $isCreator) {
        // Repli de sécurité pour les données legacy sans ligne d'appartenance créateur.
        $role = 'owner';
    }
    $members = $tenantManager->getMembers($tid);
    $addableUsers = $tenantManager->getAddableUsers($tid);
    $pageTitle = 'Gérer : ' . ($tenant['name'] ?? '');
    require __DIR__ . '/../templates/tenant-manage.php';
    return;
}

// ── Mettre à jour la collection ──────────────────────────────────
if ($path === '/tenants/update' && $method === 'POST') {
    $tid  = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tenant = $tenantManager->getById($tid);
    $isCreator = $tenant && (($tenant['created_by'] ?? '') === ($currentUser['id'] ?? ''));
    
    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'edit_settings') && !$isCreator) {
        flash('danger', 'Vous n\'avez pas la permission de modifier cette collection.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }
    
    if ($tid && $name) {
        $tenantManager->update($tid, ['name' => $name, 'description' => $desc]);
        flash('success', 'Collection mise à jour.');
    }
    header("Location: /tenants/manage?id={$tid}");
    exit;
}

// ── Ajouter un membre ────────────────────────────────────────────
if ($path === '/tenants/members/add' && $method === 'POST') {
    $tid   = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $uid   = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $role  = $_POST['role'] ?? 'viewer';

    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'manage_members')) {
        flash('danger', 'Vous n\'avez pas la permission d\'ajouter des membres.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }

    if (!PermissionManager::isValidRole($role)) {
        $role = 'viewer';
    }

    $currentRole = $auth->getUserTenantRole($tid) ?? '';
    if (!PermissionManager::canManageRole($currentRole, $role)) {
        flash('danger', 'Vous ne pouvez pas attribuer ce role.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }

    if ($tid && $uid) {
        try {
            $tenantManager->addMemberById($tid, $uid, $role);
            flash('success', 'Membre ajouté avec succès.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    } else {
        flash('warning', 'Veuillez selectionner un utilisateur.');
    }
    header("Location: /tenants/manage?id={$tid}");
    exit;
}

// ── Mettre à jour le rôle d'un membre ───────────────────────────
if ($path === '/tenants/members/update' && $method === 'POST') {
    $mid  = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $tid  = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $role = $_POST['role'] ?? 'member';
    
    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'manage_roles')) {
        flash('danger', 'Vous n\'avez pas la permission de modifier les rôles.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }
    
    if (!PermissionManager::isValidRole($role)) {
        $role = 'member';
    }

    $currentRole = $auth->getUserTenantRole($tid) ?? '';
    $membership = $mid ? $tenantManager->getMembershipById($mid) : null;
    $targetCurrentRole = $membership['role'] ?? '';
    $targetUserId = (string)($membership['user'] ?? '');

    if ($targetCurrentRole === 'owner' && $targetUserId === (string)($currentUser['id'] ?? '')) {
        flash('danger', 'Vous ne pouvez pas modifier votre propre rôle propriétaire.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }

    if (!PermissionManager::canManageRole($currentRole, $role)
        || !PermissionManager::canManageRole($currentRole, $targetCurrentRole)) {
        flash('danger', 'Vous ne pouvez pas modifier ce role.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }

    if ($mid) {
        try {
            $tenantManager->updateMemberRole($mid, $role);
            flash('success', 'Rôle mis à jour.');
        } catch (\Exception $e) {
            flash('danger', 'Impossible de mettre a jour le role: ' . $e->getMessage());
        }
    }
    header("Location: /tenants/manage?id={$tid}");
    exit;
}

// ── Retirer un membre ────────────────────────────────────────────
if ($path === '/tenants/members/remove' && $method === 'POST') {
    $mid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    
    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'manage_members')) {
        flash('danger', 'Vous n\'avez pas la permission de retirer des membres.');
        header("Location: /tenants/manage?id={$tid}");
        exit;
    }
    
    if ($mid) {
        $membership = $tenantManager->getMembershipById($mid);
        $targetCurrentRole = (string)($membership['role'] ?? '');
        $targetUserId = (string)($membership['user'] ?? '');
        if ($targetCurrentRole === 'owner' && $targetUserId === (string)($currentUser['id'] ?? '')) {
            flash('danger', 'Vous ne pouvez pas vous retirer en tant que propriétaire.');
            header("Location: /tenants/manage?id={$tid}");
            exit;
        }

        $tenantManager->removeMember($mid);
        flash('success', 'Membre retiré.');
    }
    header("Location: /tenants/manage?id={$tid}");
    exit;
}

// ── Sélectionner une collection ──────────────────────────────────
if ($path === '/tenants/select' && $method === 'POST') {
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $_SESSION['current_tenant'] = $tid ?: null;
    header('Location: ' . ($_POST['redirect'] ?? '/'));
    exit;
}

// ── Supprimer une collection ─────────────────────────────────────
if ($path === '/tenants/delete' && $method === 'POST') {
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    $tenant = $tid ? $tenantManager->getById($tid) : null;
    $membership = $tid ? $tenantManager->getMembership($tid, (string)($currentUser['id'] ?? '')) : null;
    $isOwner = (($membership['role'] ?? '') === 'owner')
        || ($tenant && (($tenant['created_by'] ?? '') === (string)($currentUser['id'] ?? '')));

    if (!$isOwner) {
        flash('danger', 'Vous n\'avez pas la permission de supprimer cette collection.');
        header('Location: /tenants');
        exit;
    }
    
    if ($tid) {
        try {
            $deleted = $tenantManager->delete($tid, (string)($currentUser['id'] ?? ''));
            if (!$deleted) {
                flash('danger', 'La suppression de la collection a échoué.');
                header('Location: /tenants');
                exit;
            }
            if (($_SESSION['current_tenant'] ?? '') === $tid) {
                unset($_SESSION['current_tenant']);
            }
            flash('success', 'Collection déplacée dans la corbeille.');
        } catch (\Exception $e) {
            flash('danger', 'Impossible de supprimer la collection : ' . $e->getMessage());
        }
    }
    header('Location: /tenants');
    exit;
}

// ── Page d'import des membres ────────────────────────────────────
if ($path === '/tenants/members/import' && $method === 'GET') {
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['id'] ?? '');
    if (!$tid) { header('Location: /tenants'); exit; }

    $tenant = $tenantManager->getById($tid);
    if (!$tenant) { flash('danger', 'Collection introuvable.'); header('Location: /tenants'); exit; }

    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'manage_members')) {
        flash('danger', 'Accès refusé.');
        header('Location: /tenants');
        exit;
    }

    $addableUsers = $tenantManager->getAddableUsers($tid);
    $pageTitle = 'Importer des membres : ' . ($tenant['name'] ?? '');
    require __DIR__ . '/../templates/tenant-import.php';
    return;
}

// ── Traiter l'import en masse ────────────────────────────────────
if ($path === '/tenants/members/bulk-import' && $method === 'POST') {
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $currentRole = $auth->getUserTenantRole($tid) ?? '';
    
    // Vérifier la permission
    if (!$auth->canDoInTenant($tid, 'manage_members')) {
        flash('danger', 'Vous n\'avez pas la permission d\'ajouter des membres.');
        header('Location: /tenants');
        exit;
    }

    if ($tid && !empty($_FILES['csv_file'])) {
        try {
            $file = $_FILES['csv_file']['tmp_name'];
            $members = [];
            
            if (($handle = fopen($file, 'r')) !== false) {
                // Ignorer l'en-tête si présent
                $header = fgetcsv($handle, 1000, ',');
                
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    if (count($row) >= 1 && !empty($row[0])) {
                        $candidateRole = trim((string)($row[1] ?? 'member'));
                        if (!PermissionManager::isValidRole($candidateRole)) {
                            $candidateRole = 'member';
                        }
                        $members[] = [
                            'email' => trim($row[0]),
                            'role'  => $candidateRole,
                        ];
                    }
                }
                fclose($handle);
            }

            if (empty($members)) {
                flash('warning', 'Aucun utilisateur à ajouter.');
            } else {
                $allowed = [];
                $blocked = [];
                foreach ($members as $member) {
                    $candidateRole = (string)($member['role'] ?? 'member');
                    if (PermissionManager::canManageRole($currentRole, $candidateRole)) {
                        $allowed[] = $member;
                    } else {
                        $blocked[] = [
                            'email' => (string)($member['email'] ?? ''),
                            'error' => 'Role non autorise pour votre niveau',
                        ];
                    }
                }

                $result = $tenantManager->addMembersInBulk($tid, $allowed);
                $result['failed'] = array_merge($result['failed'], $blocked);
                $successCount = count($result['success']);
                $failedCount = count($result['failed']);
                
                if ($successCount > 0) {
                    flash('success', "$successCount membre(s) ajouté(s) avec succès.");
                }
                if ($failedCount > 0) {
                    $failedEmails = implode(', ', array_column($result['failed'], 'email'));
                    flash('warning', "$failedCount membre(s) n'ont pas pu être ajoutés : $failedEmails");
                }
            }
        } catch (\Exception $e) {
            flash('danger', 'Erreur lors du traitement du fichier : ' . $e->getMessage());
        }
    }
    header("Location: /tenants/manage?id={$tid}");
    exit;
}

// ── Lister les collections (par défaut) ──────────────────────────
$pageTitle   = 'Mes collections';
$userTenants = $tenantManager->getUserTenants($currentUser['id']);
require __DIR__ . '/../templates/tenants.php';
