// Test de la coloration SQL (js/coloration.js) sans navigateur : node tests/coloration.js
// Le vrai analyseur de CodeMirror est chargé, avec un minimum de faux éléments de page.
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const racine = path.join(__dirname, '..');
const rien = () => {};
const element = () => ({style: {}, appendChild: rien, setAttribute: rien, addEventListener: rien, removeEventListener: rien,
    getBoundingClientRect: () => ({}), childNodes: [], firstChild: null, parentNode: null, classList: {add: rien, remove: rien},
    getElementsByTagName: () => [], offsetWidth: 0, offsetHeight: 0});
// echappe() est celle de l'application (bd_base.js)
const source_echappe = fs.readFileSync(path.join(racine, 'js/bd_base.js'), 'utf8').match(/function echappe\(texte\) \{[^}]*\}/)[0];
const echappe = vm.runInNewContext('(' + source_echappe + ')');

function charge(avecCodeMirror) {
    const bac = {navigator: {userAgent: 'node', platform: 'node', vendor: ''}, console, setTimeout, clearTimeout, echappe: echappe,
        document: {createElement: element, createTextNode: element, createRange: undefined, body: element(), documentElement: element(),
                   documentMode: undefined, addEventListener: rien}};
    bac.window = bac; bac.addEventListener = rien; bac.removeEventListener = rien; bac.getComputedStyle = () => ({});
    vm.createContext(bac);
    if (avecCodeMirror) {
        bac.module = {exports: {}}; bac.exports = bac.module.exports;
        vm.runInContext(fs.readFileSync(path.join(racine, 'js/codemirror/codemirror.js'), 'utf8'), bac);
        bac.CodeMirror = bac.module.exports; // sql.js s'enregistre auprès de CodeMirror (format CommonJS)
        bac.require = () => bac.CodeMirror; bac.module = {exports: {}}; bac.exports = bac.module.exports;
        vm.runInContext(fs.readFileSync(path.join(racine, 'js/codemirror/sql.js'), 'utf8'), bac);
    }
    vm.runInContext(fs.readFileSync(path.join(racine, 'js/coloration.js'), 'utf8'), bac);
    return bac;
}

let echecs = 0;
function verifie(nom, condition, detail) {
    console.log((condition ? '  ok   ' : '  ECHEC ') + nom + (condition ? '' : ' -> ' + detail));
    if (!condition) echecs++;
}
// Texte affiché : le HTML sans ses balises, entités décodées
const texte = html => html.replace(/<[^>]*>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const classes = html => [...html.matchAll(/<span class="([^"]*)">([^<]*)<\/span>/g)].map(m => m[1] + ':' + m[2]);

const cm = charge(true);
const colore = cm.colore_sql;

let h = colore("SELECT nom, COUNT(*) FROM etudiants WHERE nom = 'Dupont' AND age >= 18 -- adultes");
const c = classes(h);
verifie('mots-clés', c.includes('cm-keyword:SELECT') && c.includes('cm-keyword:FROM') && c.includes('cm-keyword:WHERE'), c.join(' | '));
verifie('chaîne', c.includes("cm-string:'Dupont'"), c.join(' | '));
verifie('nombre', c.includes('cm-number:18'), c.join(' | '));
verifie('commentaire', c.some(x => x.startsWith('cm-comment:-- adultes')), c.join(' | '));

// Le texte est conservé tel quel, espaces et retours à la ligne compris
for (const requete of ["select 1", "select *\n  from t\nwhere a = 'x';", "\n\nselect 1\n\n", "select\ttab,  deux  espaces", "", "SELECT 'é', \"Pays\" FROM [t t] -- été"]) {
    verifie('texte conservé : ' + JSON.stringify(requete).slice(0, 40), texte(colore(requete)) === requete, JSON.stringify(texte(colore(requete))));
}
// Un commentaire /* ... */ sur plusieurs lignes reste coloré à la ligne suivante
const multi = classes(colore("select 1 /* début\nsuite */ from t"));
verifie('commentaire sur plusieurs lignes', multi.some(x => x.startsWith('cm-comment:') && x.includes('début')) && multi.some(x => x.startsWith('cm-comment:') && x.includes('suite')), multi.join(' | '));

// Rien de ce que contient la requête ne devient du HTML
for (const piege of ["select '<img src=x onerror=alert(1)>'", "select \"</pre><script>alert(1)</script>\"", "select a < b and c > d & e", "-- <b>gras</b>", "select '&lt;'"]) {
    h = colore(piege);
    verifie('échappé : ' + piege.slice(0, 40), !/<(img|script|b|\/pre)\b/i.test(h) && texte(h) === piege, h);
}

// Sans CodeMirror : le texte est échappé, sans coloration
const sans = charge(false);
verifie('sans CodeMirror : texte échappé', sans.colore_sql("select '<b>'") === echappe("select '<b>'"), sans.colore_sql("select '<b>'"));

process.exit(echecs ? 1 : 0);
