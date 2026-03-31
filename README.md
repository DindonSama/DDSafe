azr

Application web de gestion de codes OTP (TOTP/HOTP) avec support multi-collection et Active Directory.

## Fonctionnalités

- **Gestion de codes OTP** — Ajouter, modifier, supprimer des codes TOTP/HOTP
- **Multi-collection** — Les utilisateurs peuvent appartenir à plusieurs collections avec des rôles distincts
- **Codes personnels & partagés** — Codes OTP privés autorisables par utilisateur + codes partagés au niveau de la collection
- **Import par QR code** — Scanner un QR code via la caméra ou charger une image
- **Export QR code** — Exporter un ou plusieurs codes sous forme de QR codes imprimables
- **Recherche rapide** — Filtrage instantané des codes par nom ou émetteur
- **Active Directory / LDAP** — Authentification via compte AD
- **Rôles hiérarchiques** — Propriétaire, Administrateur, Membre, Observateur par collection
- **Chiffrement** — Les secrets OTP sont chiffrés au repos (libsodium)
- **Corbeille** — Les codes supprimés sont conservés et restaurables par un administrateur
- **Journaux de sécurité** — Journal d'audit (actions OTP) et journal des échecs d'authentification
- **Santé applicative** — Vue admin avec statut PocketBase, LDAP/OIDC, nombre de codes et derniers échecs d'auth
- **Sauvegardes PocketBase** — Consultation et gestion des sauvegardes via l'API native PocketBase
- **Thème sombre** — Interface entièrement adaptée au travail en conditions sombres

## Architecture

| Service      | Image Docker                          | Rôle                                           |
|--------------|---------------------------------------|------------------------------------------------|
| `pb_2fa`     | `ghcr.io/muchobien/pocketbase:latest` | Base de données / API REST                     |
| `php`        | `php:8.2-apache`                      | Application web PHP |

> Aucun Dockerfile n'est utilisé — uniquement des images Docker officielles.

## Démarrage rapide

### 1. Configurer

Copiez le fichier exemple et adaptez-le :

```bash
cp .env.example .env
```

Variables essentielles :

```env
APP_NAME=DDSafe
APP_SECRET=une-longue-chaine-aleatoire-ici
PB_ADMIN_EMAIL=admin@admin.com
PB_ADMIN_PASSWORD=Admin12345!
EXTENSION_APP_URL=http://localhost:8080
SESSION_TIMEOUT_SECONDS=900
LOG_MAX_ENTRIES=500
```

> `APP_SECRET` sert à dériver la clé de chiffrement des secrets OTP. **Ne le modifiez pas après la première utilisation.**

`SESSION_TIMEOUT_SECONDS` règle l'expiration de session (défaut 900s).

`LOG_MAX_ENTRIES` règle la rétention maximale des journaux (`audit_logs` + `auth_failures`).
Quand la limite est atteinte (par défaut 500), les entrées les plus anciennes sont purgées automatiquement.

### 2. Lancer

```bash
docker compose up -d
```

Le premier démarrage télécharge PocketBase, installe les extensions PHP et les dépendances Composer (prévoir quelques minutes).

### 3. Accéder

