<!-- phil-dev source-sha256=571f100ab063a278e8f0d2a4d20a6cced0734b3ae03ca98831a8933373f7079e -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Le kit | Le manifeste et l'icône du kit, chaque dossier une recette ou laissé hors de l'archive, le manifeste, le type, le nom, le README et les fichiers copiés de chaque recette, les recettes dont elle dépend, les contrôleurs que ses gabarits nomment, chaque prop documentée comme le demande le Toolkit |
| `Logo` | Une image seule, une source WebP avec son repli et un lien vers l'accueil, la taille et le chargement, le thème et la locale remplacés, l'échappement, `alt` requis |
| `AnimatedHeadline` | Un mot, un mot avec `loop` et sans bouton, plusieurs avec du texte autour, le bouton de pause et ses libellés, le libellé et le séparateur, le niveau gardé un titre, l'échappement |
| `Illustration` | Le germe qui choisit, les germes négatifs, chaque candidate tirée sans germe, une URL, les tailles, une légende, aucune candidate |
| `Image` et `Picture` | En différé et en asynchrone par défaut, `priority` et les choix qui l'emportent sur elle, les valeurs inconnues ramenées au défaut, les tableaux de densités et de largeurs, `sizes` après `auto,` en différé, une image décorative qui laisse de côté `title` et `aria-*`, la légende et sa `figure`, aucune pour une légende vide, gardée sous une image décorative, les attributs permis seuls, l'échappement, les URL `javascript:`, `vbscript:` et `data:` autres qu'images refusées sous tous leurs déguisements, `data:image/` permise, `src`, `alt`, `width` et `height` requis ; les sources dans leur ordre, par densité, par largeur avec direction artistique, dimensionnées automatiquement en différé, une source d'un type inconnu ou sans candidate laissée de côté, l'`Image` seule sans source |
| `AutosaveStatus` | La région active, l'avis caché, ses boutons avec leurs cibles, leurs actions et leurs libellés par défaut ; les libellés en props, échappés ; une `class` ajoutée |
| `ReactionButton` | Enfoncé ou non, avec un émoji ou une image et son texte de remplacement, le compteur et son libellé, aucun à zéro, du texte simple quand il ne peut pas être enfoncé, d'autres attributs sur la racine |
| `ReactionPicker` | Le dévoilement, la recherche cachée, les groupes, chaque bouton avec ses attributs et le texte cherché, les attributs hors de ceux permis laissés de côté, l'échappement, les libellés en props |
| `ComingSoon` | La page entière, ses repères, le bloc de contenu ; sans repères ni illustration |
| Le contrôleur `animated-headline` | Le mot suivant, les durées tenues dans le budget, un mot dévoilé une fois puis immobile, le déroulé par défaut joué une fois jusqu'au premier mot dans le budget, le mot courant du serveur, la boucle et son bouton de pause et de lecture, le survol et le focus qui retiennent le mot, `transitionend` et son repli après la durée, le document caché puis montré ou caché dès le début, le mouvement réduit activé et désactivé, le redimensionnement, la déconnexion, rien à piloter ; sous le lanceur de tests de Node |
| Les recettes du kit Shadcn | Chacune produite en entier : ses éléments, ses classes, ses rôles et ses attributs `aria`, ses attributs Stimulus, ses variantes et ses tailles, ses parties, une balise ou une couleur refusée, les textes en props |
| Les manifestes | Les props et les blocs de chaque gabarit documentés, aucun mot écrit dans un gabarit, les noms des composants et des contrôleurs littéraux, comme le Toolkit les réécrit pour un sous-dossier ; un contrôleur nommé par un gabarit fourni par sa recette ou par une recette dont elle dépend |
| Les recettes propres au kit | `CopyButton`, `AnimatedButton`, `Stepper`, `ActivityCalendar`, `Tree`, `Rating`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline`, `BeforeAfter`, `ColorPicker`, `DatePicker` : produites en entier, leurs props, leurs limites et l'échappement |
| Les recettes du kit Shadcn sur des éléments natifs | `AlertDialog` sans fermeture par l'arrière-plan, `AspectRatio` qui refuse tout sauf un nombre ou un quotient, `Collapsible`, `Field` et ses erreurs, `Item`, `Kbd`, `NativeSelect`, `Progress` tenu dans ses bornes, `RadioGroup`, `ScrollArea` une région seulement quand elle est nommée, `Toggle` et `ToggleGroup` sur des cases à cocher et des boutons radio : produites en entier |
| Les graphiques | `Chart` : l'échelle en pas simples des plus petites valeurs aux plus grandes, ses décimales, un graphique plat, des barres qui partagent leur bande, une ligne coupée à une valeur absente, un point seul, la légende, le tableau affiché ou lu par les lecteurs d'écran seulement, les étiquettes et les séries bornées ; `ChartJs` : sa figure et son tableau lu dans les données du graphique, `render_chart` fourni par un bouchon |
| Les blocs `LoginForm` et `SignupForm` | Produits en entier, seuls et après une soumission refusée : les noms des champs, l'autocomplétion, l'erreur liée aux champs, le champ CSRF, les liens écrits seulement avec leur adresse et leur texte, un mot de passe jamais réécrit |
| Les contrôleurs `tree`, `date-picker`, `color-picker`, `copy-button`, `number-input`, `file-input`, `tag`, `relative-time`, `before-after` | Le clavier et son miroir de droite à gauche, ce qu'ils écrivent, les limites, le tri des fichiers, le focus gardé tout près, les minuteries, sous le lanceur de tests de Node |
| Les contrôleurs `popover`, `tabs`, `accordion`, `dialog`, `tooltip`, `carousel`, `select`, `dropdown-menu`, `dropdown-menu-sub` | `placement()` de chacun, les touches et leur miroir de droite à gauche, ce qui est laissé au navigateur, l'état sur le déclencheur et le panneau, le focus, les délais, la lecture automatique retenue et en pause, le mouvement réduit, la déconnexion ; pour `dialog`, les invocateurs et `closedby` détectés, laissés au navigateur là où il les exécute et exécutés par le contrôleur là où il ne le fait pas ; sous le lanceur de tests de Node, sur le petit DOM de `tests/js/fake.mjs` |

Les composants sont produits par TwigComponent dans un noyau où le `templates/` de chaque recette est un chemin de Twig,
comme `ux:install` les copierait. Installé seul, là où FrameworkBundle est une dépendance de développement, la suite
fonctionnelle est sautée.

## Dans un navigateur

`tests/Browser` pilote chaque recette interactive dans un vrai Chrome avec `symfony/panther`, contre la feuille de
style, en clair, en sombre et de droite à gauche : `Accordion`, `AnimatedHeadline`, `Carousel`, `Dialog`, `Sheet`,
`DropdownMenu` et son sous-menu, `Popover`, `Select`, `Tabs`, `Tooltip` et sa flèche, `Switch`, `Tree`, `DatePicker`,
`ColorPicker`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `CopyButton`, `AlertDialog`, `Toggle`,
`ToggleGroup`, `RadioGroup`, `ScrollArea`, les couleurs et les tirets des séries d'un `Chart`, une `Picture` réduite à
un téléphone à son rapport, et les recettes et les
blocs du kit à la largeur d'un téléphone. Elle vérifie ce que seul un navigateur fait : la couche supérieure de l'API
popover et de `dialog`, la place à l'écran, le focus, Échap et les flèches, un clic sur l'arrière-plan d'un dialogue et
d'un dialogue d'alerte. Un dernier test vérifie que chaque partie d'une page qui contient toutes les recettes porte une
classe de la feuille.

La suite est à part de `composer test`, dans `phpunit.browser.dist.xml`, et tourne à la fin d'un lot :

```sh
composer test:browser
```

Elle a besoin de Chrome et du `chromedriver` du système dans le `PATH`, de la même version majeure que Chrome :
`brew install --cask chromedriver` sur macOS, `apt install chromium-driver` sur Debian. `PANTHER_NO_HEADLESS=1` montre
la fenêtre ; `phpunit.browser.dist.xml` pose `PANTHER_NO_REDUCED_MOTION`, pour que ce que la suite observe bouge. Sans
le pilote, la suite échoue avec le message de Panther. La page est écrite sous le dossier temporaire par `RecipesPage`,
avec le vrai `@hotwired/stimulus`, épinglé dans `tests/Browser/package.json` et son fichier de verrouillage et installé
par le script : une dépendance de développement de ces tests, que le kit ne publie pas.

## Pour un autre kit

`BrowserTestCase` et `RecipesPage` sont publiés dans `src/Test/`, sous `Phil\UxKit\Test`, pour les kits dont les
recettes font tourner les contrôleurs de celui-ci. Une suite étend le cas et dit d'où vient sa page :

```php
use Phil\UxKit\Test\BrowserTestCase;
use Phil\UxKit\Test\RecipesPage;

