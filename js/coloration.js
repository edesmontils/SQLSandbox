// Coloration syntaxique d'un texte SQL affiché sans éditeur (requêtes mémorisées...).
// Elle utilise l'analyseur SQL de CodeMirror, celui de l'éditeur : les couleurs sont donc les mêmes.
// Nécessite codemirror.js et sql.js, et echappe() (bd_base.js).

// Retourne le HTML du texte : mots-clés, chaînes, nombres et commentaires dans des <span class="cm-...">.
// À placer dans un élément de classe « cm-s-default », qui porte les couleurs (codemirror.css).
// Sans CodeMirror, le texte est simplement échappé.
function colore_sql(texte) {
    texte = String(texte);
    if (typeof CodeMirror == 'undefined' || !CodeMirror.getMode || !CodeMirror.StringStream) return echappe(texte);
    var mode = CodeMirror.getMode(CodeMirror.defaults, 'text/x-sqlite');
    if (!mode || !mode.token) return echappe(texte);
    var etat = CodeMirror.startState(mode), html = '';
    CodeMirror.splitLines(texte).forEach(function (ligne, i) {
        if (i > 0) html += '\n';
        var flux = new CodeMirror.StringStream(ligne, 4);
        if (!flux.string && mode.blankLine) mode.blankLine(etat);
        while (!flux.eol()) {
            var style = mode.token(flux, etat);
            if (flux.pos <= flux.start) flux.pos = flux.start + 1; // un analyseur doit avancer : garde-fou
            var mot = echappe(flux.current());
            if (style) {
                // « variable-2 » → class="cm-variable-2" ; plusieurs styles possibles, séparés par des espaces
                var classes = style.split(' ').map(function (c) { return 'cm-' + c; }).join(' ');
                html += '<span class="' + classes + '">' + mot + '</span>';
            } else {
                html += mot;
            }
            flux.start = flux.pos;
        }
    });
    return html;
}
