// JavaScript standard (ES2015), sans bibliothèque

//=================================================================
// Outils DOM et AJAX (remplacent Prototype et script.aculo.us)
//=================================================================

function $(id) {
    return document.getElementById(id);
}

// Échappe &, < et > (comme String#escapeHTML de Prototype)
function echappe(texte) {
    return String(texte).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Applique, juste après leur insertion, les <script> d'un fragment HTML venu du serveur.
// Les réponses de l'application n'en contiennent qu'une sorte, qui positionne result_type
// (get(), arret_requete() dans fonctions.php) : seule cette affectation est reconnue, et
// rien n'est évalué. Un script glissé dans un contenu (description d'une base...) est
// donc ignoré, comme innerHTML le fait déjà.
function execute_scripts(html) {
    var re = /<script[^>]*>\s*result_type\s*=\s*'(ok|ko)'\s*;?\s*<\/script>/gi, m, types = [];
    while ((m = re.exec(html)) !== null) types.push(m[1]);
    if (types.length > 0) setTimeout(function () {
        types.forEach(function (t) { result_type = t; });
    }, 0);
}

function sans_scripts(html) {
    return html.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
}

// Remplace le contenu de l'élément
function maj(element, html) {
    element.innerHTML = sans_scripts(html);
    execute_scripts(html);
}

// Ajoute à la fin de l'élément
function ajoute(element, html) {
    element.insertAdjacentHTML('beforeend', sans_scripts(html));
    execute_scripts(html);
}

function cache(element) {
    element.style.display = 'none';
}

function montre(element) {
    element.style.display = '';
}

// Affiche l'élément en fondu (1 s)
function apparait(element) {
    montre(element);
    if (element.animate) element.animate([{opacity: 0}, {opacity: 1}], {duration: 1000});
}

// Fond jaune qui s'estompe (1 s)
function surligne(element) {
    if (element.animate) element.animate([{backgroundColor: '#ffff99'}, {backgroundColor: 'transparent'}], {duration: 1000});
}

function est_vide(element) {
    return element.innerHTML.trim() == '';
}

// Choisit l'option de valeur `valeur` dans une liste, si elle existe
function choisit(select, valeur) {
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value == valeur) {
            select.selectedIndex = i;
            return;
        }
    }
}

// Transforme la zone de saisie en éditeur SQL (CodeMirror : coloration, numéros de
// ligne). Ctrl+Entrée (Cmd+Entrée sur Mac) appelle executer(). La zone de saisie est
// tenue à jour à chaque frappe : le reste du code lit toujours $('requete').value.
function editeur_sql(zone, executer) {
    if (typeof CodeMirror == 'undefined') return null; // bibliothèque absente : zone simple
    var touches = {'Ctrl-Enter': executer, 'Cmd-Enter': executer};
    var editeur = CodeMirror.fromTextArea(zone, {
        mode: 'text/x-sqlite',
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 4,
        viewportMargin: Infinity,
        extraKeys: touches
    });
    editeur.on('change', function () { editeur.save(); });
    return editeur;
}

// Met en évidence l'onglet (ou la pastille de base) `element`, ou aucun si null
function marque_actif(element) {
    document.querySelectorAll('.onglet.actif, .pastille.actif').forEach(function (e) {
        e.classList.remove('actif');
    });
    if (element) element.classList.add('actif');
}

function pastille_base(nom) {
    var trouve = null;
    document.querySelectorAll('.pastille').forEach(function (e) {
        if (e.dataset.base == nom) trouve = e;
    });
    return trouve;
}

// Requête proposée pour une base : SELECT * sur sa première table (plutôt qu'une
// requête d'exemple qui échouerait faute de table)
function suggestion(base) {
    var tables = (listeBases[base] && listeBases[base]['tables']) || [];
    if (tables.length == 0) return '';
    var t = tables[0];
    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(t)) t = '"' + t.replace(/"/g, '""') + '"';
    return 'SELECT *\nFROM ' + t + ';';
}

// Appel AJAX à controler.php. surSucces(reponse) est appelé pour un code 2xx ou 304,
// ou sans code du tout (serveur injoignable : réponse vide) ; surEchec(reponse) sinon.
// reponse : {status, responseText, getHeader(nom)}
// Texte encodé en base64 (octets UTF-8). Le SQL est envoyé ainsi : un pare-feu devant
// l'application (relais du LS2N) refuse sinon « create table t(a) », pris pour une attaque.
function base64_utf8(texte) {
    var octets = new TextEncoder().encode(texte), binaire = '';
    for (var i = 0; i < octets.length; i += 0x8000)
        binaire += String.fromCharCode.apply(null, octets.subarray(i, i + 0x8000));
    return btoa(binaire);
}

