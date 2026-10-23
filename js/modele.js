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
    t += '<button type="button" class="btn secondaire" onclick="reinitialise_base(' + attr_js(nom) + ')" '
        + 'title="Efface vos modifications : la base redevient comme au départ">↩️ Revenir à la base d\'origine</button>';
    t += '</div>';
    t += '<p class="astuce">Modèle lu sur votre copie de la base : il tient compte de vos CREATE, ALTER et DROP. '
        + m.tables.length + ' table(s), ' + nb_cles + ' clé(s) étrangère(s).</p>';
    if (m.tables.length == 0 && !m.origine) {
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
        + '<span class="separateur">·</span>flèche : de la clé étrangère vers la table qu\'elle référence '
        + '(une flèche par colonne pour une clé étrangère composée)</p>';

    if (m.origine) {
        t += '<p class="message">Le schéma de votre copie a changé par rapport à la base d\'origine. '
            + 'Choisissez ce que vous voulez voir :</p>';
        t += '<div class="actions" id="choix-vue">'
            + '<button type="button" class="btn petit" data-vue="actuel" onclick="vue_modele(\'actuel\')">Schéma actuel</button>'
            + '<button type="button" class="btn secondaire petit" data-vue="origine" onclick="vue_modele(\'origine\')">Schéma d\'origine</button>'
            + '<button type="button" class="btn secondaire petit" data-vue="deux" onclick="vue_modele(\'deux\')">Origine et actuel côte à côte</button>'
            + '</div>';
    }
    t += '<div id="vue-modele"></div>';

    t += '<details class="volet"><summary>Structure des tables (schéma actuel)</summary>' + structure_tables(m) + '</details>';
    t += '<details class="volet" id="volet-sql" ontoggle="affiche_sql(this)"><summary>Script SQL de création</summary>'
        + '<div id="modele-sql"></div></details>';
    t += '</div>';

    cache($('posts'));
    maj($('posts'), t);
    apparait($('posts'));
    // Les mesures du texte supposent la police chargée
    vue_modele('actuel');
}

// Diagramme et schéma relationnel du modèle : 'actuel' (la copie de l'étudiant), 'origine'
// (la base d'origine) ou 'deux' (les deux côte à côte)
function vue_modele(vue) {
    var m = modele_courant, cible = $('vue-modele');
    if (!m || !cible) return;
    if (!m.origine) vue = 'actuel';
    document.querySelectorAll('#choix-vue button').forEach(function (b) {
        b.classList.toggle('secondaire', b.getAttribute('data-vue') != vue);
    });
    var t = '';
    if (vue == 'deux') {
        t += '<div class="avant-apres-grille">';
        [['Origine', m.origine], ['Actuel', m]].forEach(function (v, i) {
            t += '<div><h3>' + v[0] + '</h3><div class="diagramme ajuste" id="diagramme-' + i + '"></div>'
                + schema_relationnel(v[1]) + '</div>';
        });
        t += '</div>';
    } else {
        var x = vue == 'origine' ? m.origine : m;
        t += '<h3>Diagramme' + (vue == 'origine' ? ' d\'origine' : '') + '</h3>';
        t += '<div class="actions"><button type="button" class="btn secondaire petit" onclick="bascule_ajustement(this)">'
            + '⤢ Ajuster à la largeur</button></div>';
        t += '<div class="diagramme" id="diagramme"></div>';
        t += '<h3>Schéma relationnel' + (vue == 'origine' ? ' d\'origine' : '') + '</h3>' + schema_relationnel(x);
    }
    cible.innerHTML = t;
    var dessin = function () {
        if (vue == 'deux') {
            $('diagramme-0').innerHTML = dessine_modele(m.origine);
            $('diagramme-1').innerHTML = dessine_modele(m);
        } else {
            $('diagramme').innerHTML = dessine_modele(vue == 'origine' ? m.origine : m);
        }
        active_liens();
    };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(dessin); else dessin();
}

// Retour à la base d'origine : la copie est remplacée par l'original (requêtes mémorisées conservées)
function reinitialise_base(nom) {
    if (!confirm('Revenir à la base d\'origine ' + nom + ' ? Toutes vos modifications (tables créées, '
        + 'lignes ajoutées ou supprimées...) seront perdues.')) return;
    appel('post', {Soumettre: 'reinitialiser', csrf: csrf_token, nom_base: nom},
        function () {
            if ($('SaisieRequete')) {
                maj($('results'), "<p class='statut ok'>↩️ La base " + echappe(nom) + " est revenue à son état d'origine</p>");
            } else {
                modele(nom);
            }
        },
        function (trs) {
            if (session_perdue(trs)) return;
            alert("Impossible de réinitialiser la base.");
        });
}

