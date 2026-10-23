// Remplissage de tables et de colonnes par IA générative (page « ✨ Remplir »).
// Le serveur (ia.php) interroge le modèle, contrôle les lignes à blanc, puis ne les écrit
// dans la copie de la base qu'après validation de l'aperçu. Nécessite bd_base.js.
// ia_disponible et ia_lignes_max sont définis par index.php.

var ia_etat = null; // {base, tables, resultat}

// Bouton d'accès, ou chaîne vide quand la fonction n'est pas activée sur le serveur
function ia_bouton(base_js) {
    if (typeof ia_disponible == 'undefined' || !ia_disponible) return '';
    return '<button type="button" class="btn secondaire" onclick="ia_remplir(' + base_js + ')" '
        + 'title="Faire générer des lignes ou compléter des colonnes par une IA">✨ Remplir</button>';
}

// Retour à l'édition d'une requête sur la base
function ia_retour(base, requete) {
    current_base = base;
    current_request = requete == null ? null : requete;
    new_query();
}

function ia_remplir(nom) {
    // Depuis l'édition d'une requête, la requête en cours est gardée pour y revenir
    if ($('SaisieRequete') && $('requete') && $('requete').value != derniere_suggestion) {
        current_request = $('requete').value;
    }
    current_base = nom;
    marque_actif(pastille_base(nom));
    cache($('posts'));
    maj($('posts'), '<div class="post"><h2 class="title">✨ Remplir la base ' + echappe(nom) + '</h2>'
        + '<div class="story"><p>Chargement de la structure…</p></div></div>');
    montre($('posts'));
    appel('get', {Soumettre: 'modele', nom_base: nom},
        function (trs) {
            var m;
            try {
                m = JSON.parse(trs.responseText);
            } catch (e) {
                m = null;
            }
            if (!m || !m.tables) {
                maj($('posts'), '<div class="post"><div class="story"><p class="message">Impossible de lire la structure de cette base.</p></div></div>');
                return;
            }
            ia_etat = {base: nom, tables: m.tables, resultat: null};
            ia_formulaire();
        },
        function (trs) {
            if (session_perdue(trs)) return;
            maj($('posts'), '<div class="post"><div class="story"><p class="message">Base inconnue ou inaccessible.</p></div></div>');
        });
}

function ia_formulaire() {
    var e = ia_etat, t;
    t = '<div class="post"><h2 class="title">✨ Remplir la base ' + echappe(e.base) + '</h2><div class="story">';
    t += '<p class="astuce">Une IA génère des lignes plausibles à partir de la structure de la table : vous voyez '
        + 'un aperçu contrôlé (types, contraintes, clés étrangères) et rien n\'est écrit dans votre copie '
        + 'avant que vous le validiez. La structure de la table et quelques-unes de ses valeurs sont envoyées '
        + 'à un service d\'IA (fournisseurs français) : n\'y mettez aucune donnée personnelle. '
        + 'Le résultat peut être faux ou approximatif : vérifiez-le.</p>';
    if (e.tables.length == 0) {
        t += '<p class="message">Cette base ne contient aucune table : créez-en une avec CREATE TABLE.</p>';
        t += '<div class="actions"><button type="button" class="btn secondaire" onclick="ia_retour(' + attr_js(e.base) + ')">⬅️ Retour</button></div></div></div>';
        maj($('posts'), t);
        apparait($('posts'));
        return;
    }
    t += '<p><label for="ia-table">Table</label><select id="ia-table" onchange="ia_change_table()">';
    e.tables.forEach(function (tb, i) { t += '<option value="' + i + '">' + echappe(tb.nom) + '</option>'; });
    t += '</select></p>';
    t += '<p class="libelle">Action</p>';
    t += '<p><label><input type="radio" name="ia-mode" value="ajouter" checked onchange="ia_change_table()"> Ajouter de nouvelles lignes</label><br>';
    t += '<label><input type="radio" name="ia-mode" value="modifier" id="ia-mode-modifier" onchange="ia_change_table()"> '
        + 'Compléter des colonnes vides de lignes existantes <span class="astuce">(par exemple une colonne ajoutée avec ALTER TABLE)</span></label></p>';
    t += '<p class="libelle">Colonnes à remplir</p><div id="ia-colonnes"></div>';
    t += '<p><label for="ia-nb">Nombre de lignes</label> <input type="number" id="ia-nb" min="1" max="' + ia_lignes_max
        + '" value="' + Math.min(10, ia_lignes_max) + '"> <span class="astuce">(au plus ' + ia_lignes_max + ')</span></p>';
    t += '<p class="libelle"><label for="ia-consigne">Précisions <span class="astuce">(facultatif)</span></label></p>';
    t += '<p><input type="text" id="ia-consigne" maxlength="300" size="70" placeholder="par exemple : des vols entre villes européennes"></p>';
    t += '<div class="actions"><button type="button" class="btn" id="ia-go" onclick="ia_genere()">✨ Générer un aperçu</button>';
    t += '<button type="button" class="btn secondaire" onclick="ia_retour(' + attr_js(e.base) + ')">⬅️ Retour</button></div>';
    t += '<div id="ia-resultat"></div>';
    t += '</div></div>';
    maj($('posts'), t);
    apparait($('posts'));
    ia_change_table();
}

