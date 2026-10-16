# Symfony API — TP

Ce dépôt contient une API Symfony développée dans le cadre d'un TP. L'objectif est de gérer plusieurs ressources via des endpoints REST, avec authentification JWT, gestion des utilisateurs, fichiers, éditeurs, catégories et jeux vidéo.

Ce README est entièrement rédigé en français.

---

## Sommaire

- Prérequis
- Installation depuis GitHub
- Configuration de l'environnement
- Installation de XAMPP, Postman et Mailtrap
- Accès à phpMyAdmin
- Démarrage du serveur Symfony
- Migrations et fixtures
- Génération des clés JWT
- Authentification via Postman
- Utilisation des routes de l'API
- Commande Symfony `app:newsletter`
- Dépannage rapide

---

## Prérequis

Avant de commencer, assurez-vous d'avoir installé sur votre machine :

- Git
- PHP 8.4+
- Composer
- Symfony CLI (recommandé)
- XAMPP (pour MySQL + Apache + phpMyAdmin)
- Postman
- Un compte Mailtrap

---

## Installation depuis GitHub

1. Ouvrez un terminal.
2. Clonez le dépôt GitHub :

```bash
git clone https://github.com/EvaValenti/symfony-api.git
cd symfony-api
```

3. Installez les dépendances PHP avec Composer :

```bash
composer install
```

4. Copiez le fichier d'environnement et configurez votre projet :

```bash
cp .env .env.local
```

Ensuite, éditez le fichier `.env.local` avec votre éditeur favori pour définir :

- `DATABASE_URL`
- `MAILER_DSN`
- les variables JWT si nécessaire

---

## Configuration de l'environnement depuis le terminal

### 1) Installer les dépendances

```bash
composer install
```

### 2) Créer la base de données

```bash
php bin/console doctrine:database:create
```

### 3) Lancer les migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 4) Charger les fixtures (si disponibles)

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 5) Démarrer le serveur Symfony

Option recommandée : Symfony CLI

```bash
symfony server:start --allow-http --port=8000 -d
```

Ou en mode PHP intégré :

```bash
php -S 127.0.0.1:8000 -t public
```

Vérifiez que le site s'ouvre bien ici :

```text
http://127.0.0.1:8000
```

---

## Installation de XAMPP

### Étapes

1. Téléchargez XAMPP depuis : https://www.apachefriends.org/
2. Installez le logiciel.
3. Démarrez Apache et MySQL depuis le panneau de contrôle XAMPP.
4. Vérifiez que phpMyAdmin est accessible :

```text
http://127.0.0.1/phpmyadmin
```

### Configuration MySQL

Dans `.env.local`, configurez une URL de type :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/symfony_api"
```

Si vous avez un mot de passe sur MySQL, remplacez le bloc selon votre configuration.

---

## Accès à phpMyAdmin

Une fois XAMPP lancé :

```text
http://127.0.0.1/phpmyadmin
```

Vous pouvez alors :
- créer la base de données,
- consulter les tables,
- vérifier les données insérées par les migrations ou fixtures.

---

## Installation de Postman

1. Téléchargez Postman depuis : https://www.postman.com/downloads/
2. Installez le logiciel.
3. Ouvrez Postman et configurez une requête pour l'API.

Exemple de requête pour récupérer un token JWT :

- Méthode : POST
- URL : `http://127.0.0.1:8000/api/login_check`
- Headers : `Content-Type: application/json`
- Body (raw JSON) :

```json
{
  "username": "user@example.com",
  "password": "plainpassword"
}
```

---

## Installation de Mailtrap

1. Créez un compte sur : https://mailtrap.io/
2. Connectez-vous à votre inbox Mailtrap.
3. Récupérez vos identifiants SMTP.
4. Configurez le DSN dans `.env.local` :

```env
MAILER_DSN=smtp://username:password@smtp.mailtrap.io:2525
```

Remplacez `username` et `password` par vos vrais identifiants Mailtrap.

---

## Génération des clés JWT

Le projet utilise Lexik JWT pour l'authentification.

Dans le terminal, à la racine du projet :

```bash
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair
```

Vérifiez ensuite que ces fichiers existent :

- `config/jwt/private.pem`
- `config/jwt/public.pem`

Et dans `.env.local` :

```env
JWT_PRIVATE_KEY_PATH=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY_PATH=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
```

---

## Création d'un JWT token dans Postman

1. Démarrez le serveur Symfony.
2. Ouvrez Postman.
3. Créez une requête de type `POST`.
4. URL :

```text
http://127.0.0.1:8000/api/login_check
```

5. Dans l'onglet `Body`, choisissez `raw` puis `JSON`.
6. Envoyez les informations de connexion :

```json
{
  "username": "user@example.com",
  "password": "plainpassword"
}
```

7. Si les identifiants sont bons, vous obtenez une réponse du type :

