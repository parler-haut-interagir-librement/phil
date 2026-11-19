<!-- phil-dev source-sha256=9e890a052ed8b3a4c63391fef6e706e996ffe5eed5255cf17102764dce4781d1 -->
# Le composant

```twig
<twig:PhilReactions content="article:42" />
```

`content` est une référence de `phil/resource`, sous forme de texte ou de `ResourceReference`. Ses propriétés sont
signées : un navigateur ne peut pas changer le contenu auquel il réagit, et le membre est toujours celui de la requête.

Un même contenu peut être dessiné deux fois, au début et à la fin d'un article : le premier qui change demande aux
autres de se redessiner.

## Une page de nombreux contenus

Chaque composant lit son contenu quand il est dessiné. Une page de nombreux contenus les lit d'abord, en une requête
pour les compteurs, une pour les choix de celui qui regarde, et une pour les noms quand ils sont affichés :

```twig
{{ reactions_preload(contents) }}
```

`contents` est une liste de références, sous forme de texte ou d'objets ; en PHP, `ReactionBoard::preload()`. Ce qu'il
lit dure le temps de la requête.

## Sans JavaScript

Le composant est un formulaire envoyé à la route `phil_reactions_submit`, dont l'adresse est signée et nomme le contenu
et la page vers laquelle revenir. Son jeton est le jeton CSRF sans état `phil_reactions` de Symfony. Un succès revient à
la page avec `303` ; tout le reste s'arrête sur `@PhilReactions/stopped.html.twig`, avec `403`, `409`, `422` ou `429`, qu'une
application redéfinit sous `templates/bundles/PhilReactionsBundle/`. Une réaction au-delà du plafond de `per_member`
répond `409`.

Chaque bouton dit l'état qu'il veut : un double clic ou un formulaire envoyé deux fois ne change donc rien de plus.

## L'accessibilité

| Quoi | Comment |
|---|---|
| Les réactions de celui qui regarde | `aria-pressed` sur leurs boutons |
| Celui qui regarde a atteint le plafond de `per_member` | Une phrase après la barre, les autres réactions ne devant pas être pressées |
| Ce qu'est un bouton | Le libellé de la réaction et son compteur lus à voix haute, l'émoji et les chiffres cachés aux lecteurs d'écran ; une image a son texte de remplacement |
| Ce qui s'est passé | Une région active polie dit que la réaction a été enregistrée, retirée ou refusée |
| Le focus | Gardé sur le même bouton d'un rendu à l'autre, par son `data-phil-focus` |
| Un doigt | Les recettes font chaque bouton d'au moins 2,75rem, ou `min-h-11 min-w-11`, de large et de haut |
| Qui a réagi | Un dévoilement, atteignable au clavier, pas une infobulle ; les noms forment une liste écrite dans la langue de la page, « Ada, Linus and Grace », par `format_list` |

## Le sélecteur

Les réactions qui ne sont pas dans la barre se trouvent dans un `<details>` de simples boutons. Le contrôleur
`phil--reactions-bundle--picker` ajoute :

| Touche | Ce qu'elle fait |
|---|---|
| Saisir dans la recherche | Garde les réactions dont le libellé contient le texte, sans égard à la casse ni aux accents, et dit combien il en reste |
| Entrée dans la recherche | Choisit la première réaction affichée |
| Bas, depuis la recherche | Va à la première réaction |
| Flèches | Se déplacent parmi les réactions affichées ; Haut depuis la première revient à la recherche |
| Début, Fin | La première et la dernière réaction affichée |
| Échap, ou un clic ailleurs | Ferme le sélecteur ; Échap remet le focus sur son résumé |
