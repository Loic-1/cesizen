## Guide d'installation

### Technologies requises

* Docker
* Git

### Cloner le repository

```bash

git clone https://github.com/Loic-1/cesizen-api

cd cesizen-api

```

### Variables d'environnement

Utilisez les fichiers *.env*, *.env.test* et *.env.dev* de ce repo

### Lancement des containers Docker

```bash

docker compose up -d --build

```

* Lance **MariaDB**
* Lance **Mailpit**
* Lance **PHPMyAdmin**
* Lance l'**api**

### Liens utiles

[http://localhost:5173](http://localhost:5173) &rarr; Frontend **React**

[http://localhost:8080](http://localhost:8080) &rarr; Interface web **PHPMyAdmin**

[http://localhost:8025](http://localhost:%208025) &rarr; Interface web **Mailpit**

[Collection Postman](https://github.com/Loic-1/cesizen-api/blob/main/doc/cesizen-api.postman_collection.json)

