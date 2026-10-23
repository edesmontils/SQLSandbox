// Test du diagramme (js/modele.js) sans navigateur : node tests/modele.js [dossier de modèles JSON]
// - une flèche par colonne d'une clé étrangère composée ;
// - avec un dossier de modèles : disposition des vraies bases (liens qui traversent des tables,
//   croisements de liens, chevauchements de tables).
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const contexte = {
    // Ce que modele.js attend de bd_base.js et du navigateur
    echappe: s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
    document: {createElement: () => ({getContext: () => ({measureText: t => ({width: 7 * String(t).length})})})},
};
vm.createContext(contexte);
vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'js', 'modele.js'), 'utf8'), contexte);

const col = (nom, pk) => ({nom: nom, type: 'INT', non_nul: false, pk: pk || 0});
const modele = {
    vues: [], sql: [],
    tables: [
        {nom: 'robots', colonnes: [col('nom', 1)], cles: []},
        {nom: 'competitions', colonnes: [col('compet', 1)], cles: []},
        {nom: 'epreuves', colonnes: [col('numep', 1), col('compet', 2)],
         cles: [{colonnes: ['compet'], table: 'competitions', references: ['compet']}]},
        {nom: 'resultats', colonnes: [col('nom', 1), col('numep', 2), col('compet', 3)],
         cles: [{colonnes: ['numep', 'compet'], table: 'epreuves', references: ['numep', 'compet']},
                {colonnes: ['nom'], table: 'robots', references: ['nom']}]},
    ],
};

const svg = contexte.dessine_modele(modele);
const liens = [...svg.matchAll(/<path class="lien" data-de="([^"]*)" data-vers="([^"]*)" d="([^"]*)"/g)]
    .map(m => ({de: m[1], vers: m[2], d: m[3]}));
const vers = (de, a) => liens.filter(l => l.de == de && l.vers == a);

let echecs = 0;
function verifie(nom, condition, detail) {
    console.log((condition ? '  ok   ' : '  ECHEC ') + nom + (condition ? '' : ' -> ' + detail));
    if (!condition) echecs++;
}

verifie('clé composée : une flèche par colonne', vers('resultats', 'epreuves').length == 2, JSON.stringify(liens));
verifie('clé composée : les deux flèches partent de lignes différentes',
        new Set(vers('resultats', 'epreuves').map(l => l.d.split(' ')[0])).size == 2, JSON.stringify(vers('resultats', 'epreuves')));
verifie('clé simple : une seule flèche', vers('resultats', 'robots').length == 1 && vers('epreuves', 'competitions').length == 1);
verifie('total : 4 flèches', liens.length == 4, liens.length);

// Cas limites : le dessin ne produit ni erreur ni valeur invalide
const limites = {
    'aucune table': [],
    'table sans clé': [{nom: 'a', colonnes: [col('x', 1)], cles: []}],
    'table qui se référence': [{nom: 'emp', colonnes: [col('id', 1), col('chef')], cles: [{colonnes: ['chef'], table: 'emp', references: ['id']}]}],
    'cycle entre deux tables': [
        {nom: 'a', colonnes: [col('id', 1), col('b')], cles: [{colonnes: ['b'], table: 'b', references: ['id']}]},
        {nom: 'b', colonnes: [col('id', 1), col('a')], cles: [{colonnes: ['a'], table: 'a', references: ['id']}]}],
    'table référencée absente': [{nom: 'a', colonnes: [col('id', 1)], cles: [{colonnes: ['id'], table: 'fantome', references: ['id']}]}],
    'référence sans colonne': [
        {nom: 'p', colonnes: [col('id', 1)], cles: []},
        {nom: 'e', colonnes: [col('id', 1), col('p')], cles: [{colonnes: ['p'], table: 'P', references: [null]}]}],
};
for (const nom of Object.keys(limites)) {
    let svg = '', erreur = '';
    try { svg = contexte.dessine_modele({tables: limites[nom], vues: [], sql: []}); } catch (e) { erreur = e.message; }
    verifie('cas limite : ' + nom, erreur == '' && !/NaN|undefined|Infinity/.test(svg), erreur || 'valeur invalide dans le SVG');
}

// ---------------------------------------------------------------------------------------------
// Disposition sur les vraies bases : node tests/modele.js <dossier de modèles JSON>
// (tests/run.php écrit un fichier <base>.json par base avec modele_base()). Les mesures se font
// sur le dessin lui-même : points échantillonnés sur chaque tracé, comme le voit l'utilisateur.
// ---------------------------------------------------------------------------------------------
// Échantillonnage IMPAIR (41 points par courbe) : avec un nombre pair, deux courbes en S symétriques
// passent toutes deux par le même point milieu exact, et leur croisement n'est pas détecté.
const ECHANTILLONS = 41;
function points(d) { // tracés M, L et C → polyligne
    const v = d.match(/[MLC]|-?[\d.]+/g);
    const pts = [];
    let i = 0;
    while (i < v.length) {
        const cmd = v[i++];
        if (cmd == 'M') pts.push([+v[i++], +v[i++]]);
        else if (cmd == 'L') pts.push([+v[i++], +v[i++]]);
        else {
            const [x0, y0] = pts[pts.length - 1];
            const [x1, y1, x2, y2, x3, y3] = [+v[i++], +v[i++], +v[i++], +v[i++], +v[i++], +v[i++]];
            for (let t = 1; t <= ECHANTILLONS; t++) {
                const u = t / ECHANTILLONS, w = 1 - u;
                pts.push([w*w*w*x0 + 3*w*w*u*x1 + 3*w*u*u*x2 + u*u*u*x3, w*w*w*y0 + 3*w*w*u*y1 + 3*w*u*u*y2 + u*u*u*y3]);
            }
        }
    }
    return pts;
}
const croise = (a, b, c, d) => {
    const o = (p, q, r) => (q[0]-p[0])*(r[1]-p[1]) - (q[1]-p[1])*(r[0]-p[0]);
    return o(a, b, c) * o(a, b, d) < 0 && o(c, d, a) * o(c, d, b) < 0;
};