function ia_table() {
    return ia_etat.tables[parseInt($('ia-table').value, 10)];
}

function ia_mode() {
    var r = document.querySelector('input[name="ia-mode"]:checked');
    return r ? r.value : 'ajouter';
}

// Liste des colonnes de la table choisie : toutes en mode « ajouter » ; en mode « compléter »,
// celles qui ne sont pas la clé primaire (elle identifie les lignes)
function ia_change_table() {
    var tb = ia_table(), mode, t = '', pk = tb.colonnes.filter(function (c) { return c.pk > 0; });
    $('ia-mode-modifier').disabled = pk.length == 0;
    if (pk.length == 0 && ia_mode() == 'modifier') document.querySelector('input[name="ia-mode"][value="ajouter"]').checked = true;
    mode = ia_mode();
    tb.colonnes.forEach(function (c, i) {
        var cle = c.pk > 0;
        if (mode == 'modifier' && cle) return;
        t += '<label class="ia-colonne"><input type="checkbox" name="ia-col" value="' + i + '"' + (mode == 'ajouter' ? ' checked' : '') + '> '
            + echappe(c.nom) + (c.type ? ' <span class="astuce">' + echappe(c.type) + '</span>' : '') + '</label> ';
    });
    if (pk.length == 0) t += '<p class="astuce">Cette table n\'a pas de clé primaire : seul l\'ajout de lignes est possible.</p>';
    $('ia-colonnes').innerHTML = t;
    $('ia-resultat').innerHTML = '';
}

function ia_colonnes_choisies() {
    var tb = ia_table(), noms = [];
    document.querySelectorAll('input[name="ia-col"]:checked').forEach(function (c) {
        noms.push(tb.colonnes[parseInt(c.value, 10)].nom);
    });
    return noms;
}

// Texte d'erreur d'une réponse du serveur (JSON {erreur}) ou d'une panne
function ia_message(trs) {
    var d = null;
    try {
        d = JSON.parse(trs.responseText);
    } catch (e) {
        d = null;
    }
    if (d && d.erreur) return d.erreur;
    if (!trs.status) return 'Pas de réponse du serveur : la génération a peut-être dépassé la durée maximale. Réessayez avec moins de lignes.';
    return 'Erreur inattendue (code ' + trs.status + ').';
}

function ia_genere() {
    var cols = ia_colonnes_choisies(), tb = ia_table(), mode = ia_mode();
    if (cols.length == 0) {
        $('ia-resultat').innerHTML = '<p class="statut ko">Choisissez au moins une colonne.</p>';
        return;
    }
    $('ia-go').disabled = true;
    $('ia-resultat').innerHTML = '<p class="statut">⏳ Génération en cours (jusqu\'à une trentaine de secondes)…</p>';
    appel('post', {
            Soumettre: 'ia_generer', csrf: csrf_token, base: ia_etat.base, table: tb.nom, mode: mode,
            colonnes: JSON.stringify(cols), nb: $('ia-nb').value, consigne: $('ia-consigne').value
        },
        function (trs) {
            var d = null;
            try {
                d = JSON.parse(trs.responseText);
            } catch (e) {
                d = null;
            }
            $('ia-go').disabled = false;
            if (!d || !d.ok) {
                $('ia-resultat').innerHTML = '<p class="statut ko">❌ ' + echappe(ia_message(trs)) + '</p>';
                return;
            }
            ia_etat.resultat = d;
            ia_apercu();
        },
        function (trs) {
            $('ia-go').disabled = false;
            if (session_perdue(trs)) return;
            $('ia-resultat').innerHTML = '<p class="statut ko">❌ ' + echappe(ia_message(trs)) + '</p>';
        });
}

