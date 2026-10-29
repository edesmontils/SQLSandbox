// Mode SQL de SQL Sandbox : le mode « text/x-sqlite » de CodeMirror (js/codemirror/sql.js),
// complété pour le SQLite enseigné ici. À charger après sql.js, avant tout éditeur.
//  - les fonctions (AVG, SUM, MAX, COALESCE, LENGTH...) sont colorées quand elles sont
//    appelées, c'est-à-dire suivies de « ( » : une colonne nommée total ou length reste
//    une colonne ;
//  - les mots-clés récents de SQLite (fenêtrage, RETURNING...) et TRUE / FALSE sont reconnus ;
//  - les commandes du client en ligne de commande de SQLite (mode, log, schema, show...)
//    ne sont plus colorées : ce sont des noms de colonnes ou de tables possibles.
// Le mode ainsi défini remplace « text/x-sqlite » : l'éditeur, le script du modèle et
// colore_sql() (js/coloration.js) en profitent sans changement.
(function () {
    if (typeof CodeMirror == 'undefined' || !CodeMirror.resolveMode) return;
    var origine = CodeMirror.resolveMode('text/x-sqlite');
    if (!origine || origine.name != 'sql') return; // sql.js absent ou modifié : mode d'origine

    function ensemble(texte) {
        var o = {};
        texte.split(' ').forEach(function (m) { if (m) o[m] = true; });
        return o;
    }
    function copie(o) {
        var c = {};
        for (var k in o) c[k] = o[k];
        return c;
    }
    function ajoute(o, texte) {
        var c = copie(o);
        for (var m in ensemble(texte)) c[m] = true;
        return c;
    }

    // https://www.sqlite.org/lang_keywords.html : mots que sql.js ne connaît pas
    var motsCles = 'over partition window rows range groups unbounded preceding following current ' +
        'exclude ties others filter nulls first last do nothing returning generated always materialized';
    // https://www.sqlite.org/lang_corefunc.html, lang_aggfunc.html, lang_datefunc.html,
    // windowfunctions.html, lang_mathfunc.html et json1.html
    var fonctions = ensemble(
        'count avg sum total min max group_concat ' +
        'abs coalesce ifnull nullif iif length lower upper substr substring trim ltrim rtrim round random ' +
        'randomblob typeof instr hex unhex quote unicode char printf format sign unlikely likely likelihood ' +
        'zeroblob last_insert_rowid changes total_changes sqlite_version sqlite_source_id ' +
        'date time datetime julianday unixepoch strftime ' +
        'row_number rank dense_rank percent_rank cume_dist ntile lag lead first_value last_value nth_value ' +
        'ceil ceiling floor trunc mod pow power sqrt exp ln log log2 log10 pi sin cos tan asin acos atan ' +
        'atan2 degrees radians ' +
        'json json_array json_object json_extract json_insert json_replace json_set json_remove json_type ' +
        'json_valid json_quote json_array_length json_group_array json_group_object');

    var base = copie(origine);
    base.keywords = ajoute(origine.keywords, motsCles);
    delete base.keywords.count;          // fonction : colorée seulement quand elle est appelée
    base.atoms = ajoute(origine.atoms, 'true false');
    base.client = {};
    CodeMirror.defineMIME('text/x-sqlite-base', base);

    CodeMirror.defineMode('sqlsandbox', function (config) {
        var sql = CodeMirror.getMode(config, 'text/x-sqlite-base');
        var mode = copie(sql);
        mode.token = function (flux, etat) {
            var style = sql.token(flux, etat);
            // date, time et datetime sont aussi des types (colonnes) : un type n'est pas suivi de « ( »
            // sauf varchar(10)..., d'où la limite à ces trois noms
            if ((!style || (style == 'type' && /^(date|time|datetime)$/i.test(flux.current())))
                    && fonctions.hasOwnProperty(flux.current().toLowerCase())
                    && flux.match(/^\s*\(/, false)) return 'builtin';
            return style;
        };
        return mode;
    });
    CodeMirror.defineMIME('text/x-sqlite', {name: 'sqlsandbox'});
})();
