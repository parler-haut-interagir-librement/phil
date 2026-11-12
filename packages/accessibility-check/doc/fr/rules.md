<!-- phil-dev source-sha256=5e4ffa150a7f2a2727075cc2ce742d7723c94efe74e5466f8d6454d5a1705055 -->
# Règles

Les règles de `DefaultRules::all()`, dans leur ordre d'exécution. Chacune lit le HTML ; chacune sauf `html-lang` lit
aussi l'UJO. La sévérité est celle de la règle, sauf si la ligne indique autre chose pour un cas, et la politique peut
en donner une autre à une règle. Une clé de message est formée de l'identifiant de la règle et du cas, par exemple
`image-alt.missing`. Les paramètres substituables figurent entre accolades.

| Règle | Critère | Sévérité | Vérifie |
|---|---|---|---|
| `image-alt` | 1.1.1 Contenu non textuel (A) | erreur | les images ont un texte de remplacement |
| `heading-order` | 1.3.1 Information et relations (A) | avertissement | aucun niveau de titre n'est sauté |
| `heading-h1` | 1.3.1 Information et relations (A) | avertissement | les titres de premier niveau permis par la politique |
| `empty-heading` | 2.4.6 En-têtes et étiquettes (AA) | avertissement | les titres ont du texte |
| `link-name` | 2.4.4 Fonction du lien (selon le contexte) (A) | erreur | les liens ont un nom |
| `button-name` | 4.1.2 Nom, rôle et valeur (A) | erreur | les boutons ont un nom |
| `link-text` | 2.4.4 Fonction du lien (selon le contexte) (A) | avertissement | le nom des liens dit où ils mènent |
| `color-contrast` | 1.4.3 Contraste (minimum) (AA), ou 1.4.6 (AAA) | erreur | le texte coloré se détache de son fond |
| `table-headers` | 1.3.1 Information et relations (A) | erreur | les tableaux de données marquent leurs cellules d'en-tête |
| `html-lang` | 3.1.1 Langue de la page (A) | erreur | une page entière indique sa langue |
| `lang-change` | 3.1.2 Langue d'un passage (AA) | erreur | les passages dans une autre langue indiquent laquelle |
| `frame-title` | 4.1.2 Nom, rôle et valeur (A) | erreur | les cadres disent ce qu'ils contiennent |
| `video-captions` | 1.2.2 Sous-titres (préenregistrés) (A) | erreur | les vidéos portent des sous-titres |
| `duplicate-id` | 4.1.2 Nom, rôle et valeur (A) | avertissement | un identifiant nomme un seul élément |
| `svg-name` | 1.1.1 Contenu non textuel (A) | avertissement | les dessins disent ce qu'ils montrent |

Ce qui est affiché compte : un élément `hidden`, `aria-hidden="true"`, `display: none` ou `visibility: hidden` en ligne,
ou placé à l'intérieur d'un tel élément, ou dans `head`, `script`, `style`, `template` ou `noscript`, est laissé de
côté.

## `image-alt`

En HTML, un `img`, un `area` avec un `href` et un `input type="image"` ont un `alt`, ou un nom ARIA (`aria-label`,
`aria-labelledby`) ou un `title`. `alt=""` marque une image décorative et réussit, tout comme un `img` de rôle
`presentation` ou `none`. En UJO, un bloc `image` a un `alt` ou une légende, et chaque fichier d'une `gallery`, une
légende.

| Clé | Sévérité | Paramètres | Quand |
|---|---|---|---|
| `image-alt.missing` | erreur | `source` | aucun texte de remplacement |
| `image-alt.blank` | avertissement | `source` | un `alt` formé uniquement d'espaces |
| `image-alt.filename` | avertissement | `source`, `alt` | le texte de remplacement est un nom de fichier — `photo.jpg`, `IMG_2041`, `DSC0001`, `untitled`, le nom du fichier de l'image elle-même |

## `heading-order`

Un titre descend d'au plus un niveau par rapport au titre précédent : un `h4` après un `h2` saute un niveau. Remonter
d'un nombre quelconque de niveaux réussit. Les titres sont les `h1` à `h6`, les éléments de rôle `heading` avec leur
`aria-level` (2 s'il est absent) et, en UJO, les blocs `header` avec leur `level` (2 s'il est absent) et les titres des
champs HTML, dans l'ordre du document. Avec `HeadingPolicy::None`, le contenu se place sous le titre de sa page : son
premier titre est donc un `h2`.

