<?php

/** @var string $path */
/** @var string $method */
/** @var array $config */

$zipFileName = 'ddsafe-otp-viewer-readonly.zip';
$packageDir  = __DIR__ . '/../public/extension-package';
$extensionAppUrl = rtrim(trim((string)($config['extension']['app_url'] ?? 'http://localhost:8080')), '/');
if ($extensionAppUrl === '') {
    $extensionAppUrl = 'http://localhost:8080';
}

if ($path === '/extension/update-info' && $method === 'GET') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    $manifestPath = $packageDir . '/manifest.json';
    $latestVersion = '0.0.0';

    if (is_file($manifestPath)) {
        $raw = file_get_contents($manifestPath);
        $json = $raw ? json_decode($raw, true) : null;
        if (is_array($json) && !empty($json['version'])) {
            $latestVersion = (string)$json['version'];
        }
    }

    echo json_encode([
        'latestVersion' => $latestVersion,
        'updatePageUrl' => '/extension',
        'downloadUrl' => '/extension/download',
    ]);
    exit;
}

if ($path === '/extension/download' && $method === 'GET') {
    if (!is_dir($packageDir)) {
        flash('danger', 'Package extension introuvable. Contactez un administrateur.');
        header('Location: /extension');
        exit;
    }

    if (!class_exists('ZipArchive')) {
        flash('danger', 'Le support ZIP n\'est pas disponible sur ce serveur.');
        header('Location: /extension');
        exit;
    }

    $tmpBase = tempnam(sys_get_temp_dir(), 'ext_');
    if ($tmpBase === false) {
        flash('danger', 'Impossible de préparer le téléchargement.');
        header('Location: /extension');
        exit;
    }
    $zipPath = $tmpBase . '.zip';

    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpBase);
        flash('danger', 'Impossible de créer le package ZIP.');
        header('Location: /extension');
        exit;
    }

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($packageDir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $fullPath = $file->getPathname();
        $local    = substr($fullPath, strlen($packageDir) + 1);

        if ($local === 'popup.js' || $local === 'options.js') {
            $content = file_get_contents($fullPath);
            if ($content === false) {
                continue;
            }
            $content = str_replace(
                ['__DDSAFE_APP_URL__', 'http://localhost:8080'],
                $extensionAppUrl,
                $content
            );
            $zip->addFromString($local, $content);
            continue;
        }

        $zip->addFile($fullPath, $local);
    }

    $zip->close();
    @unlink($tmpBase);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
    header('Content-Length: ' . (string)filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

if ($path === '/extension' && $method === 'GET') {
    $pageTitle = 'Extension Navigateur';
    $extensionDownloadUrl = '/extension/download';
    $extensionConfiguredAppUrl = $extensionAppUrl;
    $zipExists = is_dir($packageDir);
    $zipSizeMb = 0;
    require __DIR__ . '/../templates/extension.php';
    return;
}

http_response_code(404);
$pageTitle = 'Page introuvable';
require __DIR__ . '/../templates/404.php';