- **Application** : [http://localhost:8080](http://localhost:8080)
- **PocketBase Admin** : [http://localhost:8090/_/](http://localhost:8090/_/)

### 4. Connexion par défaut

**Interface applicative** — compte créé automatiquement au premier lancement, configurable dans le `.env` :

| Variable `.env`    | Valeur par défaut         |
|--------------------|---------------------------|
| `APP_ADMIN_EMAIL`  | `admin@2fa-manager.local` |
| `APP_ADMIN_PASSWORD` | `admin123`              |
| `APP_ADMIN_NAME`   | `Administrateur`          |

> Ces variables ne sont lues **qu'au premier lancement** (quand aucun utilisateur n'existe). Modifiez-les avant de démarrer pour la première fois, ou changez le mot de passe depuis l'interface après connexion.

**Interface PocketBase Admin** : utilisez les identifiants définis dans le `.env` (`PB_ADMIN_EMAIL` / `PB_ADMIN_PASSWORD`).

## Configuration LDAP / Active Directory

Pour activer l'authentification AD, ajoutez dans le `.env` :

```env
LDAP_ENABLED=true
LDAP_HOST=dc01.mondomaine.local
LDAP_PORT=389
LDAP_BASE_DN=DC=mondomaine,DC=local
LDAP_DOMAIN=MONDOMAINE
LDAP_USE_SSL=false
LDAP_SEARCH_FILTER=(sAMAccountName={username})
```

Les utilisateurs AD sont automatiquement créés dans PocketBase à leur première connexion.

## Gestion des droits

### Rôles globaux

| Rôle              | Description |
|-------------------|-------------|
| **Administrateur**| Accès complet : gestion des utilisateurs, collections, corbeille |
| **Utilisateur**   | Accès à ses codes OTP et aux collections dont il est membre |

> Les OTP personnels sont refusés par défaut et doivent être autorisés utilisateur par utilisateur depuis l'administration.

### Rôles par collection

| Rôle               | Voir les codes | Gérer les codes OTP | Gérer les membres | Paramètres collection |
|--------------------|:-:|:-:|:-:|:-:|
| **Propriétaire**   | ✅ | ✅ | ✅ | ✅ |
| **Administrateur** | ✅ | ✅ | ✅ | ✅ |
| **Membre**         | ✅ | ✅ | ❌ | ❌ |
| **Observateur**    | ✅ | ❌ | ❌ | ❌ |

> Le rôle par défaut lors de l'ajout d'un membre est **Observateur**.
> Un utilisateur **Propriétaire** ne peut pas modifier son propre rôle propriétaire.
> Seul le **Propriétaire** d'une collection peut supprimer cette collection.

---

## Structure du projet

```
2FA/
├── docker-compose.yml
├── .env                    # ⚠ non commité (voir .env.example)
├── .env.example            # Modèle de configuration
├── example-import.csv      # Exemple d'import CSV de membres
├── pb_data/                # ⚠ non commité — données PocketBase
└── web/
    ├── app/
    │   ├── composer.json
    │   ├── config/config.php
    │   ├── public/
    │   │   ├── index.php
    │   │   └── assets/
    │   │       ├── css/app.css
    │   │       └── js/app.js
    │   ├── src/             # Logique métier (Auth, OTP, Collections...)
    │   ├── routes/          # Contrôleurs
    │   ├── templates/       # Templates PHP
    │   └── vendor/          # ⚠ non commité — dépendances Composer
    ├── scripts/php-start.sh # Setup PHP/Apache/Composer au démarrage
    └── composer/            # ⚠ non commité — cache Composer
```

---

## Import de membres (CSV)

Importez des membres en masse dans une collection via l'interface d'administration.
Fichier attendu (voir `example-import.csv`) :

```csv
email,role
john.doe@company.com,member
manager@company.com,admin
reader@company.com,viewer
```

Rôles valides : `owner`, `admin`, `member`, `viewer` (défaut : `member`).

---

## Extension navigateur (Chrome / Edge)

Une page intégrée permet de télécharger l'extension en lecture seule directement depuis l'application :

- Ouvrez `http://localhost:8080/extension`
- Cliquez sur **Télécharger l'extension (.zip)**
- Décompressez l'archive
- Installez-la en mode développeur via `chrome://extensions` ou `edge://extensions`

L'extension est strictement en lecture seule : affichage des OTP visibles, sans modification ni suppression.

L'URL de téléchargement de l'extension est configurable dans `.env` via :

```env
EXTENSION_DOWNLOAD_URL=/extension/download
```

Chaque utilisateur dispose d'une page **Paramètres** (`/settings`) qui contient le lien d'extension.
Les utilisateurs locaux peuvent aussi y changer leur mot de passe.

---

## Réinitialiser les données

Les données sont stockées dans des dossiers locaux (bind mounts) :

```bash
# Supprimer toutes les données PocketBase
rm -rf pb_data/

# Supprimer le cache Composer
rm -rf web/composer/
```

---

## Dépendances principales

**PHP (Composer)**
- [spomky-labs/otphp](https://github.com/Spomky-Labs/otphp) — Génération TOTP/HOTP
- [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) — Génération de QR codes

**JavaScript (CDN)**
- Bootstrap 5.3.3
- Bootstrap Icons 1.11.3
- html5-qrcode (scanner QR via caméra)

---

## Sécurité

- Secrets OTP chiffrés au repos avec **libsodium** (clé dérivée de `APP_SECRET`)
- Protection **CSRF** sur tous les formulaires
- Sessions PHP (`httponly`, `samesite=Lax`)
- Validation et échappement systématiques des entrées
- Communication PocketBase ↔ PHP via réseau Docker isolé (non exposé publiquement)
- Token admin PocketBase géré côté serveur uniquement
- Expiration de session configurable via `SESSION_TIMEOUT_SECONDS`
- Journalisation des échecs d'authentification (visible dans l'administration)
- Journal d'audit des actions OTP (création, modification, export, suppression, restauration)

---

## Santé applicative

Page admin disponible via `/admin/health` :

- Statut PocketBase
- Statut LDAP (activé/configuré/connectivité)
- Statut OIDC (activé/configuré)
- Nombre de codes OTP actifs
- Derniers échecs d'authentification

---

## Sauvegardes PocketBase (via API)

Page admin disponible via `/admin/backup` :

- Créer une sauvegarde via l'API PocketBase
- Lister les sauvegardes existantes
- Télécharger une sauvegarde
- Supprimer une sauvegarde
- Restaurer une sauvegarde

Cette page pilote exclusivement les endpoints natifs PocketBase (`/api/backups`).

Note restauration :

- Une restauration peut provoquer un redémarrage du service PocketBase.
- Vérifiez l'espace disque disponible avant de restaurer une archive volumineuse.

---

## Tests automatiques

Suite de tests CLI incluse dans `web/app/tests` pour couvrir :

- Permissions (export/édition/suppression OTP)
- Login local/LDAP
- Suppression/restauration OTP

Exécution :

```bash
php web/app/tests/run.php
```

---

## Administration utilisateurs AD/OIDC

Pour les comptes fédérés (`AD` ou `OIDC`) :

- un administrateur ne peut pas modifier le mot de passe depuis l'application ;
- le mot de passe reste géré par la source d'identité externe.

---

Pour toute question ou bug, ouvrez une issue sur le dépôt.