// Requête qui a changé les tables (CREATE, ALTER, DROP) : le serveur joint au résultat un
// <div class="avant-apres"> portant les tables avant et après (JSON). On y dessine, côte à
// côte, le diagramme avant et le diagramme après. Appelé à chaque affichage d'un résultat.
function dessine_avant_apres(element) {
    element.querySelectorAll('.avant-apres:not([data-fait])').forEach(function (div) {
        var avant, apres;
        try {
            avant = {tables: JSON.parse(div.getAttribute('data-avant'))};
            apres = {tables: JSON.parse(div.getAttribute('data-apres'))};
        } catch (e) {
            return;
        }
        div.setAttribute('data-fait', '1');
        div.innerHTML = '<h3>Effet sur le schéma</h3><div class="avant-apres-grille">'
            + '<div><h4>Avant</h4><div class="diagramme ajuste"></div></div>'
            + '<div><h4>Après</h4><div class="diagramme ajuste"></div></div></div>';
        var cases = div.querySelectorAll('.diagramme');
        var dessin = function () {
            cases[0].innerHTML = avant.tables.length ? dessine_modele(avant) : '<p class="message">Aucune table.</p>';
            cases[1].innerHTML = apres.tables.length ? dessine_modele(apres) : '<p class="message">Aucune table.</p>';
        };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(dessin); else dessin();
    });
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
            marge: 24, ecart_x: 110, ecart_y: 30, piste: 16, exhaustif_max: 4000, departs: 20};

var mesureur = null;
function largeur_texte(texte, poids, taille) {
    if (!mesureur) mesureur = document.createElement('canvas').getContext('2d');
    mesureur.font = poids + ' ' + taille + 'px ' + DIAG.police;
    return mesureur.measureText(texte).width;
}

