# SQLSandbox
 
Cette petite application Web a été développée à des fins pédagogiques. L'instance en ligne est mise à disposition pour cet usage uniquement, dans la mesure où son utilisation n'est pas détournée. L'auteur ne peut être considéré responsable des dysfonctionnements ou pertes de données et se réserve le droit de la supprimer à tout moment.

Elle peut être testée ici : http://desmontils.net/SQLSandbox/

NB : pour le bon fonctionnement de l'application, il faut vérifier que le répertoire "tmp" est accessible en écriture.
A cas où, faire : 'chmod a+w tmp'


## Prérequis

PHP 8.0 ou plus récent (pour `SQLite3::setAuthorizer`), avec les extensions `sqlite3` et `SimpleXML`.

SQLite 3.32.3 ou plus récent est recommandé (version visible avec `select sqlite_version()`) :
- avant 3.31, la limite de mémoire de SQLite (`PRAGMA hard_heap_limit`) est ignorée ;
- avant 3.32.3, les fonctions de fenêtrage (`OVER`, `WINDOW`) ont des failles connues : l'application les refuse sur ces versions.

L'hébergement mutualisé OVH (image `stable64`) fournit SQLite 3.27 : ces deux protections y sont donc respectivement inactive et active.

## Sécurité du déploiement

Les répertoires `tmp/` (copies des bases de chaque session), `bdd/` (bases et scripts SQL), `lib/` et `tests/` ne doivent pas être accessibles depuis le web. Sous Apache, les fichiers `.htaccess` fournis s'en chargent, à condition que `AllowOverride` l'autorise. Sous nginx, qui ignore les `.htaccess`, ajouter dans le bloc `server` :

```nginx
location ~ ^/SQLSandbox/(tmp|bdd|lib|tests)/ {
    deny all;
}
```

(adapter le préfixe `/SQLSandbox/` au chemin d'installation). L'idéal reste de placer `tmp/` hors de la racine web.

Les sessions PHP sont rangées dans un dossier `sessions-sqlsandbox` créé à côté de la racine web (par exemple `~/sessions-sqlsandbox` chez OVH, hors de `www/`), car le nom d'un fichier de session contient l'identifiant de session. Si ce dossier ne peut pas être créé, elles vont dans `tmp/sessions/`, protégé par `tmp/.htaccess`.

Le serveur intégré de PHP (`php -S`) ne doit pas servir l'application en public : une requête SQL trop longue arrête tout le serveur.

## Tests

```sh
php tests/run.php
```

Les tests sont lancés automatiquement par GitHub Actions (`.github/workflows/tests.yml`) et GitLab CI (`.gitlab-ci.yml`).

## Licence

Le code de SQLSandbox est un logiciel libre, distribué sous la licence GNU GPL version 3 (fichier `LICENSE`), y compris les contributions faites depuis la création du dépôt.

Éléments tiers, sous leur propre licence :
- [Prototype](http://prototypejs.org/) et [script.aculo.us](http://script.aculo.us/) (`js/`) : licence MIT ;
- design adapté du modèle « dusplic » de [Free CSS Templates](http://www.freecsstemplates.org/) (`default.css`, `images/img*.jpg`) : Creative Commons Attribution 2.5 ;
- icônes du jeu « Basic set » de [PixelMixer](http://pixel-mixer.com/) (`images/*_64.png`) : Creative Commons BY-SA ;
- base MONDIAL (`bdd/mondial/`) : voir les conditions de sa [page de référence](http://www.dbis.informatik.uni-goettingen.de/Mondial/).