| Clé | Paramètres |
|---|---|
| `heading-order.skipped` | `level`, `previous` |

## `heading-h1`

| Politique | Vérifie | Clé | Paramètres |
|---|---|---|---|
| `HeadingPolicy::Single` | au plus un `h1` : chaque `h1` en trop est signalé | `heading-h1.multiple` | `position` |
| `HeadingPolicy::Single` | une page HTML entière a un `h1` (avis) | `heading-h1.missing` | |
| `HeadingPolicy::None` | aucun `h1` : chacun est signalé | `heading-h1.forbidden` | |
| `HeadingPolicy::Any` | rien | | |

## `empty-heading`

Un titre a un nom accessible : son texte, le texte de remplacement d'une image qu'il contient, un nom ARIA.

| Clé | Paramètres |
|---|---|
| `empty-heading.empty` | `level` |

## `link-name`

Un `a` avec un `href` a un nom accessible : son texte, le texte de remplacement de ses images, le titre de ses dessins,
un nom ARIA, sinon son `title`. En UJO, un bloc `button` a un texte.

| Clé | Paramètres |
|---|---|
| `link-name.empty` | `href` |

## `button-name`

Un `button`, un `input` de type `button`, `submit` ou `reset`, et tout élément de rôle `button` ont un nom accessible.
Un `input` `submit` ou `reset` sans valeur prend le nom que lui donne le navigateur.

| Clé | Paramètres |
|---|---|
| `button-name.empty` | |

## `link-text`

