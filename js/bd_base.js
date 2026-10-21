//<script language="javascript">
// nécessite Prototypejs.org et Script.aculo.us

var Request = Class.create({
    initialize: function (req, base, res) {
        this.request = req;
        this.base = base;
        this.error = res;
        this.result = null;
    }
});

var RequestSet = Class.create({
    initialize: function () {
        this.init();
    },
    init: function () {
        this.set = new Array();
        this.nb = 0;
    },
    add: function (request) {
        this.set.push(request);
        this.nb = this.nb + 1;
    },
    remove: function (i) {
        this.set.splice(i, 1);
        this.nb = this.set.length;
    },
    show: function () {
        t = '<div class="post"><h2 class="title">Historique</h2>' + '<div class="story"><table cellspacing="5" border="1" cellpadding="2">' + '<thead><th>n°</th><th>Base</th><th>Réussite</th><th>Requête</th><th>Actions</th><th></th></thead>';
        
        for (var i = 0; i < this.nb; i++) {
            r = this.set[i];
            t = t + '<tr><td> ' + i + ' </td><td>' + r.base.escapeHTML() + '</td><td>';
            if (r.error == 'ok') t = t + "<img src='images/tick_64.png' width='32' alt='ok' title='ok' />"; else t = t + "<img src='images/block_64.png' width='32' alt='ko' title='ko'/>";
            t = t + "</td><td><pre>" + r.request.escapeHTML() + "</pre></td><td>";
            t = t + "<img src='images/gear_64.png' alt='Envoyer la requête' title='Envoyer la requête' width='32' onClick='histo(" + i + "); return false;'  style='cursor:pointer'/>";
            t = t + "<img src='images/pencil_64.png' alt='Éditer la requête' title='Éditer la requête' width='32' onClick='histo_mod(" + i + "); return false;'  style='cursor:pointer'/>";
            t = t + "&nbsp;&nbsp;&nbsp;<img src='images/delete_64.png' alt='Supprimer la requête' title='Supprimer la requête' width='32' onClick='histo_del(" + i + "); return false;'  style='cursor:pointer'/>";
            
            t = t + "</td><td><div id='results-" + i + "'></div></td></tr>";
        }
        
        t = t + '</table></div></div>';
        return t;
    }
});



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
    $('posts').update("");
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
        new Ajax.Request('controler.php', {method: 'post', asynchronous: false, parameters: data});
    }
}

function remember() {
    req = new Request($('requete').getValue(), $('base').getValue(), result_type);
    req.result = current_result;
    rs.add(req);
    $('memoriser').hide();
    $('results').insert("<p>Requête mémorisée</p>");
}

function query() {
    current_request = $('requete').getValue();
    current_base = $('base').getValue();
    new Ajax.Request('controler.php', {
        method: 'post',
        parameters: {
            Soumettre: 'Envoyer', csrf: csrf_token, requete: $('requete').getValue(), base: $('base').getValue(), mode: 1
        },
        onSuccess: function (trs) {
            if (trs.responseText == '') { // processus PHP arrêté (requête trop longue)
                query_failed();
                return;
            }
            current_result = trs.responseText;
            $('results').hide();
            $('results').update(current_result);
            $('results').appear();
        },
        onFailure: function (trs) {
            if (trs.status == 403) $('results').update(trs.responseText);
            else query_failed();
        }
    });
}

function query_failed() {
    result_type = 'ko';
    current_result = "<img src='images/block_64.png' width='32' alt='ko' title='ko'/>"
        + "<p>Pas de réponse du serveur : la requête a probablement dépassé la durée maximale d'exécution "
        + "et a été interrompue. Simplifiez-la (conditions de jointure, LIMIT...).</p>";
    $('results').update(current_result);
}

