// Modèle d'une base : diagramme (SVG dessiné ici, sans bibliothèque), schéma relationnel,
// structure des tables et script SQL de création. Le modèle est lu par le serveur sur la
// copie de l'étudiant (controler.php?Soumettre=modele) : il suit ses CREATE, ALTER et DROP.
// Nécessite bd_base.js (appel, maj, echappe, attr_js...).

var modele_courant = null;

function modele(nom) {
    // Depuis l'édition d'une requête, la requête en cours est gardée pour y revenir
    if ($('SaisieRequete') && $('requete') && $('requete').value != derniere_suggestion) {
        current_request = $('requete').value;
    }
    current_base = nom;
    marque_actif(pastille_base(nom));
    cache($('posts'));
    maj($('posts'), '<div class="post"><h2 class="title">Modèle de la base ' + echappe(nom) + '</h2>'
        + '<p>Chargement du modèle…</p></div>');
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
                maj($('posts'), '<div class="post"><p class="message">Impossible de lire le modèle de cette base.</p></div>');
                return;
            }
            modele_courant = m;
            affiche_modele(nom, m);
        },
        function (trs) {
            if (session_perdue(trs)) return;
            maj($('posts'), '<div class="post"><p class="message">Base inconnue ou inaccessible.</p></div>');
        });
}

function affiche_modele(nom, m) {
    var nb_cles = 0;
    m.tables.forEach(function (t) { nb_cles += t.cles.length; });
    var t = '<div class="post modele">';
    t += '<h2 class="title">Modèle de la base ' + echappe(nom) + '</h2>';
    t += '<div class="actions">';
    t += '<button type="button" class="btn" onclick="current_request = null; new_query()">✏️ Interroger cette base</button>';
    t += '<button type="button" class="btn secondaire" onclick="db_tables(' + attr_js(nom) + ')">📋 Voir les tables</button>';
    t += '<button type="button" class="btn secondaire" onclick="db(' + attr_js(nom) + ')">📖 Description</button>';
    t += '</div>';
    t += '<p class="astuce">Modèle lu sur votre copie de la base : il tient compte de vos CREATE, ALTER et DROP. '
        + m.tables.length + ' table(s), ' + nb_cles + ' clé(s) étrangère(s).</p>';
    if (m.tables.length == 0) {
        t += '<p class="message">Cette base ne contient aucune table.</p></div>';
        maj($('posts'), t);
        apparait($('posts'));
        return;
    }
    if (nb_cles == 0) {
        t += '<p class="message">Aucune clé étrangère n\'est déclarée dans cette base : '
            + 'le diagramme ne peut pas montrer de liens entre les tables.</p>';
    }
    t += '<p class="legende"><span class="cle-primaire">souligné</span> : clé primaire'
        + '<span class="separateur">·</span><strong>#</strong> : clé étrangère'
        + '<span class="separateur">·</span>flèche : de la clé étrangère vers la table qu\'elle référence</p>';

    t += '<h3>Diagramme</h3>';
    t += '<div class="actions"><button type="button" class="btn secondaire petit" onclick="bascule_ajustement(this)">'
        + '⤢ Ajuster à la largeur</button></div>';
    t += '<div class="diagramme" id="diagramme"></div>';

    t += '<h3>Schéma relationnel</h3>';
    t += schema_relationnel(m);

    t += '<details class="volet"><summary>Structure des tables</summary>' + structure_tables(m) + '</details>';
    t += '<details class="volet" id="volet-sql" ontoggle="affiche_sql(this)"><summary>Script SQL de création</summary>'
        + '<div id="modele-sql"></div></details>';
    t += '</div>';

    cache($('posts'));
    maj($('posts'), t);
    apparait($('posts'));
    // Les mesures du texte supposent la police chargée
    var dessin = function () { $('diagramme') && ($('diagramme').innerHTML = dessine_modele(m)); active_liens(); };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(dessin); else dessin();
}

// Colonnes d'une table qui appartiennent à une clé étrangère
function colonnes_etrangeres(table) {
    var fk = {};
    table.cles.forEach(function (k) { k.colonnes.forEach(function (c) { fk[c] = true; }); });
    return fk;
}

function liste_colonnes(cols) {
    return cols.map(echappe).join(', ');
}

