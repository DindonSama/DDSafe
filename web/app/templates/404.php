<?php ob_start(); ?>

<div class="empty-state" style="padding:5rem 1rem">
    <div class="empty-icon" style="font-size:4rem"><i class="bi bi-exclamation-triangle"></i></div>
    <h2 style="color:var(--text-primary);font-size:1.4rem;margin:.75rem 0 .5rem">Page introuvable</h2>
    <p>La page que vous cherchez n'existe pas.</p>
    <a href="/" class="btn btn-accent mt-2">
        <i class="bi bi-house me-1"></i>Retour à l'accueil
    </a>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
