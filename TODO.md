# À faire

## Diagramme du modèle : pistes d'amélioration de la disposition

La disposition en couches avec points de passage (`disposition_tables()` dans `js/modele.js`)
est en place : plus aucun lien ne traverse une table, et `tests/modele.js` le vérifie, avec un
plafond de croisements, sur les 7 bases du dépôt. Ce qui reste, par ordre d'intérêt :

- **tp-mondial** (33 tables, 75 liens) garde environ 240 croisements, contre 339 avant. Les
  tables très connectées (`Country`, `Province`, `City`) en laissent forcément beaucoup, mais
  on peut encore gagner :
  - le cycle `Country → City → Province → Country` : le lien qui remonte vers la gauche est
    choisi d'après le nombre de références ; essayer d'autres coupures et garder la meilleure ;
  - aligner chaque table sur la hauteur moyenne des tables qu'elle référence, au lieu de les
    empiler (liens plus droits, moins de dénivelés) ;
  - le diagramme est plus étroit (5 colonnes au lieu de 17) mais plus haut (environ 1740 px
    au lieu de 1130) : limiter la hauteur d'une couche si cela gêne.
- **tp-em, tp-cinema** : 1 croisement chacun, qui est le minimum pour l'ordre des tables dans
  les couches ; seul un autre découpage en couches pourrait faire mieux.
- Le test utilise une largeur de texte fixe (7 px par caractère) ; dans un navigateur, la
  largeur des boîtes dépend de la police et peut modifier légèrement la disposition.