//----------------------------------------------------------------
// Schéma relationnel, dans la notation du cours :
// Table(clé soulignée, #clé_étrangère, autre)
//----------------------------------------------------------------
function schema_relationnel(m) {
    var t = '<ul class="schema-relationnel">';
    m.tables.forEach(function (table) {
        var fk = colonnes_etrangeres(table);
        var cols = table.colonnes.map(function (c) {
            var n = (fk[c.nom] ? '#' : '') + echappe(c.nom);
            return c.pk > 0 ? '<span class="cle-primaire">' + n + '</span>' : n;
        });
        t += '<li><strong>' + echappe(table.nom) + '</strong> (' + cols.join(', ') + ')';
        if (table.cles.length > 0) {
            t += '<ul class="references">';
            table.cles.forEach(function (k) {
                var de = k.colonnes.length > 1 ? '(' + liste_colonnes(k.colonnes) + ')' : echappe(k.colonnes[0]);
                t += '<li>#' + de + ' → ' + echappe(k.table) + '(' + liste_colonnes(k.references) + ')</li>';
            });
            t += '</ul>';
        }
        t += '</li>';
    });
    t += '</ul>';
    if (m.vues.length > 0) t += '<p><strong>Vues :</strong> ' + m.vues.map(echappe).join(', ') + '</p>';
    return t;
}

//----------------------------------------------------------------
// Structure des tables : colonnes, types, clés, NOT NULL
//----------------------------------------------------------------
function structure_tables(m) {
    var t = '';
    m.tables.forEach(function (table) {
        var nb_pk = table.colonnes.filter(function (c) { return c.pk > 0; }).length;
        t += '<h4>' + echappe(table.nom) + '</h4>';
        t += '<table class="resultat"><thead><tr><th>Colonne</th><th>Type</th><th>Clé primaire</th>'
            + '<th>Clé étrangère</th><th>Non nul</th></tr></thead><tbody>';
        table.colonnes.forEach(function (c) {
            var refs = [];
            table.cles.forEach(function (k) {
                var i = k.colonnes.indexOf(c.nom);
                if (i >= 0) refs.push(echappe(k.table) + '.' + echappe(k.references[i]));
            });
            t += '<tr><td>' + echappe(c.nom) + '</td><td>' + echappe(c.type || '') + '</td>'
                + '<td>' + (c.pk > 0 ? 'oui' + (nb_pk > 1 ? ' (' + c.pk + '/' + nb_pk + ')' : '') : '') + '</td>'
                + '<td>' + (refs.length ? '→ ' + refs.join(', ') : '') + '</td>'
                + '<td>' + (c.non_nul ? 'oui' : '') + '</td></tr>';
        });
        t += '</tbody></table>';
    });
    return t;
}

// Script SQL, affiché à la première ouverture du volet (CodeMirror mesure mal un
// élément masqué)
function affiche_sql(volet) {
    var cible = $('modele-sql');
    if (!volet.open || !cible || cible.dataset.fait || !modele_courant) return;
    cible.dataset.fait = '1';
    var texte = modele_courant.sql.join('\n\n');
    if (typeof CodeMirror == 'undefined') {
        cible.innerHTML = '<pre>' + echappe(texte) + '</pre>';
        return;
    }
    CodeMirror(cible, {value: texte, mode: 'text/x-sqlite', readOnly: true, lineNumbers: true,
                       lineWrapping: true, viewportMargin: Infinity});
}

function bascule_ajustement(bouton) {
    var d = $('diagramme');
    var ajuste = d.classList.toggle('ajuste');
    bouton.textContent = ajuste ? '🔍 Taille réelle' : '⤢ Ajuster à la largeur';
}

//----------------------------------------------------------------
// Diagramme
//----------------------------------------------------------------

var DIAG = {police: '"Atkinson Hyperlegible Next", "Segoe UI", sans-serif', entete: 30, ligne: 22,
            marge: 24, ecart_x: 110, ecart_y: 30, max_par_colonne: 7};

var mesureur = null;
function largeur_texte(texte, poids, taille) {
    if (!mesureur) mesureur = document.createElement('canvas').getContext('2d');
    mesureur.font = poids + ' ' + taille + 'px ' + DIAG.police;
    return mesureur.measureText(texte).width;
}