Le nom d'un lien n'est pas l'un des textes génériques de sa langue : « click here », « read more », « ici », « lire la
suite », « aquí », « mehr », « qui », « aqui »… ([Politique](policy.md#textes-de-lien-génériques)). La langue est le
`lang` du lien ou de son ancêtre le plus proche, sinon la langue du contenu. Si elle est inconnue, les textes de toutes
les langues comptent. La comparaison ne tient compte ni de la casse, ni des espaces, ni de la ponctuation et des flèches
autour du texte. En UJO, le texte d'un bloc `button` est aussi comparé. Un nom vide relève de `link-name`.

| Clé | Paramètres |
|---|---|
| `link-text.generic` | `href`, `text` |

## `color-contrast`

Un texte que le contenu colore a, avec son fond, le rapport de contraste de WCAG : 4,5:1, ou 3:1 pour le grand texte,
avec `ContrastLevel::AA` ; 7:1 et 4,5:1 avec `ContrastLevel::AAA`, dont les constats nomment 1.4.6. Le rapport est
calculé à partir de la luminance relative de WCAG et arrondi à l'unité inférieure à l'écriture, jamais vers le haut.

- **Seules les couleurs du contenu sont connues.** Un `style` avec `color`, `background-color` ou un `background` d'une
  seule couleur, les attributs `color` et `bgcolor`, et les jetons de l'apparence qu'UJO écrit dans `data-color`, dont
  la politique contient les couleurs. Un texte dont toutes les couleurs viennent des feuilles de style de l'apparence
  n'est pas vérifié. Lorsque le contenu donne l'une des deux couleurs, le `foreground` ou le `background` de la
  politique tient lieu de l'autre.
- **Les couleurs se lisent comme CSS les écrit** : hexadécimal, `rgb()`, `hsl()`, `oklch()`, couleurs nommées,
  `transparent`, opacité comprise. Une couleur translucide se superpose à ce qui se trouve derrière. Une couleur
  `oklch()` passe par OKLab vers sRGB, chaque canal ramené dans sRGB lorsqu'il en sort. Un texte sur une image ou un
  dégradé, ou dans une couleur impossible à calculer, est laissé de côté.
- **`var()` et `color-mix()` ne sont pas résolus** : seules les feuilles de style de la page donnent leur couleur.
  L'élément qui donne une telle couleur à son texte ou à son fond, lorsqu'il affiche du texte, reçoit une seule
  remarque, `color-contrast.unresolved`, pour qu'une personne vérifie ce texte dans un navigateur ; son texte n'est pas
  mesuré.
- **Le grand texte** mesure 24 pixels CSS, ou 18,66 en gras. La taille est le `font-size` en ligne le plus proche en
  `px`, `pt`, `em`, `rem` ou `%` (de 16 pixels), sinon la taille par défaut du titre qui contient le texte. La graisse
  est le `font-weight` en ligne le plus proche, sinon le gras à l'intérieur de `b`, `strong`, `th` ou d'un titre.

Chaque élément dont le texte propre est affiché n'est vérifié qu'une fois.

| Clé | Paramètres |
|---|---|
| `color-contrast.insufficient` | `text` (coupé à 40 caractères), `ratio`, `foreground`, `background`, `required` |
| `color-contrast.unresolved` (remarque) | `value`, le `color`, `background-color` ou `background` écrit, coupé à 60 caractères |

## `table-headers`

En HTML, un `table` de deux rangées ou plus contient un `th`, ou une cellule de rôle `columnheader` ou `rowheader`. Les
rangées et les cellules d'un tableau imbriqué appartiennent à ce tableau. Un tableau de rôle `presentation` ou `none`
sert à la mise en page et est laissé de côté. En UJO, un bloc `table` de deux rangées ou plus a `withHeadings` ou une
cellule marquée `header`.

| Clé | Paramètres |
|---|---|
| `table-headers.missing` | |

## `html-lang`

Une page entière a une étiquette de langue BCP 47 bien formée dans le `lang` de son élément `html`. Un fragment prend la
langue de la page qui l'affiche et n'est pas vérifié.

| Clé | Paramètres | Quand |
|---|---|---|
| `html-lang.missing` | | aucun `lang`, ou un `lang` vide |
| `html-lang.invalid` | `lang` | un `lang` qui n'est pas une étiquette bien formée |

## `lang-change`

Chaque `lang` sous l'élément `html` est une étiquette bien formée ; `lang=""`, un passage de langue inconnue, est
signalé. En UJO, la *tune* `language` d'un bloc nomme une étiquette bien formée. Un changement de direction sans langue
— une *tune* ne portant qu'une direction, un `bdi`, un `bdo` ou un `span` avec `dir` sans `lang` — est signalé, car un
passage écrit dans l'autre sens est le plus souvent dans une autre langue.

| Clé | Sévérité | Paramètres |
|---|---|---|
| `lang-change.invalid` | erreur | `lang` |
| `lang-change.empty` | avis | |
| `lang-change.direction-only` | avis | |

## `frame-title`

Chaque `iframe` et chaque `frame` ont un `title` ou un nom ARIA ; le texte à l'intérieur d'un `iframe` ne compte pas. En
UJO, un bloc `embed` a un `title`.

| Clé | Paramètres |
|---|---|
| `frame-title.missing` | `source` |

## `video-captions`

Une `video` a un enfant `track` de type `captions` ou `subtitles` — le type d'une piste qui n'en indique aucun. En UJO,
un bloc `video` a une telle piste parmi ses `tracks`. C'est à l'auteur de vérifier que la piste dit tout ce que dit le
son. Un lecteur `audio`, dont l'équivalent est une transcription, n'est pas vérifié.

| Clé | Paramètres |
|---|---|
| `video-captions.missing` | `source` |

## `duplicate-id`

Un identifiant nomme un seul élément. Il est signalé une fois, à l'endroit où il est donné pour la deuxième fois :
erreur lorsqu'une étiquette, un attribut ARIA (`aria-labelledby`, `aria-describedby`, `aria-controls`…), un lien vers un
fragment ou un renvoi à une note y fait référence, avertissement sinon. En UJO, les identifiants sont ceux des *tunes*
`anchor`, des blocs `footnote` et des éléments des champs HTML, dans tout le document.

| Clé | Paramètres |
|---|---|
| `duplicate-id.duplicate` | `id`, `count` |

## `svg-name`

Un dessin écrit dans la page a un enfant `title` ou un nom ARIA, ou est décoratif : `aria-hidden="true"`, ou un rôle
`presentation` ou `none`. Un dessin imbriqué dans un autre, ou à l'intérieur d'un lien ou d'un bouton qui le nomme, est
laissé de côté. En UJO, les dessins qu'un bloc conserve dans `rendered` sont vérifiés comme champs HTML.

| Clé | Paramètres |
|---|---|
| `svg-name.missing` | |

## Ce qu'aucune règle ne vérifie

Ce qui dépend de l'apparence et du comportement d'une page plutôt que de son contenu : l'utilisation au clavier, le
focus, le contraste des couleurs définies par les feuilles de style, le mouvement, les délais, les formulaires et leurs
erreurs. Ce qui demande une personne : si un texte de remplacement décrit son image, si les sous-titres disent ce que
dit le son, si un titre nomme sa section.
