<!-- phil-dev source-sha256=1d6d42f696a0c23a9b180c5ebf47915b6b1fcac9de5fbcec5a1d88f6709db65a -->
# Configuration

```yaml
# config/packages/phil_slides.yaml
phil_slides:
    split: auto
    heading_level: 2
    notes_class: notes
    fragment_class: fragment
    aspect_ratio: '16:9'
    max_slides: 500
    max_source_bytes: 1048576
    markup_level: all
    presenter_role: ROLE_SLIDES_PRESENTER
    layout: '@PhilSlides/layout.html.twig'
    kit_subdirectory: ~
    pdf:
        notes_page_size: letter
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `split` | `auto` | Où un document est coupé : `separators`, `headings`, `both`, ou `auto` — à ses séparateurs quand il en contient un au niveau supérieur, à ses titres sinon. La directive `headingDivider` d'une source coupe aux deux. |
| `heading_level` | `2` | Le titre le plus profond qui commence une diapositive, de 1 à 6. |
| `notes_class` | `notes` | La classe des blocs `container` qui contiennent les notes du présentateur. |
| `fragment_class` | `fragment` | La classe des blocs `container` qui apparaissent un à la fois. |
| `aspect_ratio` | `16:9` | La forme d'une diapositive quand la source n'en nomme aucune : `16:9` ou `4:3`. |
| `max_slides` | `500` | Le plus grand nombre de diapositives d'une présentation ; un document qui en produit davantage est refusé avec `DeckRefused`. |
| `max_source_bytes` | `1048576` | La plus grande source que `DeckReader::fromMarkup()` lit, en octets. |
| `markup_level` | `all` | Le niveau auquel `phil/markup` lit une source ; voir plus bas. |
| `presenter_role` | `ROLE_SLIDES_PRESENTER` | Le rôle que demandent les routes du présentateur ; tous les autres sont refusés (403). Voir [Vue du présentateur](presenter.md#sécurité). |
| `layout` | `@PhilSlides/layout.html.twig` | Le gabarit Twig que la route du présentateur étend, avec les blocs `title` et `main`. |
| `kit_subdirectory` | `null` | L'endroit de la recette `Button` sous le répertoire des composants : `ui` la nomme `ui:Button`. À `null`, le sous-répertoire de la configuration `ux_toolkit` ; à définir dans un environnement sans le Toolkit. |
| `pdf.notes_page_size` | `letter` | La page verticale sur laquelle une diapositive est imprimée avec ses notes : `letter` ou `a4`. |

Une classe compte de 1 à 64 lettres, chiffres, `_` ou `-` ; une valeur hors limites arrête la construction du
conteneur.

## Le niveau Markdown

`all` est la valeur par défaut, et le niveau à garder pour les présentations écrites par l'équipe du site. À `all`, le
HTML brut d'une source devient des blocs `raw`, et le moteur de rendu de `phil/ujo-bundle` les refuse à moins que
`phil_ujo.allow_raw` soit à `true` : ce refus est la protection, la même que pour tous les autres documents de
l'application ; une présentation ne contient donc aucun HTML que l'application n'a pas permis. Les commentaires HTML
sont les notes du présentateur, ou les directives d'une diapositive, à partir de la version de `phil/markup` dont le
lecteur Markdown les conserve comme modulations `note` (voir [Rédaction](authoring.md#notes-dans-des-commentaires-html)) ;
avant elle, ils sont omis.

`forum` et `basic` ne lisent aucun HTML brut : ils l'affichent, avec les commentaires, comme du texte. Ne choisissez
l'un d'eux que pour des sources écrites par des gens à qui l'application ne fait pas confiance.

Le rendu de chaque diapositive — les types de blocs permis, le HTML brut, les limites d'un document — relève de la
configuration de `phil_ujo`, la même que pour tous les autres documents de l'application.
