<!-- phil-dev source-sha256=527db29b309b0de3f591979d86238511157e8200a6b42e758b33b9e5d7f331fd -->
# phil/comments-bundle

**Des commentaires sur une page, et sur des passages d'un texte qu'ils suivent quand il change, avec des remplacements suggérés.**

phil/comments-bundle permet aux lecteurs de discuter les mots d'un document là où ils se trouvent. Un lecteur
sélectionne un passage — dans un document UJO (Universal JSON Output), à l'intérieur d'un champ d'un bloc — et y ouvre
un fil, avec un commentaire et, s'il le souhaite, le texte qui devrait remplacer le passage. D'autres répondent ;
quelqu'un que l'application autorise résout le fil, ou accepte ou rejette la suggestion. Le fil est ancré au bloc, au
champ et aux positions des caractères du passage, et garde le passage lui-même avec quelques caractères de chaque côté.
Quand le texte change, le passage est retrouvé par son texte exact et son contexte : dans son champ d'abord, puis dans
son bloc, puis à une autre occurrence des mêmes mots. Quand ils n'apparaissent nulle part, le fil devient orphelin, le
dit à l'écran, puis est rattaché de nouveau s'ils reviennent. À côté d'eux, les lecteurs commentent la page entière,
sous la même politique, avec les mêmes réponses et les mêmes états. Dans le navigateur, un contrôleur Stimulus travaille
avec l'éditeur par blocs de `phil/block-editor-bundle`. Il propose de commenter une sélection, liste les fils à côté de
l'éditeur, surligne leurs passages pendant que l'auteur tape, applique à l'éditeur une suggestion acceptée et se termine
par les commentaires sur la page et leur formulaire ; sur une page sans éditeur, il montre les commentaires sur la page
seuls. Là où une page n'a besoin d'aucun script, un composant Twig dessine les commentaires sur la page côté serveur,
avec les recettes du kit PHIL UX, et chaque action est un formulaire simple.

Il sert à toute application Symfony où des textes sont révisés : un flux éditorial, une ébauche partagée, les règles
d'un jeu en relecture, un contrat en négociation, le procès-verbal d'une réunion. L'application nomme ses ressources
et ses utilisateurs comme des références de `phil/resource` (`article:42`, `user:7`). Elle dit qui peut lire,
commenter, résoudre, décider et modérer, et signale au bundle qu'un document a changé ; le bundle garde les fils et
retrouve leurs passages. Un texte simple, hors de tout bloc, se commente de la même façon.

C'est un bundle Symfony sur `phil/resource`, `phil/ujo` et Doctrine ORM : les fils et les commentaires vivent dans
deux tables, ou en mémoire pour les tests. Les points de terminaison ne répondent en JSON qu'au contrôleur. Le
contrôleur et sa feuille de style sont servis par AssetMapper sans étape de construction et ne sont chargés que sur les
pages qui les utilisent, et le surlignage utilise la CSS Custom Highlight API du navigateur, qui ne touche jamais au
balisage de l'éditeur. Derrière une option, les commentaires et les suggestions prennent les rôles `comment` et
`suggestion` de WAI-ARIA 1.3. Les outils d'annotation et de révision servent de modèles de comportement.

## État

| Partie | État |
|---|---|
| `Anchor`, `Passage`, `InlineHtml` : où se trouve un passage, dans quel texte, tel qu'un navigateur le lit | Implémenté et testé |
| `Reanchoring` : un passage retrouvé par son texte exact et son contexte, à une autre occurrence, ou orphelin | Implémenté, testé, mesuré par banc d'essai et soumis au fuzzing |
| `Thread`, `Comment` : les fils sur un passage ou sur la page entière, les commentaires, résoudre et rouvrir, les suggestions et les décisions | Implémenté et testé |
| `Comments` : le point d'entrée de l'application, avec sa politique, ses événements, le réancrage et les suggestions appliquées aux documents UJO | Implémenté et testé |
| `UjoPassages` : les textes d'un document UJO et leurs chemins, un passage remplacé dans le document | Implémenté et testé |
| Les stockages Doctrine et en mémoire | Implémenté et testé, sur SQLite ; le stockage en mémoire est vidé entre deux requêtes |
| `CommentsController`, les routes, `ClientConfig` : les points de terminaison, commentaires sur la page compris, et la configuration du contrôleur | Implémenté et testé |
| `PageCommentsComponent`, `PageController` : les commentaires sur une page entière dessinés par le serveur avec les recettes du kit, des formulaires simples sans JavaScript | Implémenté et testé, dans une application avec le kit |
| Les rôles `comment` et `suggestion` de WAI-ARIA 1.3, `aria-details` d'un passage vers ses commentaires | Implémenté et testé, derrière l'option `aria_roles` |
| L'autocomplétion `@` de `phil/mentions-bundle` dans les zones de texte du panneau et de la page | Implémenté et testé |
| Le contrôleur `comments` : la sélection, le panneau, le surlignage, l'acceptation dans l'éditeur par blocs, les commentaires sur la page | Implémenté ; modules purs testés sous Node, vérifications dans un navigateur listées dans [les tests](doc/fr/testing.md) |
| Les mentions dans les commentaires et leur notification | Avec `phil/mentions-bundle`, qui lit les événements du bundle |
| Les mises à jour poussées | Hors du bundle : le contrôleur charge les fils, et les recharge à intervalle quand on le lui demande |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, les tables, les services de l'application, les routes, le contrôleur et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les services enregistrés et les interfaces qu'écrit l'application. |
| [Ancrage](doc/fr/anchoring.md) | Les ancres, les positions des caractères, les champs des blocs UJO, retrouver un passage, suivre un document. |
| [Fils](doc/fr/threads.md) | Les auteurs et les ressources, les fils sur un passage et sur la page, la vie d'un fil, les commentaires, les événements, la politique, les noms. |
| [Suggestions](doc/fr/suggestions.md) | Les remplacements proposés, les décisions, et le passage remplacé par l'application ou dans l'éditeur. |
| [La page](doc/fr/page.md) | Les commentaires sur une page entière dessinés par le serveur, leurs formulaires simples, les recettes du kit. |
| [Points de terminaison](doc/fr/endpoints.md) | Chaque route, son corps, ses réponses, les erreurs et la configuration du contrôleur. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, sa configuration, ce qu'il fait, ses modules et la feuille de style. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, le seuil de mutation et les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/comments-bundle
```

```yaml
# config/packages/phil_comments.yaml
phil_comments:
    author_provider: App\Review\CurrentAuthor   # an AuthorProvider
    policy: App\Review\ReviewPolicy             # a CommentPolicy
```

```twig
<div data-controller="phil--comments-bundle--comments"
     data-phil--comments-bundle--comments-config-value="{{ comments|json_encode }}">
    {{ form_row(form.body) }}  {# the block editor of phil/block-editor-bundle #}
    <aside data-phil--comments-bundle--comments-target="panel"></aside>
</div>
```

`comments` vaut `ClientConfig::for(ResourceReference::of('article', '42'), $author)`, fourni par le contrôleur de la
page. Sous un article publié, sans aucun script :

```twig
<twig:PhilComments:Page subject="article:42" />
```

Après avoir enregistré le document, l'application déplace les fils avec lui :

```php
$comments->reanchorDocument(ResourceReference::of('article', '42'), $document);
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3, `phil/resource` et `phil/ujo`. Pour le contrôleur, Stimulus 3
et `phil/block-editor-bundle`. Pour les commentaires sur la page dessinés par le serveur, Symfony UX Twig Component 3.7
et les recettes du kit PHIL UX.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
