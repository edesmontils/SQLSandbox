# À faire

## Diagramme du modèle : disposition (topographie) du graphe

Le placement des tables dans le diagramme (`place_tables()` et `dessine_modele()` dans
`js/modele.js`) laisse des liens qui traversent des tables.

Exemple sur `tp-robots` : la flèche `resultats → robots` passe sur les tables `epreuves`
et `inscriptions`, parce que `robots` est dans la première colonne et `resultats` dans la
dernière, avec deux colonnes de tables entre elles.

Pistes :
- ordonner les tables d'une colonne pour réduire les croisements de liens (par exemple
  placer `robots` près de `resultats` plutôt qu'en bas de la première colonne) ;
- faire contourner les boîtes aux courbes, par exemple en passant dans l'espace libre entre
  deux colonnes (`ecart_x`) ;
- ou répartir les tables en tenant compte des liens de chaque table, et non plus seulement
  de leur niveau (le plus long chemin vers les tables référencées).

À vérifier sur les bases du dépôt (`tp-robots`, `tp-em`, `tp-vols`, `tp-cinema`,
`tp-Lutins`, `tp-mondial`) : le test `tests/modele.js` contrôle le nombre de flèches ; un
test de la disposition pourrait compter les liens qui traversent une boîte.