function appel(methode, parametres, surSucces, surEchec) {
    var corps = new URLSearchParams(parametres).toString();
    var url = 'controler.php';
    var options = {method: methode.toUpperCase(), credentials: 'same-origin',
                   headers: {'X-Requested-With': 'XMLHttpRequest'}};
    if (options.method == 'GET') {
        url = url + '?' + corps;
    } else {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        options.body = corps;
    }
    fetch(url, options).then(function (r) {
        return r.text().then(function (texte) {
            return {status: r.status, responseText: texte,
                    getHeader: function (nom) { return r.headers.get(nom); }};
        });
    }, function () {
        return {status: 0, responseText: '', getHeader: function () { return null; }};
    }).then(function (reponse) {
        var s = reponse.status;
        // Chaque réponse de l'application prolonge la session de la durée complète
        if (s && s != 401 && reponse.getHeader('X-SQLSandbox')) fin_session = Date.now() + duree_session * 1000;
        if (!s || (s >= 200 && s < 300) || s == 304) {
            if (surSucces) surSucces(reponse);
        } else if (surEchec) {
            surEchec(reponse);
        }
    });
}

//=================================================================
// Requêtes mémorisées
//=================================================================

class Requete {
    constructor(req, base, res) {
        this.request = req;
        this.base = base;
        this.error = res;
        this.result = null;
    }
}

class RequestSet {
    constructor() {
        this.init();
    }
    init() {
        this.set = new Array();
        this.nb = 0;
    }
    add(request) {
        this.set.push(request);
        this.nb = this.nb + 1;
        maj_compteur(this.nb);
    }
    remove(i) {
        this.set.splice(i, 1);
        this.nb = this.set.length;
        maj_compteur(this.nb);
    }
    show() { // corps de classe toujours en mode strict : variables déclarées
        var t, r;
        t = '<div class="post"><h2 class="title">Requêtes mémorisées</h2><div class="story">';
        if (this.nb == 0) {
            return t + '<p>Aucune requête mémorisée pour l\'instant : après avoir exécuté une requête, '
                + 'cliquez sur « 🧺 Mémoriser » pour la retrouver ici.</p></div></div>';
        }
        t = t + '<table class="historique">' + '<thead><tr><th>n°</th><th>Base</th><th>Réussite</th><th>Requête</th><th>Actions</th><th>Résultat</th></tr></thead><tbody>';

        for (var i = 0; i < this.nb; i++) {
            r = this.set[i];
            // Numérotation affichée à partir de 1 ; l'indice i (à partir de 0) reste celui du tableau
            t = t + '<tr><td> ' + (i + 1) + ' </td><td>' + echappe(r.base) + '</td><td>';
            if (r.error == 'ok') t = t + "<span class='statut ok' title='Réussite'>✅</span>"; else t = t + "<span class='statut ko' title='Échec'>❌</span>";
            t = t + "</td><td><pre class='cm-s-default'>" + colore_sql(r.request) + "</pre></td><td>";
            t = t + "<button type='button' class='btn petit' title='Afficher ou masquer le résultat obtenu' onclick='histo(" + i + ")'>👁️ Résultat</button>";
            t = t + "<button type='button' class='btn secondaire petit' title='Reprendre la requête dans l\'éditeur' onclick='histo_mod(" + i + ")'>✏️ Modifier</button>";
            t = t + "<button type='button' class='btn danger petit' title='Supprimer la requête' onclick='histo_del(" + i + ")'>🗑️ Supprimer</button>";

            t = t + "</td><td><div id='results-" + i + "'></div></td></tr>";
        }

        t = t + '</tbody></table></div></div>';
        return t;
    }
}



//=================================================================
// Contrôle de la sortie de page pour éviter de perdre les requêtes
//=================================================================

window.onbeforeunload = function (evt) {
    if (typeof rs == 'undefined' || rs.nb == 0) return;
    var message = 'Vous allez perdre les requêtes mémorisées !';
    if (typeof evt == 'undefined') {
        evt = window.event;
    }
    if (evt) {
        evt.returnValue = message;
    }
    return message;
}

//=======================================================
// Fonctions de gestion de l'interface et des appels AJAX
//=======================================================

// Nombre de requêtes mémorisées, affiché dans l'onglet « Mémoire »
function maj_compteur(nb) {
    var c = $('nb_memo');
    if (c) c.textContent = '(' + nb + ')';
}

function clear() {

	// Variables de gestion de la mémoire initialisées.
	rs = new RequestSet();
	result_type = null;
	current_request = null; // requête proposée selon la base (suggestion())
	current_base = "";
	current_result = null;

	// mémorisation des messages et aides pour éviter de charger le serveur.
	messages_aides = null;
	messages = null;
	messages_credits = null;
	messages_apropos = null;

	// Page effacée
    maj($('posts'), "");
}