// Répartit les tables en colonnes : une table est placée à droite des tables qu'elle
// référence. Dans un cycle (Country → City → Province → Country), le lien vers la table
// la moins référencée est ignoré pour le placement (il reste dessiné).
function place_tables(m) {
    var noms = m.tables.map(function (t) { return t.nom; });
    var index = {};
    noms.forEach(function (n, i) { index[n.toLowerCase()] = i; });
    var refs = noms.map(function () { return 0; });
    var parents = noms.map(function () { return []; });
    m.tables.forEach(function (t, i) {
        t.cles.forEach(function (k) {
            var p = index[k.table.toLowerCase()];
            if (p === undefined || p == i) return;
            refs[p]++;
            if (parents[i].indexOf(p) < 0) parents[i].push(p);
        });
    });
    // Liens retenus pour le placement : vers une table au moins aussi référencée
    var garde = parents.map(function (ps, i) {
        return ps.filter(function (p) { return refs[p] >= refs[i]; });
    });
    // Niveaux par plus long chemin, en coupant les cycles restants
    var niveau = noms.map(function () { return -1; });
    var en_cours = noms.map(function () { return false; });
    function calcule(i) {
        if (niveau[i] >= 0) return niveau[i];
        en_cours[i] = true;
        var n = 0;
        garde[i].forEach(function (p) {
            if (en_cours[p]) return;
            n = Math.max(n, calcule(p) + 1);
        });
        en_cours[i] = false;
        niveau[i] = n;
        return n;
    }
    noms.forEach(function (_, i) { calcule(i); });

    // Colonnes, triées : au niveau 0 les tables les plus référencées d'abord, ensuite
    // selon la position moyenne des tables référencées
    var colonnes = [];
    noms.forEach(function (_, i) { (colonnes[niveau[i]] = colonnes[niveau[i]] || []).push(i); });
    colonnes = colonnes.filter(function (c) { return c; });
    var rang = {};
    var finales = [];
    colonnes.forEach(function (col, c) {
        col.sort(function (a, b) {
            if (c == 0) return refs[b] - refs[a] || noms[a].localeCompare(noms[b]);
            return moyenne(a) - moyenne(b) || noms[a].localeCompare(noms[b]);
        });
        // Une colonne trop haute est coupée en plusieurs
        for (var d = 0; d < col.length; d += DIAG.max_par_colonne) {
            var morceau = col.slice(d, d + DIAG.max_par_colonne);
            morceau.forEach(function (i, k) { rang[i] = k; });
            finales.push(morceau);
        }
    });
    function moyenne(i) {
        var ps = parents[i].filter(function (p) { return rang[p] !== undefined; });
        if (ps.length == 0) return 1e9;
        return ps.reduce(function (s, p) { return s + rang[p]; }, 0) / ps.length;
    }
    return finales;
}