// Disposition des tables en couches, comme dans un diagramme de flux : une table est placée à
// droite des tables qu'elle référence. Un lien qui saute des couches reçoit un point de passage
// (« relais ») dans chaque couche traversée : il passe entre les tables au lieu de les traverser.
// L'ordre des tables dans chaque couche est cherché pour réduire les croisements de liens, en
// tenant compte de la ligne de colonne où chaque lien s'attache. Le calcul est déterministe
// (le diagramme ne doit pas changer d'aspect quand on le redessine) :
//  - petites bases : recherche exhaustive de l'ordre ;
//  - grandes bases : barycentres, échanges de voisins et départs aléatoires d'une graine fixe.
// Dans un cycle (Country → City → Province → Country), le lien vers la table la moins référencée
// remonte vers la gauche : il est dessiné, mais ne compte pas pour les couches.
function disposition_tables(m, boites) {
    var n = m.tables.length;
    if (n == 0) return {couches: [], chaines: {}, meme_couche: {}, col_x: [], col_l: [], largeur: 0, hauteur: 0};
    var index = {};
    m.tables.forEach(function (t, i) { index[t.nom.toLowerCase()] = i; });

    // Liens entre tables (enfant → table référencée), sans doublon ni lien d'une table sur elle-même
    var vus = {}, aretes = [];
    m.tables.forEach(function (t, i) {
        t.cles.forEach(function (k) {
            var p = index[k.table.toLowerCase()];
            if (p === undefined || p == i || vus[i + '>' + p]) return;
            vus[i + '>' + p] = true;
            aretes.push({enfant: i, parent: p});
        });
    });

    // Couches : plus long chemin vers les tables référencées. Les tables les plus référencées sont
    // visitées d'abord, pour que le lien coupé dans un cycle soit celui qui va vers la moins référencée.
    var refs = [], parents = [], niveau = [], en_cours = [], i;
    for (i = 0; i < n; i++) { refs.push(0); parents.push([]); niveau.push(-1); en_cours.push(false); }
    aretes.forEach(function (a) { refs[a.parent]++; parents[a.enfant].push(a.parent); });
    function calcule(t) {
        if (niveau[t] >= 0) return niveau[t];
        en_cours[t] = true;
        var v = 0;
        parents[t].forEach(function (p) { if (!en_cours[p]) v = Math.max(v, calcule(p) + 1); });
        en_cours[t] = false;
        niveau[t] = v;
        return v;
    }
    var visite = [];
    for (i = 0; i < n; i++) visite.push(i);
    visite.sort(function (a, b) { return refs[b] - refs[a] || a - b; });
    visite.forEach(calcule);

    // Nœuds : les tables (leurs boîtes), puis les relais
    var noeuds = boites.slice();
    for (i = 0; i < n; i++) boites[i].couche = niveau[i];
    var liens = [], chaines = {}, meme_couche = {};
    aretes.forEach(function (a) {
        var ce = niveau[a.enfant], cp = niveau[a.parent];
        if (ce == cp) { meme_couche[a.enfant + '>' + a.parent] = true; return; }
        var pas = ce > cp ? -1 : 1, chaine = [a.enfant];
        for (var c = ce + pas; c != cp; c += pas) {
            noeuds.push({relais: true, couche: c, l: 0, h: DIAG.piste, x: 0, y: 0});
            chaine.push(noeuds.length - 1);
        }
        chaine.push(a.parent);
        for (var z = 0; z + 1 < chaine.length; z++) liens.push({a: chaine[z], b: chaine[z + 1]});
        chaines[a.enfant + '>' + a.parent] = chaine;
    });
    var nb_couches = 0;
    noeuds.forEach(function (x) { nb_couches = Math.max(nb_couches, x.couche + 1); });
    var couches = [];
    for (i = 0; i < nb_couches; i++) couches.push([]);
    noeuds.forEach(function (x, id) { couches[x.couche].push(id); });
    var voisins = noeuds.map(function () { return []; });
    liens.forEach(function (e) { voisins[e.a].push(e.b); voisins[e.b].push(e.a); });

    // Segments entre couches consécutives, un par colonne de clé étrangère : décalage d'attache
    // dans la boîte pour une table, milieu de la piste pour un relais
    function decalage(t, colonne) {
        var k = 0;
        m.tables[t].colonnes.forEach(function (c, j) { if (c.nom.toLowerCase() == String(colonne).toLowerCase()) k = j; });
        return DIAG.entete + k * DIAG.ligne + DIAG.ligne / 2 + 2;
    }
    var groupes = []; // groupes[c] : segments entre les couches c et c+1
    for (i = 0; i < nb_couches; i++) groupes.push([]);
    m.tables.forEach(function (t, i) {
        t.cles.forEach(function (k) {
            var p = index[k.table.toLowerCase()];
            var chaine = p === undefined ? null : chaines[i + '>' + p];
            if (!chaine) return;
            k.colonnes.forEach(function (colonne, q) {
                var ref = k.references[q] === undefined ? k.references[0] : k.references[q];
                for (var z = 0; z + 1 < chaine.length; z++) {
                    var a = chaine[z], b = chaine[z + 1];
                    var oa = z == 0 ? decalage(i, colonne) : DIAG.piste / 2;
                    var ob = z + 2 == chaine.length ? decalage(p, ref) : DIAG.piste / 2;
                    if (noeuds[a].couche < noeuds[b].couche) groupes[noeuds[a].couche].push({bas: a, haut: b, ob: oa, oh: ob});
                    else groupes[noeuds[b].couche].push({bas: b, haut: a, ob: ob, oh: oa});
                }
            });
        });
    });

    // Ordonnancement
    var ordre = [];
    function maj() { couches.forEach(function (c) { c.forEach(function (id, k) { ordre[id] = k; }); }); }
    function positions() { // ordonnées de l'empilement des couches, centrées sur la plus haute
        var y = [], hs = [];
        couches.forEach(function (c) {
            var v = 0;
            c.forEach(function (id) { y[id] = v; v += noeuds[id].h + DIAG.ecart_y; });
            hs.push(v - DIAG.ecart_y);
        });
        var hm = Math.max.apply(null, hs);
        couches.forEach(function (c, k) { c.forEach(function (id) { y[id] += (hm - hs[k]) / 2; }); });
        return y;
    }
    // Coût : les croisements comptent d'abord (deux liens entre les mêmes couches se croisent si
    // leurs extrémités sont dans l'ordre inverse), puis la somme des dénivelés, pour des liens droits
    function cout() {
        var y = positions(), croisements = 0, deniveles = 0;
        groupes.forEach(function (g) {
            var yb = [], yh = [];
            g.forEach(function (s, j) { yb[j] = y[s.bas] + s.ob; yh[j] = y[s.haut] + s.oh; deniveles += Math.abs(yb[j] - yh[j]); });
            for (var j = 0; j < g.length; j++) for (var k = j + 1; k < g.length; k++)
                if ((yb[j] - yb[k]) * (yh[j] - yh[k]) < -1e-6) croisements++;
        });
        return croisements * 1e7 + deniveles;
    }
    function barycentres(passes) {
        for (var it = 0; it < passes; it++) {
            var descend = it % 2 == 0, suite = [];
            for (var c = 0; c < nb_couches; c++) suite.push(c);
            if (descend) suite.shift(); else { suite.pop(); suite.reverse(); }
            suite.forEach(function (c) {
                var ref = c + (descend ? -1 : 1), b = {};
                couches[c].forEach(function (id) {
                    var v = voisins[id].filter(function (w) { return noeuds[w].couche == ref; });
                    b[id] = v.length ? v.reduce(function (s, w) { return s + ordre[w]; }, 0) / v.length : ordre[id];
                });
                couches[c].sort(function (x, y) { return b[x] - b[y] || x - y; });
                maj();
            });
        }
    }
    function transposition() { // échange de voisins tant que le coût baisse
        var cur = cout(), mieux = true;
        while (mieux) {
            mieux = false;
            for (var c = 0; c < nb_couches; c++) for (var k = 0; k + 1 < couches[c].length; k++) {
                var tmp = couches[c][k]; couches[c][k] = couches[c][k + 1]; couches[c][k + 1] = tmp; maj();
                var t = cout();
                if (t < cur - 1e-6) { cur = t; mieux = true; }
                else { tmp = couches[c][k]; couches[c][k] = couches[c][k + 1]; couches[c][k + 1] = tmp; maj(); }
            }
        }
        return cur;
    }
    var meilleur = null, cout_min = Infinity;
    function retient() {
        var t = cout();
        if (t < cout_min - 1e-6) { cout_min = t; meilleur = couches.map(function (c) { return c.slice(); }); }
    }
    function restaure(o) { o.forEach(function (c, k) { couches[k] = c.slice(); }); maj(); }

    maj();
    retient();
    barycentres(30);
    retient();
    restaure(meilleur); transposition(); retient();
    var graine = 12345;
    function alea() { graine = (graine * 1103515245 + 12345) & 0x7fffffff; return graine / 0x7fffffff; }
    for (var d = 0; d < DIAG.departs; d++) {
        couches.forEach(function (c) {
            for (var j = c.length - 1; j > 0; j--) { var r = Math.floor(alea() * (j + 1)), tmp = c[j]; c[j] = c[r]; c[r] = tmp; }
        });
        maj(); barycentres(10); transposition(); retient();
    }
    // Petites bases : recherche exhaustive de l'ordre dans chaque couche
    var combinaisons = 1;
    couches.forEach(function (c) { for (var j = 2; j <= c.length; j++) combinaisons *= j; });
    if (combinaisons <= DIAG.exhaustif_max) {
        var base = meilleur.map(function (c) { return c.slice(); });
        var permuter = function (k) {
            if (k == nb_couches) { maj(); retient(); return; }
            var liste = base[k].slice();
            (function genere(debut) {
                if (debut == liste.length) { couches[k] = liste.slice(); permuter(k + 1); return; }
                for (var j = debut; j < liste.length; j++) {
                    var tmp = liste[debut]; liste[debut] = liste[j]; liste[j] = tmp;
                    genere(debut + 1);
                    tmp = liste[debut]; liste[debut] = liste[j]; liste[j] = tmp;
                }
            })(0);
        };
        permuter(0);
    }
    restaure(meilleur);

    // Coordonnées : une colonne par couche, tables centrées dans la colonne
    var x = DIAG.marge, col_x = [], col_l = [], hauteurs = [];
    couches.forEach(function (c) {
        var lmax = 0, y = 0;
        c.forEach(function (id) { lmax = Math.max(lmax, noeuds[id].l); });
        c.forEach(function (id) {
            noeuds[id].x = x + (lmax - noeuds[id].l) / 2;
            noeuds[id].y = y;
            y += noeuds[id].h + DIAG.ecart_y;
        });
        hauteurs.push(y - DIAG.ecart_y);
        col_x.push(x); col_l.push(lmax);
        x += Math.max(lmax, 1) + DIAG.ecart_x;
    });
    var hmax = Math.max.apply(null, hauteurs);
    couches.forEach(function (c, k) {
        c.forEach(function (id) { noeuds[id].y += DIAG.marge + (hmax - hauteurs[k]) / 2; });
    });
    return {noeuds: noeuds, couches: couches, chaines: chaines, meme_couche: meme_couche, col_x: col_x, col_l: col_l,
            largeur: x - DIAG.ecart_x + DIAG.marge + 40, hauteur: hmax + 2 * DIAG.marge};
}