function histo(i) { // Risque de problème de mémoire s'il y a beaucoup de requête et/ou très grosses. Peut-être refaire un accès à la base...
    code = 'results-' + i;
    if ($(code).empty()) {
        r = rs.set[i];
        $(code).hide();
        $(code).update(r.result);
        $('memoriser').hide();
        $(code).appear();
    } else {
        $(code).hide();
        $(code).update("");
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
    $('posts').hide();
    $('posts').update(rs.show());
    $('posts').appear();
}

function new_query() {
    t = '<div class="post">';
    t = t + '<h2 class="title">Édition d\'une requête</h2>';
    t = t + '<div class="story">';
    t = t + '	<form method="POST" id="SaisieRequete" onSubmit="query(); return false;">';
    t = t + '	<p>Base : <select name="base" id="base">';
    for(var i = 0; i < noms_bases.length;i++) {
    	t = t + '<option>'+noms_bases[i].escapeHTML()+'</option>';
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
    
    $('posts').hide();
    $('posts').update(t);
    
    $('requete').setValue(current_request);
    $('base').setValue(current_base);
    
    $('posts').appear();
}

// Valeur JavaScript sûre à placer dans un attribut HTML (onClick="f(...)")
function attr_js(v) {
    return JSON.stringify(v).escapeHTML().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Description d'une base (contenu du fichier de configuration, de confiance)
function description_base(nom) {
    var ref = listeBases[nom]['référence'];
    t = '<a id="matiere"></a><div class="post">';
    t = t + '<h2 class="title">Description de la base ' + nom.escapeHTML() + ' ';
    t = t + '<img src="images/Database.png" alt="Liste des tables" title="Liste des tables (nouvel onglet)" width="32" ';
    t = t + 'onClick="db_tables(' + attr_js(nom) + '); return false;" style="cursor:pointer"/></h2>';
    t = t + '<div class="story">';
    t = t + '<p>' + listeBases[nom]['description'] + '</p>';
    if (ref) t = t + '<p>Référence : <a href="' + ref.escapeHTML().replace(/"/g, '&quot;') + '">' + ref.escapeHTML() + '</a></p>';
    t = t + '</div></div>';
    return t;
}

// Ajoute à la page le résultat d'une rubrique du contrôleur, puis appelle suite()
function insere_rubrique(parametres, suite) {
    new Ajax.Request('controler.php', {
        method: 'get',
        parameters: parametres,
        onSuccess: function (trs) {
            $('posts').insert(trs.responseText);
            if (suite) suite();
        },
        onFailure: function () {
            alert('Impossible d\'obtenir la rubrique !')
        }
    });
}

// Affiche la description d'une base, ses TP et ses questions à thèmes
function db(nom) {
    $('posts').hide();
    $('posts').update(description_base(nom));
    $('posts').show();
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
    $('posts').hide();
    $('posts').update(description_base(nom));
    $('posts').show();
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
    t = t + '<h2>Base ' + q.base.escapeHTML() + '</h2>';
    t = t + '<h2 class="title">Question ' + q.numero + '</h2>';
    t = t + '<div class="story">';
    if (q.aide) {
        t = t + '<p><button class="btnPopup" onclick="bascule_aide(); return false;">Aide</button></p>';
        t = t + '<div id="div1">' + q.aide + '</div>';
    }
    if (q.type == 'rq-intention') {
        t = t + '<p>Requête :</p><pre>' + q.sql.escapeHTML() + '</pre>';
        t = t + '<p>Veuillez donner l\'intention de la requête ci-dessus :<br/>';
        t = t + '<textarea rows="3" cols="80" id="requete"></textarea></p>';
    } else {
        t = t + '<p>' + q.intention.escapeHTML() + '</p>';
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

    $('posts').hide();
    $('posts').update(t);
    var initiale = reponses[q.numero];
    if (initiale == null) initiale = (q.type == 'rq-trou') ? q.sql : '';
    $('requete').setValue(initiale);
    $('posts').appear();
}

// Exécute la réponse de l'élève sur sa copie de la base
function reponse() {
    new Ajax.Request('controler.php', {
        method: 'post',
        parameters: {
            Soumettre: 'Envoyer', csrf: csrf_token, requete: $('requete').getValue(), base: question_courante.base, mode: 0
        },
        onSuccess: function (trs) {
            if (trs.responseText == '') {
                query_failed();
                return;
            }
            $('results').hide();
            $('results').update(trs.responseText);
            $('results').appear();
        },
        onFailure: function (trs) {
            if (trs.status == 403) $('results').update(trs.responseText);
            else query_failed();
        }
    });
}

function memorise_reponse(message) {
    reponses[question_courante.numero] = $('requete').getValue();
    if (message) $('results').insert('<p>Réponse mémorisée</p>');
}

function retour_questions() {
    memorise_reponse(false);
    $('posts').hide();
    $('posts').update(page_questions);
    $('posts').appear();
}

function news() {
    if (messages == null) {
        new Ajax.Request('controler.php', {
            method: 'get',
            parameters: {
                Soumettre: 'rien'
            },
            onSuccess: function (trs) {
                messages = trs.responseText;
                $('posts').hide();
                $('posts').update(messages);
                $('posts').appear();
            },
            onFailure: function () {
                alert('messages: Impossible d\'obtenir la rubrique !')
            }
        });
    } else {
        $('posts').hide();
        $('posts').update(messages);
        $('posts').appear();
    }
}


function mentions() {
    if (messages_mentions == null) {
        new Ajax.Request('controler.php', {
            method: 'get',
            parameters: {
                Soumettre: 'mentions'
            },
            onSuccess: function (trs) {
                messages_mentions = trs.responseText
                $('posts').hide();
                $('posts').update(messages_mentions);
                $('posts').appear();
            },
            onFailure: function () {
                alert('mentions: Impossible d\'obtenir la rubrique !')
            }
        });
    } else {
        $('posts').hide();
        $('posts').update(messages_mentions);
        $('posts').appear();
    }
}

function apropos() {
    if (messages_apropos == null) {
        new Ajax.Request('controler.php', {
            method: 'get',
            parameters: {
                Soumettre: 'SQL'
            },
            onSuccess: function (trs) {
                messages_apropos = trs.responseText
                $('posts').hide();
                $('posts').update(messages_apropos);
                $('posts').appear();
            },
            onFailure: function () {
                alert('apropos: Impossible d\'obtenir la rubrique !')
            }
        });
    } else {
        $('posts').hide();
        $('posts').update(messages_apropos);
        $('posts').appear();
    }
}

function aides() {
    if (messages_aides == null) {
        new Ajax.Request('controler.php', {
            method: 'get',
            parameters: {
                Soumettre: 'aides'
            },
            onSuccess: function (trs) {
                messages_aides = trs.responseText
                $('posts').hide();
                $('posts').update(messages_aides);
                $('posts').appear();
            },
            onFailure: function () {
                alert('aide: Impossible d\'obtenir la rubrique !')
            }
        });
    } else {
        $('posts').hide();
        $('posts').update(messages_aides);
        $('posts').appear();
    }
}

//</script>