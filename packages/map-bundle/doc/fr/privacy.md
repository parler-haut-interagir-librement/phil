<!-- phil-dev source-sha256=efd94dc72fbbd40afcef0eb68c9afe27b1529fdada05a8d6974ca74617790881 -->
# Vie privée

La couche de quartier répond à une question comme « y a-t-il des membres près de chez moi ? » sans dire où se trouve
aucun membre.

## Ce qu'elle fait

`PrivacyGrid` divise le monde en cellules de `neighbourhood.cell_metres` — des rangées à partir de l'équateur, des
colonnes à partir du méridien d'origine, la largeur d'une colonne suivant la latitude de sa rangée pour que les cellules
restent à peu près carrées. `Neighbourhood` compte par cellule les positions qu'on lui donne, et garde les cellules qui en
contiennent au moins `neighbourhood.minimum`. Une cellule gardée est dessinée comme son rectangle, avec son compte ; son
entrée dans la liste nomme son milieu à deux décimales.

## Ce qu'elle ne montre jamais

- Une position : ni les positions données ni rien qui dérive d'une seule d'entre elles n'en sort — seulement les coins
  d'une cellule, son milieu et son compte.
- Une cellule de moins que le minimum : avec la valeur par défaut de 3, personne ne peut être repéré comme la seule
  personne d'une zone.
- Qui est dans une cellule : la couche compte, elle ne nomme pas.

## Pourquoi une grille fixe

- Arrondir les coordonnées est aussi une grille, mais ses cellules rétrécissent vers les pôles et changent de taille avec
  le nombre de décimales ; `PrivacyGrid` les garde à peu près aussi larges que hautes, en mètres, partout sous 89,4°.
- Un bruit aléatoire ajouté à chaque position s'annule en moyenne : en demandant assez souvent la même carte, on trouve
  la vraie position. Une cellule fixe répond la même chose chaque fois.
- Une grille ancrée sur l'équateur et le méridien d'origine place la même position dans la même cellule sur chaque site
  et à chaque requête, de sorte que deux cartes ne peuvent être combinées pour la préciser.

## Ce que l'application décide encore

- De qui elle donne les positions : les membres qui ont accepté d'apparaître, par exemple.
- Avec quelle précision elle stocke les positions : la grille cache ce qu'on lui donne, le stockage le garde.
- La cellule et le minimum : une région peu peuplée demande de plus grandes cellules pour qu'une cellule atteigne le
  minimum ; une ville dense peut en employer de plus petites. Une cellule va de 100 m à 50 km.

Une personne près du bord d'une cellule est dans cette cellule, non dans la suivante : quelqu'un qui sait qu'une cellule
contient exactement le minimum, et qui connaît tous ses habitants sauf un, apprend que le dernier est dans cette
cellule — jamais plus précisément.