function dessine_modele(m) {
    // Dimensions des boîtes
    var boites = m.tables.map(function (t) {
        var fk = colonnes_etrangeres(t);
        var l = largeur_texte(t.nom, 600, 15) + 28;
        t.colonnes.forEach(function (c) {
            var w = largeur_texte((fk[c.nom] ? '#' : '') + c.nom, 400, 13)
                  + largeur_texte(c.type || '', 400, 11) + 40;
            if (w > l) l = w;
        });
        return {table: t, fk: fk, l: Math.max(140, Math.ceil(l)),
                h: DIAG.entete + t.colonnes.length * DIAG.ligne + 8, x: 0, y: 0};
    });
    // Placement
    var colonnes = place_tables(m);
    var x = DIAG.marge, hauteurs = [];
    colonnes.forEach(function (col) {
        var lmax = 0, y = 0;
        col.forEach(function (i) { lmax = Math.max(lmax, boites[i].l); });
        col.forEach(function (i) {
            boites[i].x = x + (lmax - boites[i].l) / 2;
            boites[i].y = y;
            y += boites[i].h + DIAG.ecart_y;
        });
        hauteurs.push(y - DIAG.ecart_y);
        x += lmax + DIAG.ecart_x;
    });
    var largeur = x - DIAG.ecart_x + DIAG.marge + 40;
    var hmax = Math.max.apply(null, hauteurs);
    // Colonnes centrées verticalement
    colonnes.forEach(function (col, c) {
        var decalage = DIAG.marge + (hmax - hauteurs[c]) / 2;
        col.forEach(function (i) { boites[i].y += decalage; });
    });
    var hauteur = hmax + 2 * DIAG.marge;

    var index = {};
    boites.forEach(function (b, i) { index[b.table.nom.toLowerCase()] = i; });
    function y_colonne(b, nom) {
        var k = 0;
        b.table.colonnes.forEach(function (c, j) { if (c.nom.toLowerCase() == String(nom).toLowerCase()) k = j; });
        return b.y + DIAG.entete + k * DIAG.ligne + DIAG.ligne / 2 + 2;
    }

    var s = '<svg xmlns="http://www.w3.org/2000/svg" class="diagramme-svg" width="' + Math.ceil(largeur)
        + '" height="' + Math.ceil(hauteur) + '" viewBox="0 0 ' + Math.ceil(largeur) + ' ' + Math.ceil(hauteur)
        + '" role="img" aria-label="Diagramme des tables et de leurs clés étrangères">';
    s += '<defs><marker id="fleche" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" '
        + 'orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" class="fleche"/></marker></defs>';

    // Liens (sous les boîtes)
    boites.forEach(function (b) {
        b.table.cles.forEach(function (k) {
            var p = index[k.table.toLowerCase()];
            if (p === undefined) return; // table référencée absente
            var c = boites[p];
            var y1 = y_colonne(b, k.colonnes[0]);
            var y2 = y_colonne(c, k.references[0]);
            var x1, x2, d;
            if (c === b) { // la table se référence elle-même
                x1 = b.x + b.l; x2 = b.x + b.l;
                d = 'M' + x1 + ',' + y1 + ' C' + (x1 + 45) + ',' + y1 + ' ' + (x2 + 45) + ',' + y2 + ' ' + x2 + ',' + y2;
            } else if (Math.abs(c.x - b.x) < 1) { // même colonne : boucle par la droite
                x1 = b.x + b.l; x2 = c.x + c.l;
                var dx = 40 + Math.abs(y2 - y1) / 6;
                d = 'M' + x1 + ',' + y1 + ' C' + (x1 + dx) + ',' + y1 + ' ' + (x2 + dx) + ',' + y2 + ' ' + x2 + ',' + y2;
            } else {
                var gauche = c.x < b.x;
                x1 = gauche ? b.x : b.x + b.l;
                x2 = gauche ? c.x + c.l : c.x;
                var e = Math.max(30, Math.abs(x2 - x1) / 2) * (gauche ? -1 : 1);
                d = 'M' + x1 + ',' + y1 + ' C' + (x1 + e) + ',' + y1 + ' ' + (x2 - e) + ',' + y2 + ' ' + x2 + ',' + y2;
            }
            s += '<path class="lien" data-de="' + echappe(b.table.nom).replace(/"/g, '&quot;') + '" data-vers="'
                + echappe(c.table.nom).replace(/"/g, '&quot;') + '" d="' + d + '" marker-end="url(#fleche)">'
                + '<title>' + echappe(b.table.nom) + '(' + liste_colonnes(k.colonnes) + ') → '
                + echappe(c.table.nom) + '(' + liste_colonnes(k.references) + ')</title></path>';
        });
    });

    // Boîtes
    boites.forEach(function (b) {
        var nom = echappe(b.table.nom);
        s += '<g class="boite" data-table="' + nom.replace(/"/g, '&quot;') + '">';
        s += '<rect class="cadre" x="' + b.x + '" y="' + b.y + '" width="' + b.l + '" height="' + b.h + '" rx="8"/>';
        s += '<path class="entete" d="M' + b.x + ',' + (b.y + DIAG.entete) + ' v-' + (DIAG.entete - 8)
            + ' a8,8 0 0 1 8,-8 h' + (b.l - 16) + ' a8,8 0 0 1 8,8 v' + (DIAG.entete - 8) + ' z"/>';
        s += '<text class="nom-table" x="' + (b.x + b.l / 2) + '" y="' + (b.y + 20) + '">' + nom + '</text>';
        b.table.colonnes.forEach(function (c, j) {
            var y = b.y + DIAG.entete + j * DIAG.ligne + 16;
            s += '<text class="colonne' + (c.pk > 0 ? ' pk' : '') + '" x="' + (b.x + 10) + '" y="' + y + '">'
                + (b.fk[c.nom] ? '<tspan class="diese">#</tspan>' : '') + echappe(c.nom) + '</text>';
            s += '<text class="type" x="' + (b.x + b.l - 10) + '" y="' + y + '">' + echappe(c.type || '') + '</text>';
        });
        s += '</g>';
    });
    s += '</svg>';
    return s;
}

// Survol d'une table : ses liens sont mis en évidence
function active_liens() {
    var svg = document.querySelector('#diagramme svg');
    if (!svg) return;
    svg.querySelectorAll('.boite').forEach(function (g) {
        var nom = g.getAttribute('data-table');
        g.addEventListener('mouseenter', function () {
            svg.classList.add('survol');
            svg.querySelectorAll('.lien').forEach(function (l) {
                if (l.getAttribute('data-de') == nom || l.getAttribute('data-vers') == nom) l.classList.add('fort');
            });
            g.classList.add('fort');
        });
        g.addEventListener('mouseleave', function () {
            svg.classList.remove('survol');
            svg.querySelectorAll('.fort').forEach(function (e) { e.classList.remove('fort'); });
        });
    });
}