final class InteractiveRecipesTest extends BrowserTestCase
{
    protected static function recipesPage(): RecipesPage
    {
        return new RecipesPage(
            new TestKernel('test', true),             // an application where the kit's recipes are installed
            TestKernel::KIT_DIR,                      // the kit, whose */assets/controllers are copied
            __DIR__.'/resources',                     // its app.js and page.html.twig
            __DIR__.'/var/app.css',                   // its stylesheet
            sys_get_temp_dir().'/my-kit-browser',     // the web root written
            __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
        );
    }
}
```

La suite épingle son propre `@hotwired/stimulus` et exige, pour le développement, ce qu'emploient les classes :
`phpunit/phpunit`, `symfony/panther`, `php-webdriver/webdriver`, `symfony/http-kernel` et `twig/twig`, que
`phil/ux-kit` suggère. Chaque test ouvre alors l'un des `modes()` et pilote la page avec `open()`, `focus()`, `press()`,
`click()`, `clickBeside()`, un vrai clic sur l'arrière-plan à côté d'un élément, `read()`, `perform()`, `waitUntil()` et
`waitForFocus()`.

## Sans navigateur

```sh
vendor/bin/phpunit packages/ux-kit/tests/Unit packages/ux-kit/tests/Functional
node --import ./tests/js/register.mjs --test tests/js     # from packages/ux-kit
```

Le kit passe aussi le linter du Toolkit, `ux:toolkit:lint-kit`, lancé sur une copie des recettes seules, comme l'archive
les contient.
