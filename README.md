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

L'application étant ouverte à tous sans compte, l'espace disque des copies est borné (constantes de `fonctions.php`) : une copie grossit au plus de 10 Mo par rapport à sa base d'origine, sans dépasser 20 Mo, et le total des copies est plafonné à 1 Go (relevé à chaque ouverture de l'application, dans le fichier `occupation` du dossier de travail). Au-delà, ou s'il reste moins de 200 Mo libres sur le disque, aucune nouvelle copie n'est faite et les copies existantes ne grossissent plus, jusqu'à ce que la purge en libère ; les étudiants en sont avertis par un message. Ces deux plafonds sont prudents pour un hébergement mutualisé ; sur un serveur qui a de la place, `sqlsandbox.ini` peut les relever (`occupation_max_mo`, `espace_libre_min_mo`, voir `sqlsandbox.ini.exemple`).

Les copies sont écrites dans un fichier temporaire puis renommées : une réinitialisation pendant une requête ne laisse jamais une base à moitié copiée. Le jeton anti-CSRF n'est accepté qu'en POST. Si `config.xml` est invalide, la page n'affiche qu'un message générique ; le détail des erreurs XML, avec les chemins des fichiers, va dans le journal de PHP (`new LibXML(true)` pour l'afficher pendant la mise au point).

## Déploiement derrière Caddy

Exemple d'installation sous Debian (remplacer `<utilisateur>` par le compte qui héberge l'application) : l'application est dans `/home/<utilisateur>/programmation/SQLSandbox`, servie sous `/SQLSandbox/` par Caddy et PHP-FPM (paquets `php8.4-fpm`, `php8.4-sqlite3` et `php8.4-xml`, à installer avec `--no-install-recommends` pour ne pas tirer Apache).

**Pool PHP-FPM dédié.** Si `/home/<utilisateur>` est en `700`, PHP tourne sous `<utilisateur>` plutôt que sous `www-data`, dans son propre pool (`/etc/php/8.4/fpm/pool.d/sqlsandbox.conf`) :