// Temps restant de la session, en pied de page. La session expire duree_session secondes après
// la dernière requête au serveur (fin_session est reportée à chaque réponse, voir appel()).
var fin_session = Date.now() + (typeof duree_session == 'undefined' ? 0 : duree_session) * 1000;

function texte_duree(secondes) {
    var min = Math.ceil(secondes / 60);
    return min >= 60 ? Math.floor(min / 60) + ' h ' + ('0' + (min % 60)).slice(-2) + ' min' : min + ' min';
}

function maj_session_restante() {
    var e = $('session-restante');
    if (!e) return;
    var reste = Math.round((fin_session - Date.now()) / 1000);
    e.textContent = reste > 0 ? '⏱️ Session : encore ' + texte_duree(reste) + ' sans action'
                              : '⏱️ Session sans doute expirée : rechargez l\'application';
    e.classList.toggle('bientot', reste > 0 && reste < 600);
}

function init() {
    clear();
    // index.php?base=…&tp=… (ancienne adresse tp.php) : ouvre directement ce TP
    var p = new URLSearchParams(window.location.search);
    if (p.get('tp') && listeBases[p.get('base')]) page_tp(p.get('base'), p.get('tp'));
    else accueil();
    maj_session_restante();
    setInterval(maj_session_restante, 30000);
}

function attention() {
    alert('Attention, nous avez perdu l\'historique !!!');
}

function remember() {
    req = new Requete($('requete').value, $('base').value, result_type);
    req.result = current_result;
    rs.add(req);
    cache($('memoriser'));
    ajoute($('results'), "<p class='statut ok'>🧺 Requête mémorisée</p>");
}

function query() {
    current_request = $('requete').value;
    current_base = $('base').value;
    appel('post', {
            Soumettre: 'Envoyer', csrf: csrf_token, requete64: base64_utf8($('requete').value), base: $('base').value, mode: 1
        },
        function (trs) {
            if (trs.responseText == '') { // processus PHP arrêté (requête trop longue)
                query_failed();
                return;
            }
            current_result = trs.responseText;
            cache($('results'));
            maj($('results'), current_result);
            dessine_avant_apres($('results'));
            apparait($('results'));
        },
        function (trs) {
            query_failed(trs);
        });
}

// Échec d'une requête : réponse de l'application (session expirée...), blocage par un
// pare-feu de l'hébergeur (réponse sans l'en-tête X-SQLSandbox) ou processus PHP
// arrêté par la limite de durée (réponse vide)
function query_failed(trs) {
    var message;
    var de_l_application = trs && trs.getHeader('X-SQLSandbox');
    // Jeton périmé (403) ou session inconnue (401) : le message vient de l'application
    if (trs && (trs.status == 403 || trs.status == 401) && de_l_application) {
        result_type = 'ko';
        maj($('results'), trs.responseText);
        return;
    }
    result_type = 'ko';
    // Pare-feu devant l'application : 403 ou 406 (OVH), 501 (relais du LS2N). Le SQL est
    // envoyé en base64 pour éviter ces refus ; ce message reste pour les autres cas.
    if (trs && !de_l_application && (trs.status == 403 || trs.status == 406 || trs.status == 501)) {
        message = "La requête a été bloquée avant d'atteindre l'application (code " + trs.status
            + ") : le pare-feu de l'hébergeur l'a sans doute prise pour une attaque. "
            + "Reformulez-la, ou signalez-la à votre enseignant.";
    } else if (trs && !de_l_application && trs.status >= 400) {
        message = "Le serveur n'a pas pu transmettre la requête à l'application (code " + trs.status
            + "). Réessayez dans un instant.";
    } else {
        message = "Pas de réponse du serveur : la requête a probablement dépassé la durée maximale "
            + "d'exécution et a été interrompue. Simplifiez-la (conditions de jointure, LIMIT...).";
    }
    current_result = "<p class='statut ko'>❌ Échec</p><p class='message'>" + message + "</p>";
    maj($('results'), current_result);
}

function histo(i) { // Risque de problème de mémoire s'il y a beaucoup de requête et/ou très grosses. Peut-être refaire un accès à la base...
    code = 'results-' + i;
    if (est_vide($(code))) {
        r = rs.set[i];
        cache($(code));
        maj($(code), r.result);
        dessine_avant_apres($(code));
        cache($('memoriser'));
        apparait($(code));
    } else {
        cache($(code));
        maj($(code), "");
    }
}

function histo_mod(i) {
    r = rs.set[i];
    current_request = r.request;
    current_base = r.base;
    new_query();
}

function histo_del(i) {
    if (confirm("Êtes-vous certain(e) de vouloir supprimer la requête " + (i + 1) + " ?")) {
        rs.remove(i);
        get_histo();
    }
}

