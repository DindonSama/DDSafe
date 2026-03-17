# DDSafe OTP Viewer (Read Only)

Extension navigateur compatible Chrome et Edge (Manifest V3).

## Objectif

Afficher les codes OTP visibles dans l'onglet DDSafe actif, en lecture seule.

- Aucun import
- Aucune modification
- Aucune suppression
- Aucun appel d'ecriture

## Installation locale (mode developpeur)

1. Ouvrir le navigateur
2. Aller dans la page Extensions
3. Activer le mode Developpeur
4. Cliquer sur "Charger l'extension non empaquetee"
5. Selectionner ce dossier: `browser-extension/`

## Usage

1. Ouvrir DDSafe sur la page OTP (ex: http://localhost:8080/otp)
2. Se connecter dans DDSafe (une fois)
3. Cliquer sur l'icone de l'extension
4. Cliquer sur "Rafraichir"

L'extension lit les OTP via l'API DDSafe (lecture seule), sans onglet DDSafe actif.

## Recherche

Le popup contient un champ + bouton "Rechercher" pour filtrer par nom, emetteur ou collection.

## Mise a jour

Le popup contient un bouton "Verifier MAJ".

- Si une nouvelle version existe, l'extension propose d'ouvrir la page extension.
- La mise a jour reste manuelle en mode non-store : desinstaller puis reinstaller depuis la page web.

## Changer l'URL apres installation

Vous pouvez modifier l'URL DDSafe a tout moment :

1. Ouvrez la page "Options" de l'extension
2. Modifiez le champ "URL DDSafe"
3. Cliquez sur "Enregistrer"

Le popup n'affiche pas l'URL de configuration.

## Configuration

Dans les options de l'extension, definir l'URL DDSafe (par defaut: http://localhost:8080).
