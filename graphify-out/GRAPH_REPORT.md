# Graph Report - SQLSandbox  (2026-11-11)

## Corpus Check
- 48 files · ~376,889 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 770 nodes · 2063 edges · 43 communities (39 shown, 4 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 67 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `600a1f4b`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]

## God Nodes (most connected - your core abstractions)
1. `addEditorMethods()` - 58 edges
2. `$()` - 37 edges
3. `getLine()` - 36 edges
4. `Pos()` - 30 edges
5. `registerEventHandlers()` - 25 edges
6. `maj()` - 22 edges
7. `elt()` - 22 edges
8. `cmp()` - 22 edges
9. `lst()` - 21 edges
10. `indexOf()` - 19 edges

## Surprising Connections (you probably didn't know these)
- `execute()` --calls--> `get()`  [INFERRED]
  tests/run.php → fonctions.php
- `tenant_choisi()` --calls--> `purge_db()`  [INFERRED]
  exercices.php → fonctions.php
- `ia_corriger_intention()` --calls--> `exos_type()`  [INFERRED]
  ia.php → exercices.php
- `ia_corriger_intention()` --calls--> `exos_question()`  [INFERRED]
  ia.php → exercices.php
- `ia_prepare()` --calls--> `ouvre_base()`  [INFERRED]
  ia.php → fonctions.php

## Communities (43 total, 4 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (87): $(), aides(), ajoute(), apparait(), appel(), apropos(), attr_js(), bascule_aide() (+79 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (71): addClass(), addModifierNames(), clearCaches(), clearDragCursor(), clickInGutter(), clickRepeat(), CodeMirror(), configureMouse() (+63 more)

### Community 2 - "Community 2"
Cohesion: 0.07
Nodes (58): bases_dossiers(), bases_nom_valide(), bases_racine(), bases_supprime_dossier(), bases_synchronise(), bases_synchronisees(), bases_telecharge(), exos_appel() (+50 more)

### Community 3 - "Community 3"
Cohesion: 0.05
Nodes (31): bind(), buildCollapsedSpan(), buildToken(), copyObj(), createObj(), ensureGlobalHandlers(), eventMixin(), extendMode() (+23 more)

### Community 4 - "Community 4"
Cohesion: 0.07
Nodes (54): addLineWidget(), addToScrollTop(), adjustScrollWhenAboveVisible(), bidiSimplify(), boxIsAfter(), charCoords(), charWidth(), coordsBidiPart() (+46 more)

### Community 5 - "Community 5"
Cohesion: 0.09
Nodes (40): alignHorizontally(), buildLineContent(), buildLineElement(), buildTokenBadBidi(), classTest(), compensateForHScroll(), defaultSpecialCharPlaceholder(), Display() (+32 more)

### Community 6 - "Community 6"
Cohesion: 0.10
Nodes (37): activeElt(), belongsToInput(), calculateScrollPos(), copyableRanges(), countDirtyView(), disableBrowserMagic(), displayHeight(), displayWidth() (+29 more)

### Community 7 - "Community 7"
Cohesion: 0.12
Nodes (31): addEditorMethods(), callBlankLine(), clearLineMeasurementCache(), clearLineMeasurementCacheFor(), copyState(), deleteNearSelection(), directionChanged(), docMethodOp() (+23 more)

### Community 8 - "Community 8"
Cohesion: 0.17
Nodes (28): ia_appel(), ia_appliquer(), ia_compteur_global(), ia_config(), ia_corriger_intention(), ia_ecrit(), ia_erreur(), ia_est_liste() (+20 more)

### Community 9 - "Community 9"
Cohesion: 0.10
Nodes (18): c, charge(), classes(), cm, colores, echappe, element(), fs (+10 more)

### Community 10 - "Community 10"
Cohesion: 0.11
Nodes (21): attachDoc(), attachMarkedSpans(), cleanUpLine(), collapsedSpanAtEnd(), collapsedSpanAtSide(), collapsedSpanAtStart(), detachMarkedSpans(), endOperation_R1() (+13 more)

### Community 11 - "Community 11"
Cohesion: 0.14
Nodes (20): cmp(), copyHistoryArray(), detachSharedMarkers(), equalCursorPos(), filterSelectionChange(), findColumn(), findPosH(), indexOf() (+12 more)

### Community 12 - "Community 12"
Cohesion: 0.11
Nodes (15): A, B, base, BUDGET_CROISEMENTS, contexte, croise(), fs, liens (+7 more)

