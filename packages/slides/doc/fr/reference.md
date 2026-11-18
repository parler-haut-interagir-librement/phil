<!-- phil-dev source-sha256=18400d1964399178e19d1a9e4ee0a38b923a4eb8b22784122d0823825412ffdf -->
# Référence

Chaque type du bundle, avec son rôle.

| Type | Rôle |
|---|---|
| `PhilSlidesBundle` | Le bundle : la configuration `phil_slides`, les services, les ressources associées à `@phil/slides`, `DEFAULT_PRESENTER_ROLE` |
| `Domain\Slicer` | Coupe un document en présentation : séparateurs, titres, styles et numéros de page, notes retirées, limites |
| `Domain\Directives` | Les directives globales d'une présentation lues dans les métadonnées de sa source, et celles qui ne sont pas conservées |
| `Domain\CommentDirectives` | Les directives Marp d'une seule diapositive écrites dans une modulation `note` — un commentaire HTML —, et celles qui ne sont pas conservées |
| `Domain\SplitMode` | Où un document est coupé : `auto`, `separators`, `headings`, `both` |
| `Domain\AspectRatio` | La forme d'une diapositive, `16:9` ou `4:3`, sa page imprimée et son rapport CSS |
| `Domain\NotesPageSize` | La page verticale d'une diapositive imprimée avec ses notes, `letter` ou `a4` |
| `Domain\SlideDraft` | Interne : la diapositive que le découpeur est en train de remplir |
| `Domain\ValueObject\Deck` | Les diapositives d'une présentation, ses réglages, son identifiant, les directives non conservées |
| `Domain\ValueObject\Slide` | Une diapositive : son numéro, son contenu, ses notes, ses classes, sa pagination, son titre ; `showsNumber()` |
| `Domain\ValueObject\DeckSettings` | Ce qui s'applique à chaque diapositive : titre, langue, classes, pagination, en-tête, pied de page, forme |
| `Domain\Exception\SlidesException` | L'interface de chaque exception du bundle |
| `Domain\Exception\DeckRefused` | Trop de diapositives, imbrication trop profonde, source trop grande |
| `Domain\Exception\InvalidOption` | Une option hors de ses limites : un niveau de titre, une limite, une classe, un identifiant |
| `Application\DeckReader` | Des présentations à partir d'un document, de son JSON, d'une lecture de `phil/markup` ou d'une source |
| `Application\SlideRenderer` | Le HTML d'une diapositive sans ses notes, et de ses notes à part, par le service `Ujo` |
| `Application\Contract\PresentedDecks` | Les présentations que montrent les routes du présentateur, trouvées par identifiant : l'application l'implémente |
| `Application\Presentation` | Une présentation telle que la route du présentateur la montre : la présentation, l'adresse de sa page d'auditoire, la durée de la conférence |
| `Application\NoPresentedDecks` | Les présentations tant que l'application n'a pas nommé les siennes : aucune |
| `Infrastructure\Http\PresenterController` | Les routes du présentateur : la vue du présentateur et le PDF avec les notes, pour le rôle configuré seulement |
| `Infrastructure\Twig\SlidesExtension` | Les fonctions Twig `slides_deck`, `slides_markup`, `slides_content`, `slides_notes`, `slides_fragment_class`, `slides_kit` |
| `Infrastructure\Print\DeckPrint` | Le corps, les feuilles de style et le document d'une présentation imprimée |
| `Infrastructure\Print\DeckPdf` | Le PDF d'une présentation par `phil/paged-media-bundle` |
| `Infrastructure\DependencyInjection\SlidesServicesPass` | Arrête le conteneur sans le service `Ujo` ; retire l'impression et les routes du présentateur sans Twig, le PDF sans `phil/paged-media-bundle`, les routes du présentateur sans routeur ; nomme les recettes du kit d'après le sous-répertoire du Toolkit |

## Fonctions Twig

| Fonction | Donne |
|---|---|
| `slides_deck(document, directives = {}, id = null)` | La présentation d'un `Document`, de son JSON UJO, ou d'une `Reading` de `phil/markup` |
| `slides_markup(source, format = 'markdown', id = null)` | La présentation d'une source lue par `phil/markup` |
| `slides_content(slide)` | Le HTML que l'auditoire voit d'une diapositive |
| `slides_notes(slide)` | Le HTML des notes d'une diapositive, vide s'il n'y en a pas |
| `slides_fragment_class()` | La classe des fragments, tirée de la configuration |
| `slides_kit(recipe)` | Le nom sous lequel une recette du kit est installée : `ui:Button` avec le sous-répertoire de composants `ui` |

## Routes

`config/routes.php`, importé par l'application quand elle les veut (voir [Vue du présentateur](presenter.md)) :

| Route | Chemin | Contrôleur |
|---|---|---|
| `phil_slides_presenter` | `/{deck}` | `PresenterController::view` |
| `phil_slides_presenter_notes_pdf` | `/{deck}/notes.pdf` | `PresenterController::notesPdf` |

## Gabarits

| Gabarit | Ce qu'il dessine |
|---|---|
| `@PhilSlides/deck.html.twig` | La page de l'auditoire, avec le contrôleur `phil--slides--deck` |
| `@PhilSlides/presenter.html.twig` | La vue du présentateur, avec le contrôleur `phil--slides--presenter` |
| `@PhilSlides/presenter_page.html.twig` | La page de la route du présentateur : la vue du présentateur dans la mise en page `phil_slides.layout` |
| `@PhilSlides/layout.html.twig` | La page nue que la route du présentateur étend quand l'application ne nomme aucune mise en page |
| `@PhilSlides/print.html.twig` | Le corps d'une présentation imprimée |
| `@PhilSlides/_slide.html.twig` | Une diapositive, partagée par les trois |

## Ressources

| Fichier | Rôle |
|---|---|
| `assets/src/deck_controller.js` | Le contrôleur de l'auditoire |
| `assets/src/presenter_controller.js` | Le contrôleur du présentateur |
| `assets/src/lib/slides_controller.js` | Leur base : position, touches, région dynamique, `BroadcastChannel` |
| `assets/src/lib/deck.js` | La logique sans le DOM : positions, touches, messages, horloges |
| `assets/styles/slides.css` | L'apparence à l'écran et imprimée depuis le navigateur |
| `assets/styles/slides-print.css` | La mise en page d'une présentation imprimée par `DeckPrint` |
