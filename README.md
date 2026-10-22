# SQLSandbox
 
Cette petite application Web a été développée à des fins pédagogiques. L'instance en ligne est mise à disposition pour cet usage uniquement, dans la mesure où son utilisation n'est pas détournée. L'auteur ne peut être considéré responsable des dysfonctionnements ou pertes de données et se réserve le droit de la supprimer à tout moment.

Elle peut être testée ici : https://desmontils.net/SQLSandbox/

## Installation

1. Copier les fichiers de l'application sur le serveur (sans `.git/`, `.github/`, `.gitlab-ci.yml` ni `tests/`), en vérifiant que les fichiers `.htaccess` de `bdd/`, `lib/` et `tmp/` sont bien envoyés : beaucoup de clients FTP masquent les fichiers qui commencent par un point.
2. À la première requête, l'application crée à côté de la racine web (hors du site) deux dossiers de travail :
   - `sqlsandbox-tmp` : copies des bases de chaque session et verrous d'exécution ;
   - `sessions-sqlsandbox` : sessions PHP.

   Le dossier parent de la racine web doit donc être accessible en écriture par PHP, ce qui est le cas chez OVH (`~/`, à côté de `www/`). À défaut, l'application se replie sur `tmp/` et `tmp/sessions/`, qui doivent alors être accessibles en écriture (`chmod a+w tmp`).
3. Ouvrir l'application : un message « Problème d'installation » s'affiche si aucun dossier de travail n'est utilisable.

## Prérequis

PHP 8.0 ou plus récent (pour `SQLite3::setAuthorizer`), avec les extensions `sqlite3` et `SimpleXML`.

SQLite 3.32.3 ou plus récent est recommandé (version visible avec `select sqlite_version()`) :
- avant 3.31, la limite de mémoire de SQLite (`PRAGMA hard_heap_limit`) est ignorée ;
- avant 3.32.3, les fonctions de fenêtrage (`OVER`, `WINDOW`) ont des failles connues : l'application les refuse sur ces versions.

L'hébergement mutualisé OVH (image `stable64`) fournit SQLite 3.27 : ces deux protections y sont donc respectivement inactive et active.

## Sécurité du déploiement

Les répertoires `bdd/` (bases et scripts SQL), `lib/`, `tests/` et `tmp/` (dossier de travail de repli) ne doivent pas être accessibles depuis le web. Sous Apache, les fichiers `.htaccess` fournis s'en chargent, à condition que `AllowOverride` l'autorise. Sous nginx, qui ignore les `.htaccess`, ajouter dans le bloc `server` :

```nginx
location ~ ^/SQLSandbox/(tmp|bdd|lib|tests)/ {
    deny all;
}
```

