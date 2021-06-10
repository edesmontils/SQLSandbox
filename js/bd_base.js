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
        for (var j = i; j < this.nb - 1; j++)
        this.set[i] = this.set[i + 1];
        this.set[ this.nb - 1] = null;
        this.nb = this.nb - 1;
    },
    show: function () {
        t = '<div class="post"><h2 class="title">Historique</h2>' + '<div class="story"><table cellspacing="5" border="1" cellpadding="2">' + '<thead><th>n°</th><th>Base</th><th>n° TP</th><th>n° Question</th><th>Réussite</th><th>Requête</th><th>Actions</th><th></th></thead>';        
        for (var i = 0; i < this.nb; i++) {
            r = this.set[i];
            t = t + '<tr><td> ' + i + ' </td><td>' + r.base + '</td><td>';
            if (r.error == 'ok') t = t + "<img src='images/tick_64.png' width='32' alt='ok' title='ok' />"; else t = t + "<img src='images/block_64.png' width='32' alt='ko' title='ko'/>";
            t = t + "</td><td><pre>" + r.request + "</pre></td><td>";
            t = t + "<img src='images/gear_64.png' alt='Envoyer la requête' title='Envoyer la requête' width='32' onClick='histo(" + i + "); return false;'  style='cursor:pointer'/>";
            t = t + "<img src='images/pencil_64.png' alt='Éditer la requête' title='Éditer la requête' width='32' onClick='histo_mod(" + i + "); return false;'  style='cursor:pointer'/>";
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
    new Ajax.Request('controler.php', {
        method: 'post',
        parameters: {
            Soumettre: 'end'
        },
        onSuccess: function (trs) {
            $('posts').hide();
            $('posts').update(trs.responseText);
            $('posts').appear();
        },
        onFailure: function () {
            alert('end: Impossible !')
        }
    });
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
            Soumettre: 'Envoyer', requete: $('requete').getValue(), base: $('base').getValue(), mode: 1
        },
        onSuccess: function (trs) {
            current_result = trs.responseText;
            $('results').hide();
            $('results').update(current_result);
            $('results').appear();
        },
        onFailure: function () {
            alert('new_query: Impossible d\'obtenir la rubrique !')
        }
    });
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
    if (confirm("Êtes-vous certain(e) de vouloir supprimer la requête " + i + " ?")) {
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
    	t = t + '<option>'+noms_bases[i]+'</option>';
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

// Affiche les tables de la base de données
function db_tables(nom){
    window.open('dbListe.php?var1=@\''+nom+'\'');
}

// Affiche les tables d'une base de données
function sommaire(nom){
    t = '<a id="matiere"></a><div class="post">';
	t = t + '	<h2 class="title">Description de la base '+nom;
    t = t + '	<img src="images/Database.png" name="Liste des tables" alt="Liste des tables" title="Liste des tables" width="32" ';
    t = t + '		onClick="db_tables(\''+nom+'\'); return false;" style="cursor:pointer"/>';
    t = t + '</h2>';
	t = t + '    <div class="story">';
  	t = t + '<p>'+listeBases[nom]['description']+'</p>';
  	t = t + '<p>Référence : <a href="'+listeBases[nom]['référence']+'">'+listeBases[nom]['référence']+'</a></p>';
    $('posts').hide();
    $('posts').update(t);
    $('posts').show();
}

function db(nom) {
	sommaire(nom);

	t = t +  '</div></div>';
    t = t +  '    <div class="story"><ul>';
    tp(nom);
    
    t = t +  '</ul></div></div>';
    t = t +  '    <div class="story"><ul>';
    themes_dispo(nom);
    t = t +  '</ul></div></div>';

	 $('posts').hide();
     $('posts').update(t);
     $('posts').show();
}

// Affiche la liste des TP disponibles pour une bdd
function tp(nom) {
    sommaire(nom);

    new Ajax.Request('controler.php', {
        method: 'get',
        parameters: {
            Soumettre: 'tp', nom_base: nom, db_name: listeBases[nom]['dbName']  
        },
        onSuccess: function (trs) {
            $('posts').insert(trs.responseText);
        },
        onFailure: function () {
            alert('messages: Impossible d\'obtenir la rubrique !')
        }
    });
}

function themes_dispo(nom) {

    sommaire(nom);

    new Ajax.Request('controler.php', {
        method: 'get',
        parameters: {
            Soumettre: 'themes_dispo', nom_base: nom, descr: t, db_name: listeBases[nom]['dbName']
        },
        onSuccess: function (trs) {
            $('posts').insert(trs.responseText);
        },
        onFailure: function () {
            alert('messages: Impossible d\'obtenir la rubrique !')
        }
    });
}

function pageInitial(nom, tpName, db_name) {

    sommaire(nom);

    new Ajax.Request('controler.php', {
        method: 'get',
        parameters: {
            Soumettre: 'pageIni', nom_base: nom, tp_name: tpName, descr: t, dbName: db_name
        },
        onSuccess: function (trs) {
            $('posts').insert(trs.responseText);
        },
        onFailure: function () {
            alert('messages: Impossible d\'obtenir la rubrique !')
        }
    });
}

function popUp(){
    //alert(aide);
    var elem = document.getElementById('div1');
    elem.style.display = "block";
    //console.log(elem);
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

function new_reponse_intention($Quest,$base,$aide,$requete) {
    clear;
    t = '<div class="post">';
    t = t + '<h2>Base \''+$base+'\'</h2>';
    t = t + '<h2 class="title">Question \''+$Quest+'\'</h2>';
    t = t + '   <div class="story">';
    if($aide){
        t = t + '   	<li>';
        t = t + '   	<button id="btnPopup" class="btnPopup" onclick="popUp()">';
        t = t + '       Aide';
        t = t + '   	</button>';
        t = t + '   	</li>';
        t = t + '   	<br>';
        t = t + '   	<div id="div1">' + $aide + '</div>';
    }
    t = t + '   <form method="POST" id="SaisieRequete" onSubmit="reponse(\''+$base+'\'); return false;">';
    t = t + '       <p>\''+$requete+'\'<br/>';
    t = t + '       <p>Veuillez donner l\'intention de la requète ci-dessus: <br/>';
    t = t + '   	<textarea name="requete" rows="1" cols="80" id="requete"></textarea><br/>';

    t = t + '       <input type="hidden" name="Soumettre" value="Envoyer"/>';
    t = t + '       <img src="images/gear_64.png" name="Soumettre" alt="Soumettre" title="Soumettre" width="32" ';
    t = t + '           onClick="reponse(\''+$base+'\'); return false;" style="cursor:pointer" id="send_new"/>';
    t = t + '       <img src="images/add_64.png" id="memoriser" name="Mémoriser" alt="Mémoriser" title="Mémoriser" width="32" ';
    t = t + '           onClick="remember1(\''+$base+'\'); return false;" style="cursor:pointer"/>';
    t = t + '   </form></p>';
    t = t + '   <div id="results"></div>';
    t = t + '</div>';
    t = t + '</div>';
   
    $('posts').hide();
    $('posts').update(t);
    $('posts').appear();
}

function new_reponse_trou($Quest,$base,$aide,$intention) {
    clear;
    t = '<div class="post">';
    t = t + '<h2>Base \''+$base+'\'</h2>';
    t = t + '<h2 class="title">Question \''+$Quest+'\'</h2>';
    t = t + '   <div class="story">';
    t = t + '   <br>';
    if($aide){
        t = t + '   	<li>';
        t = t + '   	<button id="btnPopup" class="btnPopup" onclick="popUp()">';
        t = t + '       Aide';
        t = t + '   	</button>';
        t = t + '   	</li>';
        t = t + '   	<br>';
        t = t + '   	<div id="div1">' + $aide + '</div>';
    }

    t = t + '   <form method="POST" id="SaisieRequete" onSubmit="reponse(\''+$base+'\'); return false;">';
    t = t + '       <p>\''+$intention+'\'<br/>';
    t = t + '       <p>Veuillez compléter la requete suivante: <br/>';
    t = t + '   	<textarea name="requete" rows="10" cols="80" id="requete"></textarea><br/>';

    t = t + '       <input type="hidden" name="Soumettre" value="Envoyer"/>';
    t = t + '       <img src="images/gear_64.png" name="Soumettre" alt="Soumettre" title="Soumettre" width="32" ';
    t = t + '           onClick="reponse(\''+$base+'\'); return false;" style="cursor:pointer" id="send_new"/>';
    t = t + '       <img src="images/add_64.png" id="memoriser" name="Mémoriser" alt="Mémoriser" title="Mémoriser" width="32" ';
    t = t + '           onClick="remember1(\''+$base+'\'); return false;" style="cursor:pointer"/>';
    t = t + '   </form></p>';
    t = t + '   <div id="results"></div>';
    t = t + '</div>';
    t = t + '</div>';
   
    $('posts').hide();
    $('posts').update(t);
    $('posts').appear();
}

function new_reponse_requete($Quest,$base,$aide,$intention) {
    clear;
    t = '<div class="post">';
    t = t + '<h2>Base \''+$base+'\'</h2>';
    t = t + '<h2 class="title">Question \''+$Quest+'\'</h2>';
    t = t + '   <div class="story">';
    if($aide){
        t = t + '   	<li>';
        t = t + '   	<button id="btnPopup" class="btnPopup" onclick="popUp()">';
        t = t + '       Aide';
        t = t + '   	</button>';
        t = t + '   	</li>';
        t = t + '   	<br>';
        t = t + '   	<div id="div1">' + $aide + '</div>';
    }
    t = t + '   <form method="POST" id="SaisieRequete" onSubmit="reponse(\''+$base+'\'); return false;">';
    t = t + '       <p>\''+$intention+'\'<br/>';
    t = t + '       <p>Veuillez donner la requete : <br/>';
    t = t + '   	<textarea name="requete" rows="10" cols="80" id="requete"></textarea><br/>';
    t = t + '       <input type="hidden" name="Soumettre" value="Envoyer"/>';
    t = t + '       <img src="images/gear_64.png" name="Soumettre" alt="Soumettre" title="Soumettre" width="32" ';
    t = t + '           onClick="reponse(\''+$base+'\'); return false;" style="cursor:pointer" id="send_new"/>';
    t = t + '       <img src="images/add_64.png" id="memoriser" name="Mémoriser" alt="Mémoriser" title="Mémoriser" width="32" ';
    t = t + '           onClick="remember1(\''+$base+'\'); return false;" style="cursor:pointer"/>';
    t = t + '   </form></p>';
    t = t + '   <div id="results"></div>';
    t = t + '</div>';
    t = t + '</div>';
   
    $('posts').hide();
    $('posts').update(t);
    $('posts').appear();
}

function reponse($base) {
    current_request = $('requete').getValue();
    current_base = $base
    new Ajax.Request('controler.php', {
        method: 'post',
        parameters: {
            Soumettre: 'reponse', requete: $('requete').getValue(), base: $base, mode: 1
        },
        onSuccess: function (trs) {
            current_result = trs.responseText;
            $('results').hide();
            $('results').update(current_result);
            $('results').appear();
        },
        onFailure: function () {
            alert('new_query: Impossible d\'obtenir la rubrique !')
        }
    });
}

function remember1($base) {
    req = new Request($('requete').getValue(), $base, result_type);
    req.result = current_result;
    rs.add(req);
    $('memoriser').hide();
    $('results').insert("<p>Requête mémorisée</p>");
}

//</script>