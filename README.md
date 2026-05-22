# Documentation CESIZen

## Structure du projet

```text
cesizen-api/
  .github/          # Jobs de tests et d'upload d'image Docker
  cesizen-api/      # Backend Symfony
  cesizen-app/      # Frontend ReactJS
  doc/              # Documentation
  .env.example      # Exemple contenu .env
  compose.dev.yaml  # Compose environnement de développement
  compose.prod.yaml # Compose environnement de production
  compose.ps1       # Script d'exécution du compose
  README.md
```

## Guide d'installation

### Technologies requises

* [Docker](https://www.docker.com/products/docker-desktop/) pour lancer le conteneur de l'application
* [Git](https://git-scm.com/install/) (optionnel) pour faire la gestion des versions du code

### Cloner le repository

```bash
# Recupere le projet depuis le repository GitHub
git clone https://github.com/Loic-1/cesizen-api

# Pour rentrer dans le dossier du projet
cd cesizen-api
```

### Variables d'environnement

Faire une copie du fichier .env.example et le renommer .env.

### Lancement des containers Docker

Ce projet comprend deux packages d'image pour l'[API Symfony](https://github.com/Loic-1/cesizen-api/pkgs/container/cesizen-api-api) et l'[app VueJS](https://github.com/Loic-1/cesizen-api/pkgs/container/cesizen-api-app)
À chaque push et PR, l'image est ré-uploadée avec le tag correspondant à la branche afin de rester à jour.

Chaque package possède deux tags: **latest** pour l'image de production et **dev** pour l'image de développement.

Avec Powershell
```shell
# Lancement conteneur en dev (choisit par défaut l'image de dev)
./compose.ps1

# OU
.compose.ps1 dev

# Lancement conteneur en prod
./compose.ps1 prod
```

Directement en CLI Docker
```bash
# Lancement conteneur en dev
docker compose -f ./compose.dev.yaml up -d --build

# Lancement conteneur en dev
docker compose -f ./compose.prod.yaml up -d --build
```

Le conteneur Cesizen possède les services suivants :
* app-1 : le frontend **React/Vite** sur `http://localhost:5173`
* database-1 : **MariaDB**, SGBD relationnel pour le stockage des donnees
* mailpit-1 : **Mailpit**, outil de test SMTP pour l'envoi des mails
* phpmyadmin-1 : **PHPMyAdmin**, interface web d'administration de la base de donnees
* api-1 : l'**api**, le backend de Cesizen

### Liens utiles

[http://localhost:5173](http://localhost:5173) &rarr; Application web **ReactJS**

[http://localhost:8080](http://localhost:8080) &rarr; Interface web **PHPMyAdmin**

[http://localhost:8025](http://localhost:8025) &rarr; Interface web **Mailpit**

[Collection Postman](https://github.com/Loic-1/cesizen-api/blob/main/doc/cesizen-api.postman_collection.json) &rarr; Pour tester les endpoints