// Tracé d'un lien entre deux points : courbe à tangentes horizontales, ou droite si de même hauteur
function segment_lien(x1, y1, x2, y2) {
    if (y1 == y2) return ' L' + x2 + ',' + y2;
    var e = Math.max(30, Math.abs(x2 - x1) / 2) * (x2 >= x1 ? 1 : -1);
    return ' C' + (x1 + e) + ',' + y1 + ' ' + (x2 - e) + ',' + y2 + ' ' + x2 + ',' + y2;
}

// Lien de la colonne (y1) de la table i vers la colonne (y2) de la table p, par les relais de sa chaîne
function chemin_lien(d, i, p, y1, y2) {
    var b = d.noeuds[i], c = d.noeuds[p], x1, x2, e;
    if (i == p) { // la table se référence elle-même
        x1 = b.x + b.l;
        return 'M' + x1 + ',' + y1 + ' C' + (x1 + 45) + ',' + y1 + ' ' + (x1 + 45) + ',' + y2 + ' ' + x1 + ',' + y2;
    }
    var chaine = d.chaines[i + '>' + p];
    if (!chaine) { // même couche : boucle par la droite
        x1 = b.x + b.l; x2 = c.x + c.l;
        e = 40 + Math.abs(y2 - y1) / 6;
        return 'M' + x1 + ',' + y1 + ' C' + (x1 + e) + ',' + y1 + ' ' + (x2 + e) + ',' + y2 + ' ' + x2 + ',' + y2;
    }
    var gauche = c.couche < b.couche; // la table référencée est à gauche (sinon : lien qui remonte un cycle)
    var points = [[gauche ? b.x : b.x + b.l, y1]];
    chaine.slice(1, -1).forEach(function (id) {
        var r = d.noeuds[id], xg = d.col_x[r.couche], xd = xg + d.col_l[r.couche], y = r.y + DIAG.piste / 2;
        points.push(gauche ? [xd, y] : [xg, y], gauche ? [xg, y] : [xd, y]);
    });
    points.push([gauche ? c.x + c.l : c.x, y2]);
    var s = 'M' + points[0][0] + ',' + points[0][1];
    for (var k = 1; k < points.length; k++) s += segment_lien(points[k - 1][0], points[k - 1][1], points[k][0], points[k][1]);
    return s;
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
    // Placement : les boîtes reçoivent leur position (x, y) et leur couche
    var disp = disposition_tables(m, boites);
    var largeur = disp.largeur, hauteur = disp.hauteur;

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

    // Liens (sous les boîtes). Une clé étrangère composée, comme resultats(numep, compet) →
    // epreuves(numep, compet), a une flèche par colonne : chacune part de la colonne de la
    // table qui référence et arrive sur la colonne référencée.
    boites.forEach(function (b, i) {
        b.table.cles.forEach(function (k) {
            var p = index[k.table.toLowerCase()];
            if (p === undefined) return; // table référencée absente
            var c = boites[p];
            k.colonnes.forEach(function (colonne, q) {
                var y1 = y_colonne(b, colonne);
                var y2 = y_colonne(c, k.references[q] === undefined ? k.references[0] : k.references[q]);
                s += '<path class="lien" data-de="' + echappe(b.table.nom).replace(/"/g, '&quot;') + '" data-vers="'
                    + echappe(c.table.nom).replace(/"/g, '&quot;') + '" d="' + chemin_lien(disp, i, p, y1, y2)
                    + '" marker-end="url(#fleche)">'
                    + '<title>' + echappe(b.table.nom) + '(' + liste_colonnes(k.colonnes) + ') → '
                    + echappe(c.table.nom) + '(' + liste_colonnes(k.references) + ')</title></path>';
            });
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
