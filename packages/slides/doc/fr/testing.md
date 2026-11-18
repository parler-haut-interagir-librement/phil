<!-- phil-dev source-sha256=a6087a30d39b7769d3a5cd61ea2d05ce866c4165b629d917a057f71582ddbbe3 -->
# Tests

| Zone | Ce qui est couvert |
|---|---|
| `Slicer`, `SlideDraft` | Chaque mode de découpage et chaque niveau de titre, `headingDivider`, les styles et les numéros de page, les diapositives vides, les titres, les notes imbriquées n'importe où et leurs modulations, l'identifiant, les limites de diapositives et de profondeur, les options |
| `Directives` | Chaque directive, les valeurs refusées et nommées, les limites |
| `CommentDirectives`, les notes tirées des commentaires HTML | Les directives d'une diapositive et celles des suivantes, celles qui ne sont pas conservées, les notes qui restent des notes, les notes d'un séparateur ou d'un titre qui commence une diapositive, le numéro d'une diapositive ; les commentaires Markdown d'un `phil/markup` qui les conserve |
| `Deck`, `Slide`, `DeckSettings`, les énumérations et les exceptions | Les recherches, les notes, les ancres, les identifiants, les pages, les rapports, les messages |
| `DeckReader`, `SlideRenderer` | Les documents, le JSON, les lectures et les sources en plusieurs formats et niveaux, la limite de taille ; le contenu sans notes, les notes en blocs et en textes, les règles propres au moteur de rendu |
| `SlidesExtension` et les gabarits | La page de l'auditoire sans aucune note, la vue du présentateur avec elles, l'impression avec et sans, les options de chacun, l'anglais et le français ; les noms des recettes du kit |
| Les kits | La page de l'auditoire et la vue du présentateur dessinées avec la recette `Button` de PHIL UX et de Shadcn : les mêmes boutons et liens, libellés, actions, cibles, états, variantes et tailles dans les deux, chacun avec l'apparence de son kit |
| `PresenterController`, les routes | Le rôle demandé d'abord, un visiteur refusé (403), une personne non connectée invitée à se connecter (401), tous refusés sans vérificateur d'autorisation, une présentation que personne n'a (404), le rôle configuré, le PDF et ses en-têtes, sans `phil/paged-media-bundle` |
| La recette | Le manifeste, la configuration acceptée par le bundle, l'import des routes laissé en commentaire |
| `DeckPrint`, `DeckPdf` | Les règles `@page`, le corps, le document complet, un moteur d'impression à qui la page est donnée, le pipeline |
| `PhilSlidesBundle`, `SlidesServicesPass` | Les services et la configuration, les options refusées, les ressources associées, la passe avec et sans Twig, un routeur, `phil/paged-media-bundle` et le service `Ujo`, le sous-répertoire du kit |
| Les contrôleurs Stimulus | Les touches, les positions et les fragments, l'adresse, le plein écran, les messages du `BroadcastChannel`, la déconnexion, la diapositive suivante, la minuterie, les fenêtres ouvertes, sous l'exécuteur de tests de Node |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle en `fr_CA` avec AssetMapper, TwigBundle,
TwigComponentBundle avec la recette `Button` copiée là où le Toolkit la met — depuis PHIL UX dans le sous-répertoire
`ui`, ou depuis le kit Shadcn à la racine des composants dans l'environnement `shadcn` —, SecurityBundle avec des
utilisateurs sous HTTP Basic, `phil/ujo-bundle`, `phil/paged-media-bundle` avec un moteur d'impression qui rend le
document tel quel, et ce bundle avec ses routes du présentateur : `SlidesBundleTest` rend les deux pages en français,
imprime une présentation avec et sans ses notes, et trouve les contrôleurs dans AssetMapper ; `TemplatesTest` rend les
gabarits avec les deux kits ; `PresenterRoutesTest` demande les routes du présentateur en tant que présentateur,
visiteur et personne non connectée. Installée seule, là où FrameworkBundle est une exigence de développement, la suite
est ignorée.

Une cible de fuzzing, `comment-directives`, lit comme des directives des notes que personne n'a écrites et en coupe une
présentation : `composer fuzz comment-directives`.

Un banc d'essai mesure un document de deux cents diapositives coupé, la même présentation lue depuis Markdown, et
chaque diapositive rendue avec ses notes.

```sh
vendor/bin/phpunit packages/slides/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/slides
```

Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.