function ia_valeur_html(v) {
    return v === null ? '<span class="astuce">NULL</span>' : echappe(v);
}

// Aperçu des lignes générées, avec l'état de chacune (acceptée ou refusée par la base)
function ia_apercu() {
    var d = ia_etat.resultat, bonnes = d.lignes.filter(function (l) { return l.ok; }).length, t;
    t = '<h3>Aperçu : ' + d.lignes.length + ' ligne(s) proposée(s), ' + bonnes + ' acceptée(s) par la base</h3>';
    t += '<table class="resultat"><thead><tr><th></th>';
    d.colonnes.forEach(function (c) { t += '<th>' + echappe(c) + '</th>'; });
    t += '<th>Contrôle</th></tr></thead><tbody>';
    d.lignes.forEach(function (l, i) {
        t += '<tr><td>' + (i + 1) + '</td>';
        d.colonnes.forEach(function (c) { t += '<td>' + ia_valeur_html(l.valeurs[c]) + '</td>'; });
        t += '<td>' + (l.ok ? '<span title="Acceptée">✅</span>'
                            : '<span title="Refusée">❌ ' + echappe(l.erreur) + '</span>') + '</td></tr>';
    });
    t += '</tbody></table>';
    if (d.ignorees > 0) t += '<p class="astuce">' + d.ignorees + ' ligne(s) en trop renvoyée(s) par le modèle ont été ignorées.</p>';
    t += '<p class="astuce">Modèle : ' + echappe(d.modele) + '. Générations restantes aujourd\'hui : ' + d.restantes + '.</p>';
    t += '<div class="actions">';
    if (bonnes > 0) {
        t += '<button type="button" class="btn" id="ia-applique" onclick="ia_applique()">'
            + (d.mode == 'modifier' ? '✅ Compléter ' : '✅ Insérer ') + bonnes + ' ligne(s)</button>';
    }
    t += '<button type="button" class="btn secondaire" onclick="ia_genere()">🔁 Régénérer</button></div>';
    t += '<div id="ia-fin"></div>';
    $('ia-resultat').innerHTML = t;
    surligne($('ia-resultat'));
}

// Écrit dans la copie de l'étudiant les lignes acceptées de l'aperçu
function ia_applique() {
    var d = ia_etat.resultat;
    var lignes = d.lignes.filter(function (l) { return l.ok; }).map(function (l) { return l.valeurs; });
    $('ia-applique').disabled = true;
    appel('post', {
            Soumettre: 'ia_appliquer', csrf: csrf_token, base: ia_etat.base, table: d.table, mode: d.mode,
            colonnes: JSON.stringify(d.ecrire), lignes: JSON.stringify(lignes)
        },
        function (trs) {
            var r = null, t;
            try {
                r = JSON.parse(trs.responseText);
            } catch (e) {
                r = null;
            }
            if (!r || !r.ok) {
                $('ia-applique').disabled = false;
                $('ia-fin').innerHTML = '<p class="statut ko">❌ ' + echappe(ia_message(trs)) + '</p>';
                return;
            }
            t = '<p class="statut ok">✅ ' + r.ecrites + (d.mode == 'modifier' ? ' ligne(s) complétée(s)' : ' ligne(s) insérée(s)')
                + ' dans ' + echappe(d.table) + '.</p>';
            if (r.refusees.length > 0) {
                t += '<p class="statut ko">' + r.refusees.length + ' ligne(s) refusée(s) à l\'écriture : '
                    + r.refusees.map(function (x) { return 'ligne ' + x.ligne + ' (' + echappe(x.erreur) + ')'; }).join(', ') + '</p>';
            }
            t += '<div class="actions"><button type="button" class="btn" onclick="ia_retour(' + attr_js(ia_etat.base) + ', '
                + attr_js('SELECT *\nFROM ' + ia_ident_sql(d.table) + ';') + ')">✏️ Interroger la table</button></div>';
            $('ia-resultat').querySelectorAll('.actions button').forEach(function (b) { b.disabled = true; });
            $('ia-fin').innerHTML = t;
        },
        function (trs) {
            $('ia-applique').disabled = false;
            if (session_perdue(trs)) return;
            $('ia-fin').innerHTML = '<p class="statut ko">❌ ' + echappe(ia_message(trs)) + '</p>';
        });
}

function ia_ident_sql(nom) {
    return /^[A-Za-z_][A-Za-z0-9_]*$/.test(nom) ? nom : '"' + nom.replace(/"/g, '""') + '"';
}