function get_histo() {
    marque_actif($('onglet-memoire'));
    cache($('posts'));
    maj($('posts'), rs.show());
    apparait($('posts'));
}

var editeur_courant = null;  // éditeur CodeMirror de la page d'édition
var derniere_suggestion = null; // requête proposée automatiquement, pas encore modifiée

function new_query() {
    t = '<div class="post">';
    t = t + '<h2 class="title">Édition d\'une requête</h2>';
    t = t + '<div class="story">';
    t = t + '	<form method="POST" id="SaisieRequete" onSubmit="query(); return false;">';
    if (!current_base) current_base = noms_bases[0];
    t = t + '	<p>Base : <strong>' + echappe(current_base) + '</strong><input type="hidden" name="base" id="base" value="' + echappe(current_base).replace(/"/g, '&quot;') + '"/></p>';
    t = t + '	<p class="libelle"><label for="requete">Requête SQL <span class="astuce">(Ctrl+Entrée pour l\'exécuter)</span></label></p>';
    t = t + '	<textarea name="requete" rows="10" cols="80" id="requete"></textarea>';
    t = t + '	<input type="hidden" name="Soumettre" value="Envoyer"/>';
    t = t + '	<div class="actions"><button type="submit" class="btn" id="send_new">▶️ Exécuter la requête</button>';
    t = t + '	<button type="button" class="btn secondaire" onclick="modele($(\'base\').value)" title="Diagramme, schéma relationnel et script SQL de la base">🧩 Modèle</button>';
    t = t + '	<button type="button" class="btn secondaire" onclick="db_tables($(\'base\').value)" title="Contenu des tables de la base (nouvel onglet)">📋 Voir les tables</button>';
    t = t + ia_bouton("$('base').value");
    if (querycraft_possible(current_base))
        t = t + '	<button type="button" class="btn secondaire" onclick="vers_querycraft()" title="Ouvre QueryCraft dans un nouvel onglet avec cette requête (sur la base d\'origine, sans vos modifications)">🚀 Ouvrir dans QueryCraft</button>';
    t = t + '	</div>';
    t = t + '	</form>';
    t = t + '	<div id="results"></div>';
    t = t + '</div>';
    t = t + '</div>';

    cache($('posts'));
    maj($('posts'), t);

    marque_actif(pastille_base($('base').value));
    if (current_request == null) {
        derniere_suggestion = suggestion($('base').value);
        $('requete').value = derniere_suggestion;
    } else {
        derniere_suggestion = null;
        $('requete').value = current_request;
    }

    apparait($('posts'));
    editeur_courant = editeur_sql($('requete'), query);
}

// SQLSandbox est relié à une instance QueryCraft (sqlsandbox.ini) qui interroge la base `base`
function querycraft_possible(base) {
    return typeof querycraft != 'undefined' && querycraft !== null && base === querycraft.base;
}

// Ouvre QueryCraft avec la requête en cours d'édition. Elle passe dans le fragment de l'URL
// (#requete=…), que le navigateur n'envoie pas au serveur ; QueryCraft le lit puis l'efface.
function vers_querycraft() {
    if (!querycraft_possible($('base').value)) return;
    if (editeur_courant) editeur_courant.save();
    var requete = $('requete').value;
    if (requete.trim() === '') { alert('La requête est vide.'); return; }
    if (!confirm('QueryCraft va s\'ouvrir dans un nouvel onglet avec cette requête.\n\n'
            + 'Attention : si vous avez modifié la base dans SQLSandbox (INSERT, UPDATE, DELETE, '
            + 'CREATE…), QueryCraft ne verra pas ces modifications : il utilise la base d\'origine.'))
        return;
    window.open(querycraft.url + '/#requete=' + encodeURIComponent(requete), '_blank', 'noopener');
}

// Valeur JavaScript sûre à placer dans un attribut HTML (onClick="f(...)")
function attr_js(v) {
    return echappe(JSON.stringify(v)).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Description d'une base (contenu du fichier de configuration, de confiance)
function description_base(nom) {
    var ref = listeBases[nom]['référence'];
    t = '<a id="matiere"></a><div class="post">';
    t = t + '<h2 class="title">Base ' + echappe(nom) + '</h2>';
    t = t + '<div class="story">';
    t = t + '<p>' + listeBases[nom]['description'] + '</p>';
    if (ref) t = t + '<p>Référence : <a href="' + echappe(ref).replace(/"/g, '&quot;') + '">' + echappe(ref) + '</a></p>';
    t = t + '<div class="actions">';
    t = t + '<button type="button" class="btn" onclick="current_base = ' + attr_js(nom) + '; current_request = null; new_query()">✏️ Interroger cette base</button>';
    t = t + '<button type="button" class="btn secondaire" title="Diagramme, schéma relationnel et script SQL" onclick="modele(' + attr_js(nom) + ')">🧩 Modèle de la base</button>';
    t = t + '<button type="button" class="btn secondaire" title="Contenu des tables (nouvel onglet)" onclick="db_tables(' + attr_js(nom) + ')">📋 Voir les tables</button>';
    t = t + ia_bouton(attr_js(nom));
    t = t + '</div></div></div>';
    return t;
}

// Session inconnue du serveur (code 401 de l'application) : affiche son message une
// seule fois, à la suite de la page. Retourne false pour une autre erreur.
function session_perdue(trs) {
    if (!trs || trs.status != 401 || !trs.getHeader('X-SQLSandbox')) return false;
    if (!$('session-perdue')) ajoute($('posts'), trs.responseText);
    return true;
}

// Ajoute à la page le résultat d'une rubrique du contrôleur, puis appelle suite()
// (pas de suite si la rubrique n'a pas pu être obtenue)
function insere_rubrique(parametres, suite) {
    appel('get', parametres,
        function (trs) {
            ajoute($('posts'), trs.responseText);
            if (suite) suite();
        },
        function (trs) {
            if (!session_perdue(trs)) alert('Impossible d\'obtenir la rubrique !')
        });
}

// Affiche la description d'une base et ses TP
function db(nom) {
    marque_actif(pastille_base(nom));
    cache($('posts'));
    maj($('posts'), description_base(nom));
    montre($('posts'));
    insere_rubrique({Soumettre: 'tp', nom_base: nom}, maj_suivi_tp);
}

// Contenu des tables d'une base, dans un nouvel onglet
function db_tables(nom) {
    window.open('dbListe.php?base=' + encodeURIComponent(nom));
}

//=================================================================
// Travaux pratiques, au format des exercices de QueryCraft : tout se passe dans la page
// (liste des questions, question, précédente / suivante), sans nouvel onglet.
// q.attendu : 'R' (écrire la requête) ou 'I' (rédiger l'intention de q.requete) ;
// q.modele : requête à trous (types rI->R, rIT->R) ; q.table : table résultat affichée
// d'emblée (types en T). Le serveur n'envoie jamais la solution d'une question …->R,
// ni l'intention attendue d'une question …->I.
//=================================================================

var reponses = {};          // réponses mémorisées, par TP et question (cle_reponse())
var tp_courant = null;      // {base, tp, questions} du TP ouvert
var tps_charges = {};       // TP déjà chargés, par cle_reponse(base, tp)
var question_courante = null;

function cle_reponse(base, tp, code) {
    return JSON.stringify([base, tp, code]);
}

function reponse_de(q) {
    return reponses[cle_reponse(q.base, q.exercice, q.code)];
}

// Une réponse compte si elle diffère de la requête à trous donnée au départ
function est_repondue(q) {
    var r = reponse_de(q);
    return r != null && r.trim() !== '' && r.trim() !== (q.modele || '').trim();
}

function nb_repondues(questions) {
    var n = 0;
    questions.forEach(function (q) { if (est_repondue(q)) n++; });
    return n;
}

// Avancement des TP déjà ouverts, sur leurs cartes (liste des TP d'une base)
function maj_suivi_tp() {
    document.querySelectorAll('.carte-tp-suivi').forEach(function (e) {
        var c = JSON.parse(e.dataset.tp);
        var tp = tps_charges[cle_reponse(c[0], c[1])];
        if (!tp || !tp.questions.length) return;
        var n = nb_repondues(tp.questions);
        e.textContent = n == tp.questions.length ? '✅ Toutes les questions ont une réponse'
                                                  : n + ' / ' + tp.questions.length + ' avec une réponse';
        e.classList.toggle('fini', n == tp.questions.length);
    });
}

// Fil d'Ariane : Base › TP › Question
function fil_ariane(question) {
    var t = '<nav class="fil-ariane" aria-label="Fil d\'Ariane">';
    t = t + '<button type="button" onclick="db(' + attr_js(tp_courant.base) + ')">🗄️ ' + echappe(tp_courant.base) + '</button>';
    t = t + '<span aria-hidden="true">›</span>';
    if (question) {
        t = t + '<button type="button" onclick="retour_questions()">📝 ' + echappe(tp_courant.tp) + '</button>';
        t = t + '<span aria-hidden="true">›</span><span aria-current="page">Question ' + question.numero + '</span>';
    } else {
        t = t + '<span aria-current="page">📝 ' + echappe(tp_courant.tp) + '</span>';
    }
    return t + '</nav>';
}

// Description de la base, repliée, pour l'avoir sous la main pendant le TP
function description_repliee(nom) {
    var b = listeBases[nom] || {};
    var t = '<details class="description-base"><summary>📖 Description de la base ' + echappe(nom) + '</summary>';
    t = t + '<div class="story"><p>' + (b['description'] || '') + '</p>';
    if (b['tables'] && b['tables'].length) t = t + '<p><strong>Tables :</strong> ' + b['tables'].map(echappe).join(', ') + '</p>';
    return t + '</div></details>';
}

// Ouvre un TP : ses questions sont chargées une fois, puis la navigation reste dans la page
function page_tp(nom, tp) {
    marque_actif(pastille_base(nom));
    var deja = tps_charges[cle_reponse(nom, tp)];
    if (deja) {
        tp_courant = deja;
        affiche_questions();
        return;
    }
    cache($('posts'));
    maj($('posts'), '<div class="post"><p>⏳ Chargement du TP…</p></div>');
    montre($('posts'));
    appel('get', {Soumettre: 'questions', nom_base: nom, tp_name: tp},
        function (trs) {
            var r = null;
            try { r = JSON.parse(trs.responseText); } catch (e) { r = null; }
            if (!r || !r.questions) {
                maj($('posts'), '<div class="post"><p class="message">Impossible de charger ce TP.</p></div>');
                return;
            }
            tp_courant = tps_charges[cle_reponse(nom, tp)] = r;
            affiche_questions();
        },
        function (trs) {
            if (session_perdue(trs)) return;
            maj($('posts'), '<div class="post"><p class="message">Ce TP est introuvable.</p></div>');
        });
}

// Résumé d'une question pour la liste
function resume_question(q) {
    if (q.attendu == 'I') return 'Que recherche cette requête ?';
    return q.intention;
}

// Liste des questions du TP courant
function affiche_questions() {
    question_courante = null;
    var qs = tp_courant.questions;
    var faites = nb_repondues(qs);
    var t = fil_ariane(null);
    t = t + '<div class="post">';
    t = t + '<h2 class="title">TP ' + echappe(tp_courant.tp) + '</h2>';
    t = t + '<div class="progression"><div class="progression-barre"><span style="width:'
          + (qs.length ? Math.round(100 * faites / qs.length) : 0) + '%"></span></div>';
    t = t + '<span>' + faites + ' / ' + qs.length + ' question' + (qs.length > 1 ? 's' : '') + ' avec une réponse</span></div>';
    t = t + description_repliee(tp_courant.base);
    t = t + '<details class="deroulement"><summary>❔ Comment ça marche ?</summary><ul>'
          + '<li>Cliquez sur une question pour y répondre ; « Précédente » et « Suivante » passent d\'une question à l\'autre.</li>'
          + '<li>« ▶️ Exécuter » (ou Ctrl+Entrée) lance votre requête sur votre copie de la base, ou la requête donnée quand il faut en trouver l\'intention.</li>'
          + '<li>Pour une question d\'intention, « ✅ Faire corriger mon intention » la fait évaluer (par l\'IA quand le serveur l\'a activée).</li>'
          + '<li>Votre réponse est gardée quand vous changez de question ; elle est perdue si vous rechargez la page.</li>'
          + '</ul></details>';
    if (qs.length == 0) {
        t = t + '<p class="indicateur">Aucune question n\'est disponible actuellement.</p>';
    } else {
        t = t + '<ol class="liste-questions">';
        qs.forEach(function (q, i) {
            var faite = est_repondue(q);
            t = t + '<li><button type="button" class="ligne-question' + (faite ? ' faite' : '') + '" onclick="ouvre_question(' + i + ')">';
            t = t + '<span class="numero">' + q.numero + '</span>';
            t = t + '<span class="corps"><span class="type">' + echappe(q.libelle || '') + '</span>';
            t = t + '<span class="resume">' + echappe(resume_question(q)) + '</span></span>';
            t = t + '<span class="etat">' + (faite ? '✅ Répondue' : 'À faire') + '</span>';
            t = t + '</button></li>';
        });
        t = t + '</ol>';
        var suivante = qs.findIndex(function (q) { return !est_repondue(q); });
        t = t + '<div class="actions"><button type="button" class="btn" onclick="ouvre_question(' + (suivante < 0 ? 0 : suivante) + ')">'
              + (faites == 0 ? '🚀 Commencer' : (suivante < 0 ? '🔁 Revoir la question 1' : '▶️ Reprendre à la question ' + qs[suivante].numero)) + '</button></div>';
    }
    t = t + '</div>';
    cache($('posts'));
    maj($('posts'), t);
    apparait($('posts'));
    window.scrollTo(0, 0);
}

function bascule_aide() {
    var aide = $('div1');
    aide.style.display = (aide.style.display == 'block') ? 'none' : 'block';
}

// Ouvre la question d'indice i du TP courant
function ouvre_question(i) {
    var qs = tp_courant.questions;
    if (i < 0 || i >= qs.length) return;
    var q = qs[i];
    question_courante = q;
    var t = fil_ariane(q);
    t = t + '<div class="post question-tp">';
    // Pastilles des questions : position, réponses données, accès direct
    t = t + '<div class="navigation-questions" role="group" aria-label="Questions du TP">';
    qs.forEach(function (autre, j) {
        t = t + '<button type="button" class="pastille-question' + (j == i ? ' courante' : '') + (est_repondue(autre) ? ' faite' : '')
              + '" title="Question ' + autre.numero + (est_repondue(autre) ? ' (répondue)' : '') + '"'
              + (j == i ? ' aria-current="step"' : '') + ' onclick="ouvre_question(' + j + ')">' + autre.numero + '</button>';
    });
    t = t + '</div>';
    t = t + '<h2 class="title">Question ' + q.numero + ' <span class="sur">/ ' + qs.length + '</span>'
          + ' <span class="type">' + echappe(q.libelle || '') + '</span></h2>';
    t = t + '<div class="story">';
    if (q.instructions) t = t + '<p class="message">' + echappe(q.instructions) + '</p>';
    if (q.attendu == 'I') {
        t = t + '<p class="libelle">Requête :</p><pre class="cm-s-default enonce-requete">' + colore_sql(q.requete) + '</pre>';
        if (q.table) t = t + '<p class="libelle">Son résultat :</p><div class="table-attendue">' + q.table + '</div>';
        t = t + html_volet_base(q.base);
        t = t + '<p class="libelle"><label for="requete">Que recherche la requête ci-dessus ? Rédigez son intention :</label></p>';
        t = t + '<textarea rows="3" cols="80" id="requete" maxlength="2000"></textarea>';
    } else {
        t = t + '<p class="enonce">' + echappe(q.intention) + '</p>';
        if (q.table) t = t + '<p class="libelle">Table attendue :</p><div class="table-attendue">' + q.table + '</div>';
        t = t + html_volet_base(q.base);
        t = t + '<p class="libelle"><label for="requete">' + (q.modele ? 'Complétez la requête suivante' : 'Écrivez la requête');
        t = t + ' <span class="astuce">(Ctrl+Entrée pour l\'exécuter)</span></label></p>';
        t = t + '<textarea rows="10" cols="80" id="requete"></textarea>';
    }
    // Le commentaire de l'enseignant (indice) est du texte : il est échappé
    if (q.aide) {
        t = t + '<p><button type="button" class="btn secondaire petit" onclick="bascule_aide()">💡 Indice</button></p>';
        t = t + '<div id="div1"><p>' + echappe(q.aide) + '</p></div>';
    }
    t = t + '<div class="actions">';
    if (q.attendu == 'I') {
        t = t + '<button type="button" class="btn" onclick="execute_requete_donnee()" title="Exécuter la requête donnée pour la comprendre">▶️ Exécuter la requête</button>';
        t = t + '<button type="button" class="btn" id="corriger_intention" onclick="corrige_intention()">✅ Faire corriger mon intention</button>';
    } else {
        t = t + '<button type="button" class="btn" onclick="reponse()">▶️ Exécuter</button>';
    }
    t = t + '</div><div id="correction"></div><div id="results"></div>';
    t = t + '</div>';
    // Navigation entre questions
    t = t + '<div class="pied-question">';
    t = t + '<button type="button" class="btn secondaire" onclick="ouvre_question(' + (i - 1) + ')"' + (i == 0 ? ' disabled' : '') + '>◀️ Précédente</button>';
    t = t + '<button type="button" class="btn secondaire" onclick="retour_questions()">☰ Toutes les questions</button>';
    if (i < qs.length - 1) t = t + '<button type="button" class="btn" onclick="ouvre_question(' + (i + 1) + ')">Suivante ▶️</button>';
    else t = t + '<button type="button" class="btn" onclick="retour_questions()">🏁 Terminer</button>';
    t = t + '</div>';
    t = t + '</div>';

    cache($('posts'));
    maj($('posts'), t);
    var initiale = reponse_de(q);
    if (initiale == null) initiale = q.modele || '';
    $('requete').value = initiale;
    apparait($('posts'));
    window.scrollTo(0, 0);
    if (volet_base.ouvert) charge_volet_base();
    // L'intention se rédige en français : zone de saisie simple
    // La réponse est gardée à chaque frappe : elle survit à tout changement de vue
    var editeur = (q.attendu != 'I') ? editeur_sql($('requete'), reponse) : null;
    if (editeur) editeur.on('change', function () { memorise_reponse(false); });
    else $('requete').addEventListener('input', function () { memorise_reponse(false); });
}

// Exécute la réponse de l'élève sur sa copie de la base
function reponse() {
    execute_sur_copie($('requete').value);
}

// Exécute la requête donnée d'une question d'intention (sans correction)
function execute_requete_donnee() {
    execute_sur_copie(question_courante.requete);
}

// Texte de l'IA (Markdown simple) en HTML sûr : échappé, gras et retours à la ligne
function texte_ia(texte) {
    return echappe(texte).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br/>');
}

// Fait corriger l'intention rédigée : par l'IA si le serveur l'a activée, sinon
// auto-évaluation (l'intention de l'enseignant est affichée)
function corrige_intention() {
    var texte = $('requete').value.trim();
    if (texte == '') {
        maj($('correction'), '<p class="statut ko">Rédigez d\'abord l\'intention de la requête.</p>');
        return;
    }
    memorise_reponse(false);
    $('corriger_intention').disabled = true;
    maj($('correction'), '<p>⏳ Correction en cours…</p>');
    appel('post', {
            Soumettre: 'corriger_intention', csrf: csrf_token, base: question_courante.base,
            exercice: question_courante.exercice, question: question_courante.code, intention: texte
        },
        function (trs) {
            $('corriger_intention').disabled = false;
            var r = null;
            try { r = JSON.parse(trs.responseText); } catch (e) { r = null; }
            if (!r || !r.ok) {
                maj($('correction'), '<p class="statut ko">' + echappe(r && r.erreur ? r.erreur : 'Réponse inattendue du serveur.') + '</p>');
            } else if (r.ia) {
                var libelles = {correct: '✅ Intention correcte', partiel: '🟠 Intention partiellement correcte',
                                incorrect: '❌ Intention incorrecte'};
                var t = r.verdict ? '<p class="statut ' + (r.verdict == 'correct' ? 'ok' : 'ko') + '">' + libelles[r.verdict] + '</p>' : '';
                t = t + '<div class="story"><p>' + texte_ia(r.explication || '') + '</p>'
                      + '<p class="astuce">Correction proposée par une IA (' + echappe(r.modele || '') + ') : elle peut se tromper.</p></div>';
                maj($('correction'), t);
            } else {
                maj($('correction'), '<p><strong>Auto-évaluation</strong> : comparez votre intention à celle de l\'enseignant.</p>'
                    + '<blockquote>' + echappe(r.reference || '') + '</blockquote>');
            }
        },
        function (trs) {
            $('corriger_intention').disabled = false;
            if (session_perdue(trs)) return;
            var r = null;
            try { r = JSON.parse(trs.responseText); } catch (e) { r = null; }
            maj($('correction'), '<p class="statut ko">' + echappe(r && r.erreur ? r.erreur : 'La correction a échoué.') + '</p>');
        });
}

function execute_sur_copie(requete) {
    appel('post', {
            Soumettre: 'Envoyer', csrf: csrf_token, requete64: base64_utf8(requete), base: question_courante.base, mode: 0
        },
        function (trs) {
            if (trs.responseText == '') {
                query_failed();
                return;
            }
            cache($('results'));
            maj($('results'), trs.responseText);
            apparait($('results'));
            rafraichit_volet_base(requete);
        },
        function (trs) {
            query_failed(trs);
        });
}

// Garde la réponse de la question courante (en mémoire de la page)
function memorise_reponse(message) {
    if (!question_courante || !$('requete')) return;
    var q = question_courante;
    reponses[cle_reponse(q.base, q.exercice, q.code)] = $('requete').value;
    var pastille = document.querySelector('.pastille-question.courante');
    if (pastille) pastille.classList.toggle('faite', est_repondue(q));
    if (message) ajoute($('results'), '<p class="statut ok">🧺 Réponse mémorisée</p>');
}

function retour_questions() {
    affiche_questions();
}

// Affiche une rubrique du contrôleur, chargée une seule fois puis gardée dans
// la variable globale `memoire` (messages, crédits...)
function rubrique(soumettre, memoire, nom_erreur, onglet) {
    marque_actif(onglet ? $(onglet) : null);
    if (window[memoire] == null) {
        appel('get', {Soumettre: soumettre},
            function (trs) {
                window[memoire] = trs.responseText;
                cache($('posts'));
                maj($('posts'), window[memoire]);
                apparait($('posts'));
            },
            function (trs) {
                if (session_perdue(trs)) return;
                alert(nom_erreur + ': Impossible d\'obtenir la rubrique !')
            });
    } else {
        cache($('posts'));
        maj($('posts'), window[memoire]);
        apparait($('posts'));
    }
}

function accueil() {
    rubrique('rien', 'messages_accueil', 'accueil', 'onglet-accueil');
}

function news() {
    rubrique('news', 'messages', 'nouveautés');
}

function apropos() {
    rubrique('SQL', 'messages_apropos', 'apropos');
}

function credits() {
    rubrique('credits', 'messages_credits', 'crédits');
}

function aides() {
    rubrique('aides', 'messages_aides', 'aide', 'onglet-aide');
}
