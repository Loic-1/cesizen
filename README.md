## Guide d'installation

### Technologies requises

* Docker
* Git

### Cloner le repository

```bash

# Récupère le projet depuis le repository GitHub
git clone https://github.com/Loic-1/cesizen-api

# Pour rentrer dans le dossier du projet
cd cesizen-api

```

### Variables d'environnement

Stockées dans les fichiers *.env*, *.env.test* et *.env.dev* de ce repo

### Lancement des containers Docker

```bash

# Lancement conteneur
docker compose up -d --build

```

Lance le conteneur *cesizen-api*, contenant les services suivants :
* database-1 : **MariaDB**, SGBD relationnel pour le stockage des données
* mailpit-1 : **Mailpit**, outil de test SMTP pour l'envoi des mails
* phpmyadmin-1 : **PHPMyAdmin**, interface web d'administration de la base de données
* api-1 : L'**api**, le backend de cesizen

### Liens utiles

[http://localhost:5173](http://localhost:5173) &rarr; Frontend **React**

[http://localhost:8080](http://localhost:8080) &rarr; Interface web **PHPMyAdmin**

[http://localhost:8025](http://localhost:%208025) &rarr; Interface web **Mailpit**

[Collection Postman](https://github.com/Loic-1/cesizen-api/blob/main/doc/cesizen-api.postman_collection.json) &rarr; Pour tester les endpoints

