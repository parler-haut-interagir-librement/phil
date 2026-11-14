<!-- phil-dev source-sha256=913b30dc92262f521952818be87bc24d1012c724d65b5e0dd87063f82921aace -->
# Accessibilité

- Un vrai `<table>` avec son `<caption>`, `scope="col"` sur les en-têtes et `scope="row"` sur la première cellule de
  chaque ligne.
- `aria-sort` sur la colonne triée, et un vrai `<button>` dans chaque en-tête triable.
- Le nombre de résultats, et ce qu'a fait une action ou une modification, dans une région active `role="status"`.
- La pagination est un `<nav>` étiqueté, et la page courante porte `aria-current="page"`.
- La case à cocher et l'action de chaque ligne sont nommées d'après la ligne.
- Les erreurs d'une modification sont dans `role="alert"` et liées au contrôle par `aria-invalid` et
  `aria-describedby`.
- La question d'une action est un `<dialog>` natif de rôle `alertdialog`, nommé d'après sa question. Il est dessiné
  ouvert : un navigateur sans JavaScript le montre donc dans la page, avant les lignes, avec son simple formulaire et un
  lien Annuler vers le tableau. Le contrôleur Stimulus le rend modal : le navigateur rend la page inerte derrière lui et
  garde le focus à l'intérieur. Le focus commence sur Annuler, la réponse qui ne fait aucun mal ; Échap répond comme
  Annuler ; une fois le dialogue disparu, le focus retourne au bouton qui l'avait demandé.
- Quand la ligne en cours de modification n'est plus dans la page — autre tri, autre filtre ou autre page —, la région
  active le dit.
- Le texte masqué visuellement emploie le `phil-data-table__hidden` du bundle, si bien que le tableau n'a besoin
  d'aucun utilitaire d'une feuille de style.

## Le clavier

Chaque contrôle est un lien, un bouton ou un champ de formulaire. Un lien Modifier ouvre le contrôle de la cellule et y
déplace le focus. Entrée enregistre et Échap revient en arrière. Le contrôleur Stimulus redonne le focus au contrôle
qui l'avait après un nouveau rendu, par sa clé `data-phil-focus`, et un contrôle que le serveur marque `autofocus` passe
en premier. La suite au navigateur le vérifie dans Chrome, après un filtre, une colonne masquée, un tri et le dialogue
d'une action.

## La liste empilée

La feuille de style empile les lignes quand le conteneur du tableau est plus étroit que `40rem`, et chaque cellule
reçoit pour en-tête son `data-label`. Un navigateur abandonne la sémantique d'un tableau dessiné en blocs : le gabarit la
répète donc sous forme de rôles, `table`, `rowgroup`, `row`, `columnheader`, `rowheader` et `cell`. Les boutons de tri
restent disponibles au-dessus de la liste.

La feuille de style n'emploie aucun style en ligne : une politique de sécurité du contenu sans `unsafe-inline` montre
donc le tableau tel qu'il a été conçu.
