# Documentation CESIZen

## Structure du projet

```text
cesizen-api/
  cesizen-api/  # Backend Symfony
  cesizen-app/  # Frontend ReactJS
  doc/          # Documentation
  compose.yaml
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

Stockees dans les fichiers *.env*, *.env.test* et *.env.dev* du backend, dans `cesizen-api/`

### Lancement des containers Docker

```bash
# Lancement conteneur
docker compose up -d --build
```

Lance les conteneurs Cesizen, contenant les services suivants :
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