function mesure(modele) {
    const svg = contexte.dessine_modele(modele);
    const boites = [...svg.matchAll(/<g class="boite" data-table="([^"]*)"><rect class="cadre" x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)"/g)]
        .map(m => ({nom: m[1], x: +m[2], y: +m[3], l: +m[4], h: +m[5]}));
    const liens = [...svg.matchAll(/<path class="lien" data-de="([^"]*)" data-vers="([^"]*)" d="([^"]*)"/g)]
        .map(m => ({de: m[1], vers: m[2], pts: points(m[3])}));
    // liens qui traversent une table autre que leur origine et leur arrivée
    const traversent = liens.filter(l => boites.some(b => b.nom != l.de && b.nom != l.vers &&
        l.pts.some(p => p[0] > b.x + 1 && p[0] < b.x + b.l - 1 && p[1] > b.y + 1 && p[1] < b.y + b.h - 1)));
    // paires de liens qui se croisent (les extrémités, où les liens d'une même table se rejoignent, sont ignorées)
    let croisements = 0;
    for (let i = 0; i < liens.length; i++) for (let j = i + 1; j < liens.length; j++) {
        const A = liens[i].pts.slice(3, -3), B = liens[j].pts.slice(3, -3);
        let x = false;
        for (let a = 0; a < A.length - 1 && !x; a++) for (let b = 0; b < B.length - 1; b++)
            if (croise(A[a], A[a + 1], B[b], B[b + 1])) { x = true; break; }
        if (x) croisements++;
    }
    const taille = svg.match(/width="(\d+)" height="(\d+)" viewBox/);
    // les boîtes ne se chevauchent pas
    let chevauchements = 0;
    for (let i = 0; i < boites.length; i++) for (let j = i + 1; j < boites.length; j++) {
        const a = boites[i], b = boites[j];
        if (a.x < b.x + b.l && b.x < a.x + a.l && a.y < b.y + b.h && b.y < a.y + a.h) chevauchements++;
    }
    return {tables: boites.length, liens: liens.length, traversent: traversent.map(l => l.de + '→' + l.vers),
            croisements, chevauchements, largeur: +taille[1], hauteur: +taille[2]};
}

// La mesure elle-même : deux courbes en S symétriques (même milieu) qui se croisent sont comptées
{
    const A = points('M274,135 C219,135 219,246 164,246'), B = points('M274,291 C219,291 219,90 164,90');
    let croisent = false;
    for (let a = 0; a < A.length - 1 && !croisent; a++) for (let b = 0; b < B.length - 1; b++)
        if (croise(A[a], A[a + 1], B[b], B[b + 1])) { croisent = true; break; }
    verifie('mesure : deux courbes en S symétriques qui se croisent', croisent, 'croisement non détecté');
}

// Croisements au plus tolérés par base : la disposition ne doit pas se dégrader. Les valeurs sont
// celles de la disposition en couches (avant elle : tp-mondial 364, tp-robots 4, tp-cinema 3).
// tp-em a 2 croisements, le minimum pour cette disposition : les colonnes id_em puis id_p de Fleuve
// visent EspaceMaritime (en bas) puis Pays (en haut), et Fleuve(id_p) coupe Cotoie(id_em).
const BUDGET_CROISEMENTS = {'cours': 0, 'tp-Lutins': 0, 'tp-cinema': 1, 'tp-em': 2, 'tp-robots': 0, 'tp-vols': 0, 'tp-mondial': 255};

const dossier = process.argv[2];
if (dossier) {
    for (const f of fs.readdirSync(dossier).filter(f => f.endsWith('.json')).sort()) {
        const base = f.replace('.json', '');
        const r = mesure(JSON.parse(fs.readFileSync(path.join(dossier, f), 'utf8')));
        const budget = base in BUDGET_CROISEMENTS ? BUDGET_CROISEMENTS[base] : Infinity;
        console.log('  --   ' + base + ' : ' + r.tables + ' tables, ' + r.liens + ' liens, ' + r.croisements + ' croisement(s), '
            + r.traversent.length + ' traversée(s), ' + r.largeur + '×' + r.hauteur);
        verifie(base + ' : aucun lien ne traverse une table', r.traversent.length == 0, r.traversent.join(', '));
        verifie(base + ' : aucune table ne chevauche une autre', r.chevauchements == 0, r.chevauchements);
        verifie(base + ' : au plus ' + budget + ' croisement(s)', r.croisements <= budget, r.croisements);
    }
}

process.exit(echecs ? 1 : 0);
