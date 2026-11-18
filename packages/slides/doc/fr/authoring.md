<!-- phil-dev source-sha256=193fd33892e421bffe102df34168966a0d7d1b2b3efa786b00b808abb527ca0b -->
# Rédaction

Une présentation est un document UJO (Universal JSON Output) : l'éditeur de blocs de l'application l'écrit, ou
`phil/markup` le lit depuis Markdown ou un autre format. Rien dans le document n'est propre aux diapositives : le même
document se lit comme un article quand il est rendu en entier.

## Où les diapositives sont coupées

Les blocs de premier niveau sont lus dans l'ordre.

| Bloc | Effet |
|---|---|
| `delimiter` — `---` en Markdown, après une ligne vide — ou `pageBreak` — `\page` | Termine la diapositive, et n'est pas montré |
| `header` jusqu'à `heading_level` | Commence une diapositive, sauf si la diapositive ne contient encore rien de montré |
| `pageStyle` — `\page {.lead}` | Donne ses classes à la diapositive qui le contient, et n'est pas montré |
| `pageNumber` de mode `skip` — `{{skipCounting}}` | Masque le numéro de la diapositive qui le contient, et n'est pas montré |
| Tout autre bloc | Montré sur la diapositive courante |

`split` choisit ce qui coupe : les séparateurs, les titres, les deux, ou, avec `auto`, les séparateurs quand le document
en contient un au niveau supérieur et les titres sinon — un document de l'éditeur de blocs, avec des titres et sans
séparateur, est coupé à ses titres. Une diapositive sans rien de montré et sans note est écartée : deux séparateurs de
suite, ou un séparateur avant un titre, ne font donc aucune diapositive vide. Le titre d'une diapositive est le texte
brut de son premier titre ; il la nomme pour les technologies d'assistance et dans la vue du présentateur.

En Markdown, laissez une ligne vide avant `---` : une ligne de texte suivie de `---` est un titre, comme CommonMark le
lit. Un `---` sur la première ligne ouvre l'en-tête YAML.

## Notes

Les notes du présentateur sont retirées de la diapositive où qu'elles soient imbriquées — dans une colonne, une
bascule, un encadré — et ne sont jamais rendues pour l'auditoire :

- un bloc `container` dont les classes nomment `notes_class` — `::: notes` … `:::` en Markdown — garde ses blocs, avec
  leurs mises en évidence, leurs listes et leurs liens ;
- la modulation `note` de n'importe quel bloc est une note en texte brut, dont les lignes sont conservées ;
  `phil/markup` la donne au bloc qui suit un commentaire HTML, ou au dernier bloc quand rien ne suit.

La modulation `note` d'un bloc qui ferme une diapositive — un séparateur, ou un titre qui commence la diapositive
suivante — appartient à la diapositive qu'il ferme : une note écrite à la fin d'une diapositive reste avec elle.

```markdown
## The plan

Three steps.

::: notes
Ask who has tried it already. Keep **five minutes** for questions.
:::
```

Une diapositive qui ne contient que des notes est conservée : le présentateur la voit, l'auditoire voit une diapositive
vide.

## Fragments

Un bloc `container` dont les classes nomment `fragment_class` — `::: fragment` … `:::` en Markdown — apparaît à sa
propre pression de touche, dans l'ordre du document, y compris les blocs imbriqués. Sans script, et à l'impression,
tous les fragments sont montrés.

```markdown
::: fragment
First, the problem.
:::

::: fragment
Then, the answer.
:::
```

## Directives

Les métadonnées d'une source — l'en-tête YAML que `phil/markup` lit avant un texte Markdown — sont les directives
globales de la présentation, sous les noms que leur donne Marp.

| Directive | Valeur | Effet |
|---|---|---|
| `title` | Texte, jusqu'à 200 caractères | Le nom de la présentation pour les technologies d'assistance, le titre de la vue du présentateur et du PDF |
| `lang` | Une balise BCP 47 | Le `lang` de la présentation et du document imprimé |
| `class` | Jusqu'à 16 noms de classes, séparés par des espaces | Classes de chaque diapositive |
| `paginate` | `true` ou `false` | Le numéro de chaque diapositive dans son pied de page |
| `header`, `footer` | Texte, jusqu'à 200 caractères | Répété sur chaque diapositive |
| `size` | `16:9` ou `4:3` | La forme des diapositives ; sinon `aspect_ratio` |
| `headingDivider` | 1 à 6 | Coupe aux séparateurs et aux titres jusqu'à ce niveau |

Une directive dont la valeur est d'un mauvais type, et tout autre nom — `theme`, `style`, `backgroundColor`… — n'est
pas conservé : `Deck::$ignoredDirectives` les liste, pour qu'un éditeur les montre. Les couleurs et les arrière-plans
relèvent de l'apparence du site : donnez une classe aux diapositives et stylez-la. Un document UJO n'a pas d'en-tête
YAML : `slides_deck()` et `DeckReader::fromDocument()` reçoivent les directives sous forme de tableau.

## Notes dans des commentaires HTML

Marp écrit les notes d'une diapositive, et les directives d'une seule diapositive, dans des commentaires HTML :

```markdown
---

<!-- _class: lead -->
<!-- _paginate: false -->

# Welcome

<!-- Thank the organisers. -->
```

Le lecteur HTML de `phil/markup` conserve chaque commentaire comme la modulation `note` du bloc qui le suit, et son
lecteur Markdown fait de même à partir de la version de `phil/markup` 0.2 qui suit 0.2.1-alpha. La présentation les lit
alors sans autre changement :

- un commentaire fait uniquement de directives Marp — chaque ligne `nom: valeur`, avec un nom que Marp donne à une
  directive — est lu comme des directives, non comme une note. `_class` donne des classes à sa diapositive, `class` à sa
  diapositive et aux suivantes ; `_paginate` et `paginate`, `true` ou `false`, montrent ou cachent le numéro de sa
  diapositive, ou de sa diapositive et des suivantes, quoi que dise l'en-tête YAML. Tout autre nom de directive, et une
  valeur d'un mauvais type, est listé dans `Deck::$ignoredDirectives` ;
- tout autre commentaire est une note de sa diapositive.

Une valeur peut être entre guillemets. Une ligne qui n'est pas une directive fait de tout le commentaire une note : une
note qui commence par `Title: …` ne devrait donc pas se trouver seule dans son commentaire. Avec un `phil/markup` plus
ancien, le lecteur Markdown omet les commentaires : écrivez `\page {.lead}` et `{{skipCounting}}` pour la diapositive,
et `::: notes` pour les notes, que toutes les versions lisent.

## Fabriquer une présentation en code

```php
use Phil\Slides\Application\DeckReader;

$deck = $reader->fromMarkup($source);                       // Markdown, read by phil/markup
$deck = $reader->fromMarkup($source, 'mediawiki');          // another format of phil/markup
$deck = $reader->fromDocument($page->body, ['title' => 'Q3']); // a document, or its UJO JSON
$deck = $reader->fromReading($reading);                     // what phil/markup already read
```

`DeckReader` est un service public. L'identifiant d'une présentation est une empreinte de ses blocs ; `Deck::withId()`
lui en donne un stable — l'identifiant de la conférence — pour que les fenêtres d'une présentation restent ensemble
pendant qu'elle est modifiée.
