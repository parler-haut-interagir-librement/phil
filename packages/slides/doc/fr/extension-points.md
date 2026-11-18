<!-- phil-dev source-sha256=5e209c483910595873846ce60246b6232091dd046dfa5d306dc24602fca100b3 -->
# Points d'extension

Ce qu'une application configure, remplace, substitue ou sur quoi elle s'appuie. Le paquet est en `0.x` : ses points
d'extension sont stables seulement à partir de sa version 1.0 ; d'ici là, une version mineure peut les changer, et son
CHANGELOG dit comment.

## Le découpage

| Point | Comment |
|---|---|
| Où les diapositives sont coupées | `split`, `heading_level`, et la directive `headingDivider` d'une source — voir [Configuration](configuration.md) |
| Ce qui contient des notes, ce qui est un fragment | `notes_class`, `fragment_class` ; la modulation `note` de n'importe quel bloc, et les directives Marp d'une note, lues par `CommentDirectives` |
| Les limites | `max_slides`, `max_source_bytes` |
| Les formats lus | Les formats et les extensions de `phil/markup` : `DeckReader` prend le service `DefaultCompiler` quand `phil/markup-bundle` en enregistre un |
| Couper en code | `Slicer::slice()`, ou `DeckReader` pour les documents, le JSON, les lectures et les sources |

## Le rendu

| Point | Comment |
|---|---|
| Les types de blocs, le HTML brut, les limites d'un document | La configuration de `phil_ujo` : chaque diapositive est rendue par son service `Ujo` |
| Un type de bloc propre à l'application | Enregistré auprès de `phil/ujo-bundle`, comme pour tous les documents |
| Le balisage | `@PhilSlides/deck.html.twig`, `presenter.html.twig`, `presenter_page.html.twig`, `layout.html.twig`, `print.html.twig` et `_slide.html.twig`, remplacés dans `templates/bundles/PhilSlidesBundle/` |
| Les boutons | La recette `Button` du kit que l'application a installé, PHIL UX ou Shadcn, sous le nom que donne `slides_kit('Button')` ; `kit_subdirectory` |
| Les libellés | Le domaine de traduction `phil_slides` : les traductions d'une application remplacent celles qui sont fournies |
| L'apparence | `assets/styles/slides.css`, ses classes `phil-slide*` et ses états `isX` ; les classes données par la directive `class` et par les blocs `pageStyle` |
| L'apparence imprimée | `assets/styles/slides-print.css`, ou les feuilles de style propres à l'application autour de `DeckPrint::body()` |

## Le comportement

| Point | Comment |
|---|---|
| Les touches | `keyboard: 'page'` sur la page de l'auditoire ; les contrôleurs étendent une même classe de base, `assets/src/lib/slides_controller.js`, dont `run()` reçoit une commande |
| Les fenêtres d'une présentation | L'identifiant de la présentation, donné par `Deck::withId()` ou par le dernier argument de `slides_deck()` et `slides_markup()` |
| Les présentations des routes du présentateur | Un service qui implémente `Phil\Slides\Application\Contract\PresentedDecks`, avec cette interface pour alias |
| L'accès aux notes | `presenter_role`, demandé par les routes du présentateur ; ou les routes propres à l'application, qui servent la vue du présentateur et le PDF avec notes à qui elle décide |
| La page de la route du présentateur | `layout`, un gabarit avec les blocs `title` et `main` ; ou `@PhilSlides/presenter_page.html.twig`, remplacé |

## L'impression

| Point | Comment |
|---|---|
| Le moteur d'impression | Le `PrintEngine` de `phil/paged-media-bundle` |
| Les étapes après l'impression | Le `Pipeline` donné à `DeckPdf::render()` |
| La page des notes | `pdf.notes_page_size` |
