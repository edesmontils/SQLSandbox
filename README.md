# SQLSandbox
 
Cette petite application Web a été développée à des fins pédagogiques. Elle ne peut donc aucunement être utilisée pour d'autres desseins. Elle est mise à disposition dans la mesure où son utilisation n'est pas détournée. L'auteur ne peut être considéré responsable des dysfonctionnements ou pertes de données en cas d'utilisation détournée et se réserve le droit de la supprimer à tout moment.

Elle peut être testée ici : http://desmontils.net/SQLSandbox/

NB : pour le bon fonctionnement de l'application, il faut vérifier que le répertoire "tmp" est accessible en écriture.
A cas où, faire : 'chmod a+w tmp'


## Prérequis

PHP 8.0 ou plus récent (pour `SQLite3::setAuthorizer`), avec les extensions `sqlite3` et `SimpleXML`.

## Sécurité du déploiement

Les répertoires `tmp/` (copies des bases de chaque session), `bdd/` (bases et scripts SQL), `lib/` et `tests/` ne doivent pas être accessibles depuis le web. Sous Apache, les fichiers `.htaccess` fournis s'en chargent, à condition que `AllowOverride` l'autorise. Sous nginx, qui ignore les `.htaccess`, ajouter dans le bloc `server` :

```nginx
location ~ ^/SQLSandbox/(tmp|bdd|lib|tests)/ {
    deny all;
}
```

(adapter le préfixe `/SQLSandbox/` au chemin d'installation). L'idéal reste de placer `tmp/` hors de la racine web.

Le serveur intégré de PHP (`php -S`) ne doit pas servir l'application en public : une requête SQL trop longue arrête tout le serveur.

## Tests

```sh
php tests/run.php
```

Les tests sont lancés automatiquement par GitHub Actions (`.github/workflows/tests.yml`) et GitLab CI (`.gitlab-ci.yml`).
