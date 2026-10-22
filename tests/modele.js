// Test du diagramme (js/modele.js) sans navigateur : node tests/modele.js
// Le diagramme doit avoir une flèche par colonne d'une clé étrangère composée.
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
process.exit(echecs ? 1 : 0);
