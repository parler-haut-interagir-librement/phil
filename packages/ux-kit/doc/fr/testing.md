<!-- phil-dev source-sha256=cb6923a4537abd82ced2740c2d9e0dc6ddbfaf686d1ec0120735bfeb929b039c -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Le kit | Le manifeste et l'icône du kit, chaque dossier une recette ou laissé hors de l'archive, le manifeste, le type, le nom, le README et les fichiers copiés de chaque recette, les recettes dont elle dépend, les contrôleurs que ses gabarits nomment, chaque prop documentée comme le demande le Toolkit |
| `Logo` | Par `Picture` : une image seule, une source WebP avec son repli et un lien vers l'accueil, l'AVIF puis le WebP par densité, la taille et le chargement, le thème et la locale remplacés dans chaque URL, l'échappement, une URL refusée laissée de côté, `alt` requis |
| `AnimatedHeadline` | Un mot, un mot avec `loop` et sans bouton, plusieurs avec du texte autour, le bouton de pause et ses libellés, le libellé et le séparateur, le niveau gardé un titre, l'échappement |
| `Illustration` | Le germe qui choisit, les germes négatifs, chaque candidate tirée sans germe, une URL, les tailles, une légende, aucune candidate |
| `Image` et `Picture` | En différé et en asynchrone par défaut, `priority` et les choix qui l'emportent sur elle, les valeurs inconnues ramenées au défaut, les tableaux de densités et de largeurs, `sizes` après `auto,` en différé, une image décorative qui laisse de côté `title` et `aria-*`, la légende et sa `figure`, aucune pour une légende vide, gardée sous une image décorative, les attributs permis seuls, l'échappement, les URL `javascript:`, `vbscript:` et `data:` autres qu'images refusées sous tous leurs déguisements, `data:image/` permise, `src`, `alt`, `width` et `height` requis ; les sources dans leur ordre, par densité, par largeur avec direction artistique, dimensionnées automatiquement en différé, une source d'un type inconnu ou sans candidate laissée de côté, l'`Image` seule sans source ; les dispositions et le `sizes` qu'elles calculent, chaque rapport, l'ajustement et le recadrage, les valeurs inconnues laissées de côté ; la direction artistique, les sources à requête d'abord dans l'ordre de leurs types, une requête ou une taille de mauvaise forme |
| `Image` et `Picture` en 0.3 | Le préchargement de l'image principale par WebLink, ses attributs et son absence sans `priority`, avec `preload: false` ou avec une requête media ; les variantes sombre et fixe d'abord, en URL, en tableaux ou en sources de leurs propres types ; le crédit, la licence et son lien, la création dans les mots du vocabulaire ou du site, d'après un code ou une énumération ; une image d'un hôte listé demandée sans référent, une d'un autre hôte refusée, l'origine même de la page ; `image_sources()` sur les ressources d'AssetMapper |
| `Image` et `Picture` en 0.4 | `deferred` et `fetchpriority="low"`, `priority` l'emportant ; la classe de chaque couleur d'attente, une inconnue ne peignant rien ; un aperçu remis à son contrôleur à côté de celui de la page, seule une toute petite image matricielle en ligne en base 64 gardée ; la variante des couleurs forcées d'abord et jamais préchargée ; la description dans son dévoilement avant la légende, échappée, décrivant l'image à côté d'une description de la page, son identifiant tiré de celui de l'image ou propre, une image décorative la montrant sans être décrite ; `Gallery`, `Lightbox` et `Logo` qui les portent ; les avertissements d'`image_check()` en débogage, aucun quand il est coupé ou sans débogage ; la priorité, la disposition, le rapport, l'ajustement et le recadrage de `Logo`, portés par `ComingSoon` avec la taille de son logo et le chargement de son illustration |
| Les images en 0.5 | Une légende posée sur son image, la classe de sa figure, dessous par défaut et rien de posé sans légende ; `Gallery` en carrousel, sa liste atteinte au clavier, les noms de ses marqueurs et de ses boutons, la place de ses légendes ; `Hotspots`, chaque point un lien placé par ses classes aux cinq pour cent près et par ses attributs de données, gardé sur le bord, un point sans mots, sans adresse ou sans place ou avec une adresse refusée écarté, les sources et le préchargement de l'image, une image refusée ; `Hero`, son image principale de la page, préchargée, couvrante, ses tailles et ses sources, ses hauteurs et ses alignements, paresseuse sous la ligne de flottaison, une image refusée qui laisse le contenu ; `Video`, son affiche, ses sources et ses pistes, rien de demandé et aucune lecture automatique, une source, une piste ou une affiche refusée écartée, sa transcription qui la décrit, rien sans source ; `Figure`, ses images sous une légende, un crédit, une licence et une création, ses colonnes, la grille seule sans eux |
| `Gallery` et `Lightbox` | La liste nommée par son libellé, ses figures, la visionneuse de chaque image et leurs identifiants, les tailles de la grille, une image refusée qui ne prend aucune place ; la vignette dans son bouton avec les invocateurs, le dialogue et son bouton de fermeture, l'image entière montrée en entier, la légende sous les deux, les libellés en props, une image décorative nommée par sa légende, une image refusée, `id` requis |
| `image_origin()` et `image_sources()` | `ImageOrigin` : les chemins relatifs, les images en ligne et l'origine de la page locaux, les hôtes de la liste et ceux qui sont sous eux externes, tout autre hôte, des identifiants, une adresse IP, `localhost` sauf permission et tout autre schéma refusés, un `srcset` coupé à chaque espace et à chaque virgule, aucun hôte caché derrière une virgule, et jugé sur son pire morceau ; `ImageSources` : les formats et les densités d'une image, un fichier seul sans descripteur, les largeurs, les formats et les replis choisis, la taille du repli, un chemin refusé, aucun repli |
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
| Le contrôleur `image-preview` | Seule une toute petite image matricielle en ligne gardée, son flou dessiné dans un SVG en ligne au rapport de l'image, peint par le CSSOM et retiré au chargement, à l'erreur ou à la déconnexion, une image déjà chargée ou en échec laissée tranquille ; sous l'exécuteur de tests de Node |
| `ImageTextCheck` | Le nom du fichier sous toutes ses formes, par `phil/url`, les mots génériques et les débuts redondants en anglais et en français, une largeur ou une hauteur absente ou de mauvaise forme, chaque message |
| Le contrôleur `lightbox` | Laissé au navigateur là où il exécute les invocateurs et `closedby`, qui ouvre et ferme le dialogue une fois là où il ne le fait pas, un clic sur l'arrière-plan qui le ferme et un sur l'image qui ne le ferme pas ; sous l'exécuteur de tests de Node |
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
un téléphone à son rapport et une autre qui choisit son recadrage selon la largeur de l'écran, une `Image` gardée
carrée, couverte et rognée, une `Gallery` dont les colonnes suivent son conteneur, une `Lightbox` ouverte depuis sa
vignette et fermée par Échap et par son bouton, une `Picture` qui suit le schéma de couleurs et le mouvement du système,
émulés par le protocole DevTools, et les recettes et les blocs du kit à la largeur d'un téléphone. Elle vérifie ce que
seul un navigateur fait : la couche supérieure de l'API popover et de `dialog`, la place à l'écran, le focus, Échap et
les flèches, un clic sur l'arrière-plan d'un dialogue et d'un dialogue d'alerte. axe-core audite les images de la page,
la galerie, une visionneuse ouverte et l'image avec ses variantes, selon les règles WCAG 2.2 A et AA, dans chaque mode,
et l'image avec sa variante des couleurs forcées, sa couleur d'attente et sa description, l'image avec son aperçu et la
galerie différée, une de ses visionneuses ouverte. La suite vérifie qu'une galerie différée garde ses figures hors de
l'écran à leur rapport et son anneau de focus entier, qu'un aperçu est peint jusqu'à l'arrivée de son image, et qu'une
image montre sa variante là où les couleurs sont forcées. axe-core audite aussi ce qu'ajoute la 0.5, dans chaque mode :
une légende sur son image, une galerie en carrousel, une image avec ses points, un héros, une vidéo avec ses
sous-titres et sa transcription, et une figure de plusieurs images. La suite vérifie que la légende repose sur le pied
de son image, que le carrousel est une seule rangée qui s'aimante et défile aux flèches, ses boutons nommés, que chaque
point se tient à sa place, la même de droite à gauche, et sous l'image sur un téléphone, que l'image d'un héros le
couvre, qu'une vidéo ne demande rien avant sa lecture, garde son rapport sur un téléphone et lit son fichier, et qu'une
figure dispose ses images en colonnes au-dessus de sa légende. Un dernier test vérifie que chaque partie d'une page qui
contient toutes les recettes porte une classe de la feuille.

La suite est à part de `composer test`, dans `phpunit.browser.dist.xml`, et tourne à la fin d'un lot :

```sh
composer test:browser
```

Elle a besoin de Chrome et du `chromedriver` du système dans le `PATH`, de la même version majeure que Chrome :
`brew install --cask chromedriver` sur macOS, `apt install chromium-driver` sur Debian. `PANTHER_NO_HEADLESS=1` montre
la fenêtre ; `phpunit.browser.dist.xml` pose `PANTHER_NO_REDUCED_MOTION`, pour que ce que la suite observe bouge. Sans
le pilote, la suite échoue avec le message de Panther. La page est écrite sous le dossier temporaire par `RecipesPage`,
avec les fichiers de `tests/Browser/resources/media`, un extrait WebM de deux secondes et ses sous-titres WebVTT, le
vrai `@hotwired/stimulus`, épinglé avec `axe-core` dans `tests/Browser/package.json` et son fichier de
verrouillage et installé par le script : des dépendances de développement de ces tests, que le kit ne publie pas.

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
            __DIR__.'/resources',                     // its app.js, page.html.twig and media/
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
