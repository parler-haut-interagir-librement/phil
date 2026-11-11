<!-- phil-dev source-sha256=2e930bdfd461e2e77eaf4277b3103023bfd47f7d0a8b54fb987f3e5de3c2bc1b -->
# L'élément courant

Après les décorateurs, `Menus` marque l'élément où se trouve le visiteur (`current`) et les éléments qui y mènent
(`currentAncestor`).

Un élément est courant quand :

- il a une route, la route du visiteur est la même, et chacun de ses paramètres figure parmi ceux du visiteur avec la
  même valeur sous forme de texte (le visiteur peut avoir plus de paramètres) ; ou
- il a une adresse sur le site, qui commence par une seule `/`, dont le chemin est celui du visiteur tel qu'un
  navigateur le demande (les segments `.` et `..` résolus, échappés ou non, une lettre hors ASCII encodée en
  pourcentage, comme phil/url lit les deux) ; la chaîne de requête n'est pas comparée.

Une adresse sur un autre site n'est jamais courante. Twig lit la route, ses paramètres, le chemin et la langue dans la
requête en cours de traitement.