### Community 13 - "Community 13"
Cohesion: 0.11
Nodes (18): code:nginx (location ~ ^/SQLSandbox/(tmp|bdd|lib|tests)/ {), code:ini ([sqlsandbox]), code:sh (sudo setfacl -m u:caddy:x /home/<utilisateur>), code:caddy (redir /SQLSandbox /SQLSandbox/ 308), code:caddy (@tenant_barre path_regexp tenant_barre ^/SQLSandbox/([0-9]{6), code:sh (cd /home/<utilisateur>/programmation && git clone https://gi), code:sh (cd /home/<utilisateur>/programmation/SQLSandbox && git pull ), code:sh (php tests/run.php) (+10 more)

### Community 14 - "Community 14"
Cohesion: 0.24
Nodes (17): addChangeToHistory(), addSelectionToHistory(), adjustForChange(), attachLocalSpans(), changeEnd(), clearSelectionEvents(), computeSelAfterChange(), historyChangeFromChange() (+9 more)

### Community 15 - "Community 15"
Cohesion: 0.16
Nodes (16): addMarkedSpan(), adjustView(), buildViewArray(), changeLine(), clipLine(), copySharedMarkers(), findViewIndex(), markText() (+8 more)

### Community 16 - "Community 16"
Cohesion: 0.21
Nodes (15): badPos(), clipPos(), clipPosArray(), clipToLen(), computeReplacedSel(), copyPos(), domToPos(), findSharedMarkers() (+7 more)

### Community 17 - "Community 17"
Cohesion: 0.26
Nodes (14): applyTextInput(), countColumn(), findStartLine(), getBetween(), getLine(), indentLine(), isWholeLineUpdate(), makeChangeSingleDoc() (+6 more)

### Community 18 - "Community 18"
Cohesion: 0.14
Nodes (13): database, name, type, questions, q0, q1, q2, q3 (+5 more)

### Community 19 - "Community 19"
Cohesion: 0.22
Nodes (3): tokenBase(), tokenComment(), tokenLiteral()

### Community 20 - "Community 20"
Cohesion: 0.20
Nodes (10): clearEmptySpans(), getMarkedSpanFor(), getOldSpans(), isLine(), lineIsHiddenInner(), markedSpansAfter(), markedSpansBefore(), mergeOldSpans() (+2 more)

### Community 21 - "Community 21"
Cohesion: 0.24
Nodes (4): copies(), execute(), nouvelles(), tailles()

### Community 22 - "Community 22"
Cohesion: 0.22
Nodes (8): database, name, type, questions, cmp-em-01, int-em-01, re-em-01, tr-em-01

### Community 23 - "Community 23"
Cohesion: 0.25
Nodes (7): database, name, type, questions, q11, q12, q13

### Community 24 - "Community 24"
Cohesion: 0.29
Nodes (6): database, name, type, questions, q1, q2

### Community 25 - "Community 25"
Cohesion: 0.29
Nodes (6): database, name, type, questions, q1, q2

### Community 26 - "Community 26"
Cohesion: 0.29
Nodes (6): database, name, type, questions, re-em-02, tr-em-02

### Community 28 - "Community 28"
Cohesion: 0.50
Nodes (5): collapsedSpanAround(), compareCollapsedMarkers(), conflictingCollapsedRange(), extraLeft(), extraRight()

### Community 29 - "Community 29"
Cohesion: 0.40
Nodes (4): code:sql ({{ requete }}), Format de la réponse, Instructions, Situation

### Community 30 - "Community 30"
Cohesion: 0.83
Nodes (3): ajoute(), copie(), ensemble()

## Knowledge Gaps
- **80 isolated node(s):** `fs`, `vm`, `path`, `racine`, `echappe` (+75 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **4 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `CodeMirror()` connect `Community 1` to `Community 0`, `Community 3`, `Community 5`, `Community 7`, `Community 10`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **Why does `affiche_sql()` connect `Community 0` to `Community 1`?**
  _High betweenness centrality (0.085) - this node is a cross-community bridge._
- **Are the 14 inferred relationships involving `$()` (e.g. with `ia_apercu()` and `ia_applique()`) actually correct?**
  _`$()` has 14 INFERRED edges - model-reasoned connections that need verification._
- **What connects `fs`, `vm`, `path` to the rest of the system?**
  _80 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.06246134817563389 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.06277665995975855 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.06971153846153846 - nodes in this community are weakly interconnected._