```ini
[sqlsandbox]
user = <utilisateur>
group = <utilisateur>
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

**Accès de Caddy aux fichiers statiques.** Caddy (utilisateur `caddy`) sert lui-même les CSS, JS et polices : il doit pouvoir traverser `/home/<utilisateur>`, sans pouvoir en lister le contenu :

```sh
sudo setfacl -m u:caddy:x /home/<utilisateur>
```

**Route Caddy**, dans le bloc du site :

```caddy
redir /SQLSandbox /SQLSandbox/ 308
handle /SQLSandbox/* {
	root * /home/<utilisateur>/programmation
	@interdit path_regexp ^/SQLSandbox/(\.[^/]*|tmp|bdd|lib|tests)(/|$)
	respond @interdit 403
	php_fastcgi unix//run/php/sqlsandbox.sock
	file_server
}
```

Facultatif, l'adresse `/SQLSandbox/123456` d'un tenant de QueryCraft (`/SQLSandbox/?123456` fonctionne sans cela), dans le bloc `handle /SQLSandbox/*`, avant `php_fastcgi` :

```caddy
@tenant_barre path_regexp tenant_barre ^/SQLSandbox/([0-9]{6})/$
redir @tenant_barre /SQLSandbox/{re.tenant_barre.1} 308
@tenant path_regexp tenant ^/SQLSandbox/([0-9]{6})$
rewrite @tenant /SQLSandbox/index.php?qc={re.tenant.1}
```

Caddy ignore les `.htaccess` : la règle `@interdit` les remplace, et interdit aussi les fichiers cachés, dont `.git/`. Utiliser `handle` et non `handle_path`, qui retirerait le préfixe et changerait le `DOCUMENT_ROOT`. Celui-ci vaut ici `/home/<utilisateur>/programmation` : les dossiers de travail sont donc créés dans `/home/<utilisateur>` (`/home/<utilisateur>/sqlsandbox-tmp` et `/home/<utilisateur>/sessions-sqlsandbox`), ce que permet le pool sous `<utilisateur>`. Valider avant de recharger : `caddy validate --config /etc/caddy/Caddyfile`, puis `sudo systemctl reload caddy`.

**Installation et mises à jour par git.** Le dossier est un clone du dépôt public (HTTPS, sans clé) :

```sh
cd /home/<utilisateur>/programmation && git clone https://github.com/edesmontils/SQLSandbox.git
```

Pour passer à la dernière version poussée sur GitHub :

```sh
cd /home/<utilisateur>/programmation/SQLSandbox && git pull --ff-only
```

Rien à recharger ensuite : PHP relit les fichiers modifiés. Les copies des bases et les sessions étant hors du clone, celui-ci reste propre (`git status` vide) et `git pull` ne rencontre pas de conflit ; ne pas modifier les fichiers directement sur le serveur. `tests/`, `.github/` et `.git/` sont présents dans le dossier mais interdits par la règle `@interdit`. Les dossiers de travail existant, `tmp/` ne sert pas.

Pour vérifier : `https://<site>/SQLSandbox/bdd/em/em.db` et `https://<site>/SQLSandbox/.git/config` doivent répondre 403, et une session ouverte doit apparaître dans `/home/<utilisateur>/sessions-sqlsandbox`.

## Génération de données par IA (facultatif)

Le bouton « ✨ Remplir » (page d'une base et édition d'une requête) fait générer par une IA des lignes pour une table, ou le contenu d'une colonne ajoutée avec `ALTER TABLE`. Le modèle ne produit pas de SQL : il renvoie des lignes en JSON, qui sont contrôlées sur la copie de l'étudiant (types, contraintes, clés étrangères) et présentées en aperçu ; rien n'est écrit tant que l'étudiant ne le valide pas.

La fonction passe par un ProxyIAg (proxy compatible OpenAI qui garde les vraies clés des providers et applique des quotas) et **n'existe que si le fichier de réglages est présent**. Copier `ia.ini.exemple` **hors du site**, à côté de la racine web, sous le nom `sqlsandbox-ia.ini` (`~/sqlsandbox-ia.ini` chez OVH, droits `600`), et y renseigner l'URL du proxy (HTTPS obligatoire hors `localhost`), une clé `sk-…` propre à l'application et l'alias du modèle. La variable d'environnement `SQLSANDBOX_IA_INI` permet de placer le fichier ailleurs. Prérequis : les extensions `curl` et `mbstring` de PHP (paquets `php8.4-curl` et `php8.4-mbstring` sous Debian) ; sans elles, la fonction est désactivée.

Comme la clé est unique, le quota de ProxyIAg est commun à tous les étudiants : l'application limite donc elle-même chaque session (`par_session` générations par jour, `delai` secondes entre deux, `lignes_max` lignes par génération) et l'ensemble de l'installation (`plafond_global` par jour, compté dans un fichier `ia_compteur` du dossier de travail). Comme une nouvelle session s'obtient en changeant de cookie, chaque adresse IP est aussi limitée (`par_ip`, 250 appels par jour, la moitié du plafond global, générations et corrections d'intention confondues, fichier `ia_ip`) : un seul poste ne peut plus épuiser le plafond global. La valeur doit rester assez large pour une salle de TP dont tous les postes sortent par la même adresse ; 0 la supprime.

Les mêmes réglages servent à la **correction des intentions** (questions `R->I` et `RT->I`, section « Exercices »), avec un compteur de session à part (`par_session_intention`, 30 corrections par jour par défaut) : faire corriger ses intentions n'épuise pas les générations de « ✨ Remplir », et inversement. Le plafond global, lui, est commun aux deux. Dans le prompt, l'intention de l'étudiant est placée entre balises `<intention_etudiant>` (qu'il ne peut pas refermer lui-même) et le modèle a pour consigne de ne suivre aucune demande qu'elle contient. Si l'explication renvoyée recopie malgré tout l'intention de référence (la référence entière ou six de ses mots à la suite), elle est remplacée par un message invitant à reformuler, et l'incident est noté dans le journal de PHP. Les messages d'erreur du proxy ne sont jamais montrés aux étudiants ; ils vont dans le journal d'erreurs de PHP.

Pour ces deux usages, le modèle reçoit le schéma SQL de la base et sa **description** (celle de QueryCraft en Markdown pour une base synchronisée, sinon l'élément `<description>` du `config.xml` ramené à du texte) : elle lui donne le sens des tables et des colonnes que le schéma ne porte pas, par exemple que `Fleuve.id_p` désigne le pays où le fleuve prend sa source.

Modèle retenu : `gemma-4-31b`, le plus fiable à l'essai du 2026-10-23 (40 générations relues) mais lent (~9 s) ; `mistral-small-24b` (~4,5 s) est le repli si la lenteur gêne. Les données générées peuvent rester approximatives : l'aperçu sert à les vérifier.

## Exercices (format QueryCraft)

Les travaux pratiques sont des exercices au format JSON de [QueryCraft](https://pedago.desmontils.net/querycraft/), communs aux deux applications. Un exercice porte sur une base, désignée par le nom de son fichier SQLite (`{"type": "sqlite", "name": "em.db"}` pour `bdd/em/em.db`), et chaque question est une liste `[requête, intention, commentaire, type, instructions]`, avec un 6e élément, la requête à trous, pour les types `rI->R` et `rIT->R`.

| Type | L'étudiant reçoit | Il produit |
|---|---|---|
| `I->R` | l'intention | la requête |
| `IT->R` | l'intention et la table attendue | la requête |
| `rI->R` | l'intention et une requête à trous | la requête |
| `rIT->R` | l'intention, une requête à trous et la table attendue | la requête |
| `R->I` | une requête, qu'il peut exécuter | son intention |
| `RT->I` | une requête et son résultat | son intention |

L'intention rédigée pour une question `…->I` est corrigée par l'IA quand elle est activée (section suivante), sinon l'étudiant la compare à celle de l'enseignant (auto-évaluation). Le navigateur ne reçoit jamais la solution d'une question `…->R` ni l'intention attendue d'une question `…->I` : le serveur les relit dans le catalogue.

**Origine des exercices**, réglée par le fichier `sqlsandbox.ini` (modèle : `sqlsandbox.ini.exemple`), à placer **hors du site** comme `sqlsandbox-ia.ini` (`~/sqlsandbox.ini` chez OVH, droits `600` ; variable d'environnement `SQLSANDBOX_INI` pour un autre emplacement) :

- `exos_source` vide, ou pas de fichier : exercices **locaux**, dans `bdd/exercices/` (protégé comme les bases) ;
- `exos_source` renseigné : exercices de l'instance QueryCraft indiquée, la démonstration (`https://<hôte>/`) ou le tenant d'un enseignant (`https://<hôte>/<code>/`), lus sur sa route `/catalogue/exos` avec le jeton `exos_jeton`, que l'administrateur de QueryCraft crée par `tenant-sbs --set-catalogue-token <demo|code>`. Le catalogue est gardé en cache dans le dossier de travail et revérifié toutes les `exos_ttl` secondes (300 par défaut) : un exercice créé ou modifié dans QueryCraft apparaît donc sans redéploiement. Si QueryCraft ne répond pas, le dernier cache sert, et à défaut les exercices locaux.

**Bases synchronisées.** Avec une source, SQLSandbox reproduit aussi les **bases SQLite** de l'instance QueryCraft et leurs descriptions (rédigées en Markdown dans QueryCraft, par l'enseignant ou par l'IA, et transmises déjà rendues en HTML ; le Markdown est gardé pour l'IA). Une base nouvelle ou modifiée est téléchargée (taille limitée à 20 Mo, empreinte sha256, format et `PRAGMA quick_check` vérifiés) dans un dossier de travail hors du site, `sqlsandbox-bases` à côté de la racine web (`tmp/bases` en repli) ; une base supprimée dans QueryCraft y passe à la corbeille (`.corbeille`, vidée après 7 jours). Rien n'est jamais écrit dans `bdd/`, qui reste versionné. Dès qu'une base a été synchronisée, ce sont les bases de QueryCraft qui sont proposées ; `bdd/` ne sert qu'en l'absence de source ou tant qu'aucune synchronisation n'a réussi. Les copies de session déjà faites par les étudiants gardent l'ancienne version jusqu'à leur expiration ou au bouton « ↩️ Revenir à la base d'origine ».

Le contenu venu de QueryCraft n'est pas tenu pour sûr, les tenants étant rédigés par d'autres enseignants : les descriptions HTML sont assainies par SQLSandbox (balises de mise en forme seulement, liens et images en http(s) ou relatifs ; scripts, styles, cadres et formulaires retirés), et les requêtes des questions dont la table résultat est affichée passent par les mêmes protections que celles des étudiants (autorizer, mémoire, durée de 5 s, places d'exécution par adresse). Ces tables sont gardées en cache dans le dossier de travail (`table_*`, purgés comme les copies).

Un exercice dont la base n'existe pas dans SQLSandbox n'est pas proposé.

**Ouvrir une requête dans QueryCraft.** Le catalogue indique aussi la base que l'instance QueryCraft interroge (`base_active`, réglée par l'enseignant dans QueryCraft). Sur cette base seulement, la page d'édition propose « 🚀 Ouvrir dans QueryCraft » : QueryCraft s'ouvre dans un nouvel onglet avec la requête de l'étudiant, passée dans le fragment de l'URL (`<source>/#requete=…`), que le navigateur n'envoie pas au serveur. QueryCraft l'exécute sur sa base d'origine, sans les modifications faites dans la copie de session.

**Tenants de QueryCraft.** Une même installation suit aussi les tenants (instances d'enseignants) de l'instance `exos_source`, qui doit alors en être la racine (`https://<hôte>/`) : l'adresse `/SQLSandbox/?123456` présente les exercices et les bases du tenant 123456, et son bouton « 🚀 Ouvrir dans QueryCraft » mène à `https://<hôte>/123456/`. Il faut pour cela :

- dans `exos_jeton`, le jeton de la démo, qui sert de **jeton général** (rien à changer si SQLSandbox suit déjà la démo) ;
- que l'enseignant ait coché **« Partage avec SQLSandbox »** dans la configuration de son instance : sans cela, QueryCraft refuse le jeton général pour ce tenant, et SQLSandbox revient à l'instance par défaut avec un message (de même pour un code inconnu).

Le tenant est gardé dans la session : `/SQLSandbox/` sans code revient à l'instance par défaut. Changer de tenant oublie les copies de bases de la session (deux tenants peuvent avoir une base du même nom mais pas du même contenu). Chaque tenant a son cache de catalogue (`exos-catalogue-123456.json`) et ses bases synchronisées (`sqlsandbox-bases/.tenant-123456/`).

Chaque code encore inconnu coûte un appel à QueryCraft : un refus (tenant inconnu, partage non autorisé, QueryCraft injoignable) est donc retenu 2 minutes, et SQLSandbox vérifie au plus 20 tenants sans cache par minute pour toute l'installation (registre `exos-tenants.json` du dossier de travail). Un enseignant qui vient d'autoriser le partage attend donc au plus 2 minutes.

L'adresse `/SQLSandbox/123456` fonctionne aussi là où le serveur la réécrit en `index.php?qc=123456` : le `.htaccess` fourni à la racine s'en charge sous Apache (à envoyer avec les autres), et pour Caddy voir ci-dessous. Sans barre finale : `/SQLSandbox/123456/` y est renvoyée, pour que les adresses relatives de l'application restent valables.

**Bases et exercices locaux** : le package QueryCraft en est la référence. `outils/importe-querycraft.sh <dépôt QueryCraft>` (avec `--dry-run` pour voir ce qui changerait) recopie ses bases dans les dossiers de `bdd/` qui les déclarent, ses exercices dans `bdd/exercices/` et le prompt de correction des intentions dans `templates/`. Une nouvelle base de QueryCraft demande d'écrire son `config.xml` (description).

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
