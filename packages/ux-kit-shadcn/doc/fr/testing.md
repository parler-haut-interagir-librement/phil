<!-- phil-dev source-sha256=845035eba8b9d3d730d5fe38de56a3c5dac3b79d780eb654daeb1762fe199baf -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Le kit | Le manifeste et l'icône du kit, chaque répertoire, recette ou laissé hors de l'archive, le manifeste, le README et les fichiers copiés de chaque recette, les recettes dont elle dépend, les contrôleurs que nomment ses gabarits, chaque propriété documentée comme le demande le Toolkit |
| La parité | Les mêmes recettes et manifestes que le kit PHIL UX, les mêmes propriétés, blocs et attributs Stimulus, le même fichier de contrôleur ; la recette `button` du kit Shadcn, dont dépend le `Carousel` du kit PHIL UX, dessinée en ligne ici ; face au kit PHIL UX installé parmi les dépendances de développement |
| L'installation | `ux:install` exécuté par le Toolkit sur l'archive du kit, avec les composants et les contrôleurs dans le sous-répertoire `ui` : les fichiers du carrousel dessous, ses identifiants préfixés dans le balisage et dans une table Twig, ceux du contrôleur d'un bundle laissés tels quels |
| Les recettes | Le rendu de chacune par TwigComponent avec ses utilitaires : un logo selon le thème et par densité par sa picture, son `alt` requis, les mots et les états d'un titre, une illustration selon le germe, une `Image` et une `Picture` à sources AVIF et WebP, par densité et par largeur, décorative, légendée, disposée, avec un rapport, un ajustement et un recadrage, en direction artistique, `priority`, ses attributs triés et ses URL refusées, ses variantes sombre et fixe, son crédit, sa licence et sa création, une image d'un autre hôte refusée et une d'un hôte listé demandée sans référent, une `Image` et une `Picture` structurées, décrites en JSON-LD avant elles, une galerie sur une requête de conteneur, une visionneuse sur le dialogue natif, une image différée avec sa couleur d'attente et son aperçu, une variante des couleurs forcées, une description longue, une galerie différée, une légende sur son image, une galerie en carrousel, une image avec ses points, un héros, une vidéo avec ses pistes et sa transcription, plusieurs images sous une légende, les cadres, le jeton, le pixel art et le nom d'une transition de vue, portés par une galerie, une visionneuse et une image avec ses points, jamais un jeton là, les utilitaires du papier et de la droite à gauche, jamais un style, le statut d'un brouillon et ses boutons, la page « bientôt » entière, les séries d'un graphique dans les jetons de graphique et leurs tirets, les erreurs des blocs de connexion et d'inscription, un carrousel et ses boutons dans chaque variante et chaque taille du bouton du kit, son bouton de pause quand il joue ; aucune classe de phil-ux dans aucune d'elles |

Les tests propres au contrôleur sont ceux du kit PHIL UX, le fichier étant le même.

```sh
vendor/bin/phpunit packages/ux-kit-shadcn/tests/Unit packages/ux-kit-shadcn/tests/Functional
```

## Dans un navigateur

`tests/Browser` pilote les recettes sur lesquelles agit un utilisateur dans un vrai Chrome avec `symfony/panther`, avec
une feuille de style compilée par Tailwind, en clair, en sombre et de droite à gauche : `AnimatedHeadline`, `Tree`, un `Carousel` défilé d'une diapositive à son bouton et à sa touche fléchée, ses boutons désactivés à chaque bout, `NumberInput`,
`ColorPicker`, `Tag`, `FileInput`, `BeforeAfter`, `Rating`, `RelativeTime`, `CopyButton`,
une `Celebration` jouée par son bouton et par l'événement DOM, son message dit, son canvas sur la page au-dessus d'elle
qui laisse passer le pointeur, dessiné puis retiré, rien de dessiné en mouvement réduit, auditée par axe-core pendant
qu'elle joue,
les couleurs et les tirets des séries d'un `Chart`, une galerie différée dont les figures hors de l'écran gardent leur
rapport, l'aperçu d'une image peint jusqu'à son arrivée, les images de la 0.5, auditées par axe-core selon les règles
WCAG 2.2 A et AA dans chaque mode, la légende sur le pied de son image, le carrousel en une seule rangée qui défile aux
flèches et ses boutons nommés, chaque point à sa place, le héros couvert par son image, la vidéo à son rapport et la
figure dans ses colonnes, les cadres, le jeton, le pixel art et une transition de vue qui déplace une image sous son
nom, les images sur papier, le support émulé, et de droite à gauche, une légende, une galerie et un carrousel qui
commencent à droite, le bouton du début du carrousel qui pointe vers la droite et le bouton de fermeture d'une
visionneuse dans le coin en haut à gauche, et les recettes et les blocs à la largeur d'un téléphone. Chaque test des
images et de la célébration tourne aussi sous la politique de sécurité du contenu stricte de `RecipesPage`, sur les pages
`strict.html`, `strict-dark.html` et `strict-rtl.html`, et échoue à la première chose que le navigateur refuse ; un test
n'y trouve rien de refusé pendant que les recettes s'affichent, puis voit un attribut `style` refusé. Le
balisage et ses utilitaires sont propres à ce kit alors que les contrôleurs sont partagés : la suite vérifie donc ce que
les deux font ensemble, c'est-à-dire ce qu'un utilitaire compilé cache, montre, coupe ou peint une fois qu'un
contrôleur a changé la page.

`TailwindStylesheet` compile `tests/Browser/resources/app.css`, qui contient des jetons des noms que lisent les
recettes. La page et le navigateur viennent de `Phil\UxKit\Test\BrowserTestCase` et de `Phil\UxKit\Test\RecipesPage`,
que le kit PHIL UX publie dans son `src/Test/`. Les médias de la page, un extrait WebM de deux secondes et ses
sous-titres WebVTT, sont dans `tests/Browser/resources/media`. Tailwind, tw-animate-css, `@hotwired/stimulus` et
axe-core sont épinglés dans `tests/Browser/package.json` et son fichier de verrouillage : des dépendances de
développement de ces tests, hors de l'archive du kit.

Chaque disposition des images est comparée à une capture de référence de `tests/Browser/screenshots/`, par
`BrowserTestCase::assertLooksLike()` du kit PHIL UX : une image `fixed`, `constrained`, `full-width` et recadrée à un
rapport, une galerie en grille et une figure de trois colonnes, en aplats de couleur sans texte, dans une boîte `w-full
max-w-[480px]`, dans la tolérance de `Phil\UxKit\Test\Screenshots`. Les références sont propres à ce kit, dont les
utilitaires dessinent les dispositions ; après un changement voulu, `PHIL_UX_UPDATE_SCREENSHOTS=1` les écrit de nouveau,
à relire dans le diff. La comparaison emploie GD, `ext-gd`, que la racine du monodépôt exige.

La suite est à part des autres, dans `phpunit.browser.dist.xml`, et a besoin de Chrome et du `chromedriver` du
système :

```sh
composer test:browser
```

## Le linter

Le *linter* du Toolkit, `vendor/bin/ux-toolkit-kit-lint`, exécuté sur une copie des seules recettes, ne signale aucune erreur. Ses
avertissements concernent les dépendances que déclare un manifeste, et les manifestes sont ceux du kit PHIL UX.
