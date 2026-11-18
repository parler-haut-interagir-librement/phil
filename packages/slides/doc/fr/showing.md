<!-- phil-dev source-sha256=d444d1c3f886a08f00211938257d4c4e696d64b0b7d2b00e7bb1180d349f29fc -->
# Montrer une présentation

```twig
{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(talk.source, 'markdown', 'talk-' ~ talk.id), presenter_url: path('phil_slides_presenter', {deck: 'talk-' ~ talk.id})}) }}
```

| Variable | Valeur par défaut | Rôle |
|---|---|---|
| `deck` | obligatoire | Le `Deck`, tiré de `slides_deck()` ou de `slides_markup()` |
| `presenter_url` | aucune | Un lien vers la vue du présentateur, ouverte dans sa propre fenêtre |
| `id` | `phil-slides` | L'`id` de l'élément |
| `keyboard` | aucune | `page` : les touches pilotent la présentation où que soit le focus, pour une page qui ne contient rien d'autre |

Les notes du présentateur ne sont jamais dans cette page : le gabarit rend chaque diapositive avec `slides_content()`,
qui les omet.

## Sans script

Les diapositives se suivent, chacune une boîte de la forme de la présentation avec son en-tête, son contenu et son pied
de page, tous les fragments montrés : une présentation se lit dans l'ordre, s'imprime depuis le navigateur à raison
d'une diapositive par page, et est indexée comme une page. Les commandes sont `hidden`.

## Avec le contrôleur

`phil--slides--deck` marque la présentation `isPresenting` et montre une diapositive à la fois, celle qui est
`isCurrent` ; les fragments à venir sont `isPending`, gardés à leur place mais cachés. Il démarre à la diapositive que
nomme l'adresse, `#slide-3`, et garde l'adresse sur la diapositive courante : un lien ou un rechargement y atterrit, et
un changement d'adresse déplace la présentation.

| Touche | Action |
|---|---|
| Flèche droite, flèche bas, Page suivante, Espace | Fragment suivant, ou diapositive suivante |
| Flèche gauche, flèche haut, Page précédente | Fragment précédent, ou diapositive précédente avec tous ses fragments montrés |
| Début, Fin | Première diapositive, dernière diapositive avec tous ses fragments montrés |
| F | Active ou désactive le plein écran |
| Échap | Quitte le plein écran |

Les touches appartiennent à la présentation tant que le focus est dedans — la boîte des diapositives prend le focus
avec Tab —, en plein écran, ou n'importe où dans la page avec `keyboard: 'page'`. Une touche tapée dans un champ, ou
pressée avec Ctrl, Alt ou Commande, est laissée à la page. Les boutons *Previous*, *Next* et *Full screen* font la même
chose ; le compteur dit *3 / 12*.

## Accessibilité

- La présentation est une région nommée d'après son titre, décrite comme un *slide deck* ; chaque diapositive est
  décrite comme une *slide* et nommée *Slide 3 of 12 – son titre*.
- Une région dynamique polie annonce chaque nouvelle diapositive, avec son titre, une fois que le lecteur avance ; rien
  n'est dit à l'ouverture de la page.
- Le focus reste où il est : avancer dans la présentation ne le prend pas.
- L'entrée d'une diapositive se fond seulement pour les lecteurs qui n'ont pas demandé de mouvement réduit.
- *Full screen* est un bouton à bascule, dont `aria-pressed` dit l'état.
- Les boutons et le lien vers la vue du présentateur sont la recette `Button` du kit : de vrais éléments `button` de type
  `button`, et un vrai lien, atteints avec Tab et actionnés avec Entrée ou Espace, avec l'anneau de focus du kit.
- Les libellés sont dans le domaine de traduction `phil_slides`, en anglais et en français.

## L'apparence

`assets/styles/slides.css` habille la présentation par-dessus la feuille de style de PHIL UX, avec ses jetons —
`--phil-space-*`, `--phil-color-*`, `--phil-radius-md`, `--phil-duration-sm` —, sinon ceux du kit Shadcn, sinon une
couleur du système ou une valeur OKLCH. Les boutons prennent l'apparence du `Button` du kit. Une diapositive est un
conteneur dont le texte grandit avec sa largeur. Les classes d'état sont `isPresenting`,
`isCurrent`, `isPending` et `isFullscreen` ; les classes de la directive `class` et des blocs `pageStyle` vont sur
chaque diapositive, à côté de `phil-slide`, pour les styles propres à l'application.

| Élément | Classe |
|---|---|
| La présentation | `phil-slides`, avec `data-ratio` |
| La boîte des diapositives | `phil-slides__stage` |
| Une diapositive | `phil-slide`, son en-tête `phil-slide__header`, son contenu `phil-slide__content`, son pied de page `phil-slide__footer`, son numéro `phil-slide__number` |
| Les commandes | `phil-slides__controls`, le compteur `phil-slides__counter` |
| Le lien vers la vue du présentateur | `phil-slides__presenter` |
