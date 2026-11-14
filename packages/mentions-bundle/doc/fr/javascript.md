<!-- phil-dev source-sha256=d42811e9cf1da6291766167ad6fa88fb30b2676ef04937936f48bc5308043c18 -->
# JavaScript

## Le contrôleur

`mention` — `phil--mentions-bundle--mention` une fois enregistré — est une autocomplétion `@` sur un champ de texte
brut, un `textarea` ou un `input` :

```twig
<textarea name="body"
          data-controller="phil--mentions-bundle--mention"
          data-phil--mentions-bundle--mention-url-value="{{ path('phil_block_editor_mentions') }}"
          data-phil--mentions-bundle--mention-empty-value="{{ 'No member found'|trans }}"></textarea>
```

| Valeur | Valeur par défaut | Effet |
|---|---|---|
| `url` | aucune | La recherche des membres de l'éditeur par blocs ; sans elle, la liste reste vide |
| `delay` | `150` | Les millisecondes entre la dernière touche et la recherche |
| `empty` | `No member found` | Ce que dit la liste, et qu'annonce la région dynamique, quand personne ne correspond |
| `found` | `{count} members found` | Ce qu'annonce la région dynamique quand des membres sont listés, `{count}` étant remplacé |

## Ce qu'il fait

- Quand la personne tape `@` suivi d'au plus 64 lettres, chiffres, `_`, `.` ou `-`, sans mot juste avant, il cherche
  les membres après le délai et annule la requête précédente.
- Il liste sous le champ les membres qui ont un identifiant : leur nom et `@handle`.
- Les flèches se déplacent dans la liste, Entrée ou Tab écrit l'identifiant choisi suivi d'une espace à la place de ce
  qui a été tapé, Échap ou la sortie du champ ferme la liste ; un clic choisit aussi.
- Il diffuse `phil--mentions-bundle--mention:chosen` avec `{handle, reference}`, et un événement `input` sur le champ.

Le champ reste du texte brut : le serveur retrouve les identifiants à l'enregistrement et décide qui est mentionné.

## Accessibilité

Le champ est un `combobox` avec `aria-autocomplete="list"`, `aria-controls` qui nomme la liste et `aria-expanded` ; la
liste est une `listbox` d'`option`, l'option active porte `aria-selected` et est nommée par `aria-activedescendant`.
Les lecteurs d'écran suivent ainsi le choix pendant que le focus reste dans le champ. Un `role="status"` masqué
visuellement, placé après la liste, annonce combien de membres ont été trouvés, ou que personne ne l'a été, et se vide
quand la liste se ferme. Les attributs et les éléments sont retirés quand le contrôleur se déconnecte. La suite de
navigateur vérifie chacun d'eux dans Chrome.

## La requête

Un `GET` de `url?q=<query>` avec l'en-tête `X-Phil-Block-Editor: 1`, sur la même origine, avec les identifiants de
connexion de la session : le point de terminaison de l'éditeur par blocs ne modifie rien et ne demande aucun jeton
CSRF. Une requête refusée ou en échec ne liste personne.

## Modules

| Module | Rôle |
|---|---|
| `mention_controller.js` | Le contrôleur |
| `lib/query.js` | `activeQuery(text, caret)`, `insertMention(text, range, handle)` et `handleOf(item)`, purs |
| `lib/api.js` | `suggestions(url, query, fetcher, signal)`, la recherche et ses suggestions qui ont un identifiant |

## La feuille de style

`phil-mentions/mentions.css` place la liste sous le champ — son parent porte `position: relative` — et met en forme la
liste et `.mention` à partir des jetons de PHIL UX ou des couleurs de la page, avec un contour en couleurs forcées.