```json
{
  "token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

8. Copiez la valeur du token.
9. Ajoutez le header suivant pour les routes protégées :

```http
Authorization: Bearer <votre_token>
```

---

## Utilisation des routes de l'API

Les routes disponibles dans ce projet sont les suivantes.

### Utilisateurs

#### Liste des utilisateurs

```http
GET /api/v1/users
```

Paramètres optionnels :
- `page` : numéro de page
- `limit` : nombre d'éléments par page

Exemple :

```http
GET http://127.0.0.1:8000/api/v1/users?page=1&limit=5
```

#### Récupérer un utilisateur par ID

```http
GET /api/v1/user/{id}
```

Exemple :

```http
GET http://127.0.0.1:8000/api/v1/user/1
```

#### Créer un utilisateur

```http
POST /api/v1/user/create
```

Exemple de body JSON :

```json
{
  "email": "newuser@example.com",
  "password": "Azerty123!",
  "roles": ["ROLE_USER"],
  "subscription_to_newsletter": true
}
```

#### Mettre à jour un utilisateur

```http
PUT /api/v1/user/update/{id}
```

#### Supprimer un utilisateur

```http
DELETE /api/v1/user/delete/{id}
```

---

### Catégories

#### Liste des catégories

```http
GET /api/v1/categories
```

#### Détail d'une catégorie

```http
GET /api/v1/category/{id}
```

#### Créer une catégorie

```http
POST /api/v1/category/create
```

#### Modifier une catégorie

```http
PUT /api/v1/category/update/{id}
```

#### Supprimer une catégorie

```http
DELETE /api/v1/category/delete/{id}
```

---

### Éditeurs

#### Liste des éditeurs

```http
GET /api/v1/editors
```

#### Détail d'un éditeur

```http
GET /api/v1/editor/{id}
```

#### Créer un éditeur

```http
POST /api/v1/editor/create
```

#### Modifier un éditeur

```http
PUT /api/v1/editor/update/{id}
```

#### Supprimer un éditeur

```http
DELETE /api/v1/editor/delete/{id}
```

---

### Fichiers

#### Liste des fichiers

```http
GET /api/v1/files
```

#### Créer un fichier

```http
POST /api/v1/file/create
```

#### Modifier un fichier

```http
PUT /api/v1/file/update/{id}
```

#### Supprimer un fichier

```http
DELETE /api/v1/file/delete/{id}
```

---

### Jeux vidéo

#### Liste des jeux vidéo

```http
GET /api/v1/videogames
```

#### Détail d'un jeu vidéo

```http
GET /api/v1/videogame/{id}
```

#### Créer un jeu vidéo

```http
POST /api/v1/videogame/create
```

Pour l'image de couverture, utilisez le champ :

- `coverImage`

avec `multipart/form-data` dans Postman.

#### Modifier un jeu vidéo

```http
PUT /api/v1/videogame/update/{id}
```

#### Supprimer un jeu vidéo

```http
DELETE /api/v1/videogame/delete/{id}
```

---

### Route mailer

```http
GET /email?to=votre-email@example.com
```

ou

```http
POST /email
```

Dans Postman, en `form-data`, ajoutez :

- `to`: adresse email de destination

---

## Commande `app:newsletter`

La commande console suivante permet d'envoyer la newsletter à tous les utilisateurs abonnés :

```bash
php bin/console app:newsletter
```

### À quoi sert la commande ?

- récupère les utilisateurs abonnés à la newsletter,
- vérifie la liste des abonnés,
- envoie un email à chacun via Mailtrap.

### Exemple d'utilisation

```bash
php bin/console app:newsletter
```

Si aucun abonné ne figure dans la base, la commande affichera un message de type :

```text
Aucun abonné trouvé pour la newsletter.
```

---

## Dépannage rapide

### Problème de JWT : `The key "username" must be provided.`

Cela arrive si le firewall attend le champ `username` par défaut.

Dans `security.yaml`, ajoutez :

```yaml
json_login:
  check_path: /api/login_check
  username_path: email
  password_path: password
```

Et envoyez alors le body suivant :

```json
{
  "email": "user@example.com",
  "password": "plainpassword"
}
```

### Problème de base de données

Vérifiez :
- `DATABASE_URL` dans `.env.local`
- le serveur MySQL dans XAMPP
- les migrations

### Problème d'email Mailtrap

Vérifiez :
- `MAILER_DSN`
- que Mailtrap est bien configuré
- que le serveur Mailtrap accepte les connexions SMTP

### Problème PHP / IDE

Le projet exige PHP 8.4+, donc assurez-vous que votre IDE et l'analyseur PHP sont configurés pour cette version.

---

## Conclusion

Après installation, configuration de la base, génération des clés JWT et démarrage du serveur, vous devez être capable de :

- créer un compte utilisateur,
- obtenir un token JWT,
- tester toutes les routes API depuis Postman,
- envoyer des emails via Mailtrap,
- utiliser la commande `app:newsletter` pour les abonnés.

Si vous avez besoin d'un support plus avancé, vous pouvez ensuite ajouter :
- des DTOs,
- une documentation Swagger avec Nelmio,
- des tests fonctionnels,
- ou un système de rôles plus détaillé.
