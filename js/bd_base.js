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

// Exécute, juste après leur insertion, les <script> d'un fragment HTML venu du
// serveur (ils positionnent result_type) : innerHTML ne les exécute pas.
function execute_scripts(html) {
    var re = /<script[^>]*>([\s\S]*?)<\/script>/gi, m, codes = [];
    while ((m = re.exec(html)) !== null) codes.push(m[1]);
    if (codes.length > 0) setTimeout(function () {
        codes.forEach(function (code) { (0, eval)(code); });
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

// Appel AJAX à controler.php. surSucces(reponse) est appelé pour un code 2xx ou 304,
// ou sans code du tout (serveur injoignable : réponse vide) ; surEchec(reponse) sinon.
// reponse : {status, responseText, getHeader(nom)}
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
    }
    remove(i) {
        this.set.splice(i, 1);
        this.nb = this.set.length;
    }
    show() { // corps de classe toujours en mode strict : variables déclarées
        var t, r;
        t = '<div class="post"><h2 class="title">Historique</h2>' + '<div class="story"><table cellspacing="5" border="1" cellpadding="2">' + '<thead><th>n°</th><th>Base</th><th>Réussite</th><th>Requête</th><th>Actions</th><th></th></thead>';

        for (var i = 0; i < this.nb; i++) {
            r = this.set[i];
            t = t + '<tr><td> ' + i + ' </td><td>' + echappe(r.base) + '</td><td>';
            if (r.error == 'ok') t = t + "<img src='images/tick_64.png' width='32' alt='ok' title='ok' />"; else t = t + "<img src='images/block_64.png' width='32' alt='ko' title='ko'/>";
            t = t + "</td><td><pre>" + echappe(r.request) + "</pre></td><td>";
            t = t + "<img src='images/gear_64.png' alt='Envoyer la requête' title='Envoyer la requête' width='32' onClick='histo(" + i + "); return false;'  style='cursor:pointer'/>";
            t = t + "<img src='images/pencil_64.png' alt='Éditer la requête' title='Éditer la requête' width='32' onClick='histo_mod(" + i + "); return false;'  style='cursor:pointer'/>";
            t = t + "&nbsp;&nbsp;&nbsp;<img src='images/delete_64.png' alt='Supprimer la requête' title='Supprimer la requête' width='32' onClick='histo_del(" + i + "); return false;'  style='cursor:pointer'/>";

            t = t + "</td><td><div id='results-" + i + "'></div></td></tr>";
        }

        t = t + '</table></div></div>';
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

function clear() {

	// Variables de gestion de la mémoire initialisées.
	rs = new RequestSet();
	result_type = null;
	current_request = "Select * \n From X \n Where x";
	current_base = "";
	current_result = null;

	// mémorisation des messages et aides pour éviter de charger le serveur.
	messages_aides = null;
	messages = null;
	messages_mentions = null;
	messages_apropos = null;

	// Page effacée
    maj($('posts'), "");
}

function init() {
    clear();
    news();
}

function attention() {
    alert('Attention, nous avez perdu l\'historique !!!');
}

function end() {
    // sendBeacon survit à la fermeture de la page, contrairement à un appel AJAX
    var data = 'Soumettre=end&csrf=' + encodeURIComponent(csrf_token);
    if (navigator.sendBeacon) {
        navigator.sendBeacon('controler.php', new Blob([data], {type: 'application/x-www-form-urlencoded'}));
    } else {
        fetch('controler.php', {method: 'POST', keepalive: true, credentials: 'same-origin',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: data});
    }
}

function remember() {
    req = new Requete($('requete').value, $('base').value, result_type);
    req.result = current_result;
    rs.add(req);
    cache($('memoriser'));
    ajoute($('results'), "<p>Requête mémorisée</p>");
}

function query() {
    current_request = $('requete').value;
    current_base = $('base').value;
    appel('post', {
            Soumettre: 'Envoyer', csrf: csrf_token, requete: $('requete').value, base: $('base').value, mode: 1
        },
        function (trs) {
            if (trs.responseText == '') { // processus PHP arrêté (requête trop longue)
                query_failed();
                return;
            }
            current_result = trs.responseText;
            cache($('results'));
            maj($('results'), current_result);
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
    if (trs && trs.status == 403 && de_l_application) {
        maj($('results'), trs.responseText);
        return;
    }
    result_type = 'ko';
    if (trs && !de_l_application && (trs.status == 403 || trs.status == 406)) {
        message = "La requête a été bloquée avant d'atteindre l'application (code " + trs.status
            + ") : le pare-feu de l'hébergeur l'a sans doute prise pour une attaque. "
            + "Reformulez-la, ou signalez-la à votre enseignant.";
    } else {
        message = "Pas de réponse du serveur : la requête a probablement dépassé la durée maximale "
            + "d'exécution et a été interrompue. Simplifiez-la (conditions de jointure, LIMIT...).";
    }
    current_result = "<img src='images/block_64.png' width='32' alt='ko' title='ko'/><p>" + message + "</p>";
    maj($('results'), current_result);
}

function histo(i) { // Risque de problème de mémoire s'il y a beaucoup de requête et/ou très grosses. Peut-être refaire un accès à la base...
    code = 'results-' + i;
    if (est_vide($(code))) {
        r = rs.set[i];
        cache($(code));
        maj($(code), r.result);
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
    if (confirm("Êtes-vous certain(e) de vouloir supprimer la requête " + i + " ?")) {
        rs.remove(i);
        get_histo();
    }
}

function get_histo() {
    cache($('posts'));
    maj($('posts'), rs.show());
    apparait($('posts'));
}

function new_query() {
    t = '<div class="post">';
    t = t + '<h2 class="title">Édition d\'une requête</h2>';
    t = t + '<div class="story">';
    t = t + '	<form method="POST" id="SaisieRequete" onSubmit="query(); return false;">';
    t = t + '	<p>Base : <select name="base" id="base">';
    for(var i = 0; i < noms_bases.length;i++) {
    	t = t + '<option>'+echappe(noms_bases[i])+'</option>';
    }
    t = t + '</select></p>';
    t = t + '	<p>Entrez votre requête : <br/>';
    t = t + '	<textarea name="requete" rows="10" cols="80" id="requete"></textarea><br/>';
    t = t + '	<input type="hidden" name="Soumettre" value="Envoyer"/>';
    t = t + '	<img src="images/gear_64.png" name="Soumettre" alt="Soumettre" title="Soumettre" width="32" ';
    t = t + '		onClick="query(); return false;" style="cursor:pointer" id="send_new"/>';
    t = t + '	</form></p>';
    t = t + '	<div id="results"></div>';
    t = t + '</div>';
    t = t + '</div>';

    cache($('posts'));
    maj($('posts'), t);

    $('requete').value = current_request;
    choisit($('base'), current_base);

    apparait($('posts'));
}

// Valeur JavaScript sûre à placer dans un attribut HTML (onClick="f(...)")
function attr_js(v) {
    return echappe(JSON.stringify(v)).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Description d'une base (contenu du fichier de configuration, de confiance)
function description_base(nom) {
    var ref = listeBases[nom]['référence'];
    t = '<a id="matiere"></a><div class="post">';
    t = t + '<h2 class="title">Description de la base ' + echappe(nom) + ' ';
    t = t + '<img src="images/Database.png" alt="Liste des tables" title="Liste des tables (nouvel onglet)" width="32" ';
    t = t + 'onClick="db_tables(' + attr_js(nom) + '); return false;" style="cursor:pointer"/></h2>';
    t = t + '<div class="story">';
    t = t + '<p>' + listeBases[nom]['description'] + '</p>';
    if (ref) t = t + '<p>Référence : <a href="' + echappe(ref).replace(/"/g, '&quot;') + '">' + echappe(ref) + '</a></p>';
    t = t + '</div></div>';
    return t;
}

// Ajoute à la page le résultat d'une rubrique du contrôleur, puis appelle suite()
function insere_rubrique(parametres, suite) {
    appel('get', parametres,
        function (trs) {
            ajoute($('posts'), trs.responseText);
            if (suite) suite();
        },
        function () {
            alert('Impossible d\'obtenir la rubrique !')
        });
}

// Affiche la description d'une base, ses TP et ses questions à thèmes
function db(nom) {
    cache($('posts'));
    maj($('posts'), description_base(nom));
    montre($('posts'));
    // Requêtes enchaînées pour garder l'ordre d'affichage
    insere_rubrique({Soumettre: 'tp', nom_base: nom}, function () {
        insere_rubrique({Soumettre: 'themes_dispo', nom_base: nom});
    });
}

// Contenu des tables d'une base, dans un nouvel onglet
function db_tables(nom) {
    window.open('dbListe.php?base=' + encodeURIComponent(nom));
}

// Page d'accueil d'un TP
function page_tp(nom, tp) {
    cache($('posts'));
    maj($('posts'), description_base(nom));
    montre($('posts'));
    insere_rubrique({Soumettre: 'pageIni', nom_base: nom, tp_name: tp});
}

//=================================================================
// Questions (pages tp.php et questionsThem.php)
//=================================================================

var reponses = {};          // réponses mémorisées, par numéro de question
var question_courante = null;
var page_questions = null;  // liste des questions, pour y revenir

function bascule_aide() {
    var aide = $('div1');
    aide.style.display = (aide.style.display == 'block') ? 'none' : 'block';
}

function ouvre_question(q) {
    if (page_questions == null) page_questions = $('posts').innerHTML;
    question_courante = q;
    t = '<div class="post">';
    t = t + '<h2>Base ' + echappe(q.base) + '</h2>';
    t = t + '<h2 class="title">Question ' + q.numero + '</h2>';
    t = t + '<div class="story">';
    if (q.aide) {
        t = t + '<p><button class="btnPopup" onclick="bascule_aide(); return false;">Aide</button></p>';
        t = t + '<div id="div1">' + q.aide + '</div>';
    }
    if (q.type == 'rq-intention') {
        t = t + '<p>Requête :</p><pre>' + echappe(q.sql) + '</pre>';
        t = t + '<p>Veuillez donner l\'intention de la requête ci-dessus :<br/>';
        t = t + '<textarea rows="3" cols="80" id="requete"></textarea></p>';
    } else {
        t = t + '<p>' + echappe(q.intention) + '</p>';
        t = t + '<p>' + (q.type == 'rq-trou' ? 'Veuillez compléter la requête suivante :' : 'Veuillez donner la requête :') + '<br/>';
        t = t + '<textarea rows="10" cols="80" id="requete"></textarea></p>';
    }
    t = t + '<p>';
    if (q.type != 'rq-intention') {
        t = t + '<img src="images/gear_64.png" alt="Exécuter" title="Exécuter la requête" width="32" ';
        t = t + 'onClick="reponse(); return false;" style="cursor:pointer"/>';
    }
    t = t + '<img src="images/add_64.png" alt="Mémoriser" title="Mémoriser la réponse" width="32" ';
    t = t + 'onClick="memorise_reponse(true); return false;" style="cursor:pointer"/>';
    t = t + '<img src="images/left_64.png" alt="Retour" title="Retourner à la liste des questions" width="32" ';
    t = t + 'onClick="retour_questions(); return false;" style="cursor:pointer"/>';
    t = t + '</p><div id="results"></div>';
    t = t + '</div></div>';

    cache($('posts'));
    maj($('posts'), t);
    var initiale = reponses[q.numero];
    if (initiale == null) initiale = (q.type == 'rq-trou') ? q.sql : '';
    $('requete').value = initiale;
    apparait($('posts'));
}

// Exécute la réponse de l'élève sur sa copie de la base
function reponse() {
    appel('post', {
            Soumettre: 'Envoyer', csrf: csrf_token, requete: $('requete').value, base: question_courante.base, mode: 0
        },
        function (trs) {
            if (trs.responseText == '') {
                query_failed();
                return;
            }
            cache($('results'));
            maj($('results'), trs.responseText);
            apparait($('results'));
        },
        function (trs) {
            query_failed(trs);
        });
}

function memorise_reponse(message) {
    reponses[question_courante.numero] = $('requete').value;
    if (message) ajoute($('results'), '<p>Réponse mémorisée</p>');
}

function retour_questions() {
    memorise_reponse(false);
    cache($('posts'));
    maj($('posts'), page_questions);
    apparait($('posts'));
}

// Affiche une rubrique du contrôleur, chargée une seule fois puis gardée dans
// la variable globale `memoire` (messages, mentions...)
function rubrique(soumettre, memoire, nom_erreur) {
    if (window[memoire] == null) {
        appel('get', {Soumettre: soumettre},
            function (trs) {
                window[memoire] = trs.responseText;
                cache($('posts'));
                maj($('posts'), window[memoire]);
                apparait($('posts'));
            },
            function () {
                alert(nom_erreur + ': Impossible d\'obtenir la rubrique !')
            });
    } else {
        cache($('posts'));
        maj($('posts'), window[memoire]);
        apparait($('posts'));
    }
}

function news() {
    rubrique('rien', 'messages', 'messages');
}

function mentions() {
    rubrique('mentions', 'messages_mentions', 'mentions');
}

function apropos() {
    rubrique('SQL', 'messages_apropos', 'apropos');
}

function aides() {
    rubrique('aides', 'messages_aides', 'aide');
}