(adapter le préfixe `/SQLSandbox/` au chemin d'installation).

Les copies des bases et les sessions sont rangées hors du site (`sqlsandbox-tmp` et `sessions-sqlsandbox`, voir l'installation), pour que leur protection ne dépende pas d'un `.htaccess` : le nom d'un fichier de session contient l'identifiant de session. `tmp/` n'est utilisé qu'en repli, protégé par `tmp/.htaccess` et un `index.html` vide.

## Déploiement derrière Caddy

Exemple de l'instance du Raspberry Pi : l'application est dans `/home/ed/programmation/SQLSandbox`, servie sous `/SQLSandbox/` par Caddy et PHP-FPM (paquets `php8.4-fpm`, `php8.4-sqlite3` et `php8.4-xml`, à installer avec `--no-install-recommends` pour ne pas tirer Apache).

**Pool PHP-FPM dédié.** `/home/ed` est en `700` : PHP tourne donc sous `ed` plutôt que sous `www-data`, dans son propre pool (`/etc/php/8.4/fpm/pool.d/sqlsandbox.conf`) :

```ini
[sqlsandbox]
user = ed
group = ed
listen = /run/php/sqlsandbox.sock
listen.owner = caddy
listen.group = caddy
listen.mode = 0660
pm = ondemand
pm.max_children = 8
pm.process_idle_timeout = 60s
pm.max_requests = 500
request_terminate_timeout = 60s
php_admin_value[expose_php] = Off
```

puis `sudo php-fpm8.4 -t && sudo systemctl reload php8.4-fpm`.

**Accès de Caddy aux fichiers statiques.** Caddy (utilisateur `caddy`) sert lui-même les CSS, JS et polices : il doit pouvoir traverser `/home/ed`, sans pouvoir en lister le contenu :

```sh
sudo setfacl -m u:caddy:x /home/ed
```

**Route Caddy**, dans le bloc du site :

```caddy
redir /SQLSandbox /SQLSandbox/ 308
handle /SQLSandbox/* {
	root * /home/ed/programmation
	@interdit path_regexp ^/SQLSandbox/(tmp|bdd|lib|tests)(/|$)
	respond @interdit 403
	php_fastcgi unix//run/php/sqlsandbox.sock
	file_server
}
```

Caddy ignore les `.htaccess` : la règle `@interdit` les remplace. Utiliser `handle` et non `handle_path`, qui retirerait le préfixe et changerait le `DOCUMENT_ROOT`. Celui-ci vaut ici `/home/ed/programmation` : les dossiers de travail sont donc créés dans `/home/ed` (`/home/ed/sqlsandbox-tmp` et `/home/ed/sessions-sqlsandbox`), ce que permet le pool sous `ed`. Valider avant de recharger : `caddy validate --config /etc/caddy/Caddyfile`, puis `sudo systemctl reload caddy`.

**Envoi des fichiers**, depuis la copie de travail, sans le contenu de `tmp/` (verrous ou copies de bases laissés par des essais en local) :

```sh
rsync -av --delete --exclude .git --exclude .github --exclude .gitlab-ci.yml --exclude tests --exclude .idea --exclude .claude --exclude .DS_Store --exclude 'tmp/*' ./ ed@<serveur>:/home/ed/programmation/SQLSandbox/
ssh ed@<serveur> chmod a-w /home/ed/programmation/SQLSandbox/tmp
```

`tmp/` est ainsi créé vide et laissé en lecture seule : l'application ne peut pas s'y replier, et un problème de dossier de travail se voit tout de suite (message « Problème d'installation »). Pour vérifier : `https://<site>/SQLSandbox/bdd/em/em.db` doit répondre 403, et une session ouverte doit apparaître dans `/home/ed/sessions-sqlsandbox`.

## Plusieurs installations sur un même hébergement

Le nom du cookie de session et des dossiers de travail vient de la constante `INSTANCE` de `dossiers.php` : `sqlsandbox` par défaut (cookie `sqlsandbox`, dossiers `sqlsandbox-tmp` et `sessions-sqlsandbox`). Pour faire fonctionner deux installations côte à côte, par exemple une version de test dans `/SQLSandbox6/`, donner à chacune un nom d'`INSTANCE` différent : sinon elles partagent cookie et sessions, et ouvrir l'une réinitialise les bases de l'autre.

Le serveur intégré de PHP (`php -S`) ne doit pas servir l'application en public : une requête SQL trop longue arrête tout le serveur.

## Tests

```sh
php tests/run.php
```

Les tests sont lancés automatiquement par GitHub Actions (`.github/workflows/tests.yml`) et GitLab CI (`.gitlab-ci.yml`). GitHub Actions les lance aussi avec SQLite 3.27.2, la version de l'hébergement OVH, pour vérifier les protections propres aux anciennes versions.

## Licence

Le code de SQLSandbox est un logiciel libre, distribué sous la licence GNU GPL version 3 (fichier `LICENSE`).

Éléments tiers, sous leur propre licence :
- éditeur SQL [CodeMirror 5](https://codemirror.net/5/) (`js/codemirror/`, version 5.65.21) : licence MIT (`js/codemirror/LICENSE`) ;
- police [Atkinson Hyperlegible Next](https://www.brailleinstitute.org/freefont/) du Braille Institute of America (`fonts/`) : utilisation libre, sans modification des fichiers (`fonts/LICENCE.txt`) ;
- base MONDIAL (`bdd/mondial/`) : voir les conditions de sa [page de référence](http://www.dbis.informatik.uni-goettingen.de/Mondial/).
