<!-- phil-dev source-sha256=707267e2366c6d0694c9a197e34bf8290ecdb123ed9b71a32e43448e2c801996 -->
# L'élément courant

Après les décorateurs, `Menus` marque l'élément où se trouve le visiteur (`current`) et les éléments qui y mènent
(`currentAncestor`).

Un élément est courant quand :

- il a une route, la route du visiteur est la même, et chacun de ses paramètres figure parmi ceux du visiteur avec la
  même valeur sous forme de texte (le visiteur peut avoir plus de paramètres) ; ou
- il a une adresse sur le site, sans hôte, dont le chemin est celui du visiteur (la chaîne de requête n'est pas
  comparée).

Une adresse sur un autre site n'est jamais courante. Twig lit la route, ses paramètres, le chemin et la langue dans la
requête en cours de traitement.
