<!-- phil-dev source-sha256=231930885c24da2c747698aa3516a62de585cb1d10dc74bec00548d8b1546f66 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\ValueObject\Anchor` | Où se trouve un passage — bloc, champ, positions des caractères — et ce qu'il dit, avec son contexte |
| `Domain\ValueObject\Suggestion` | Le remplacement que propose un fil, et où en est la décision à son sujet |
| `Domain\ValueObject\Member` | La référence d'un auteur ou d'un acteur telle qu'elle est gardée : un identifiant stable, jamais une adresse de courriel |
| `Domain\Anchoring\Passage` | Un texte où les passages sont cherchés : un champ d'un bloc, ou un texte simple |
| `Domain\Anchoring\Reanchoring` | Retrouve un passage dans des textes qui ont changé |
| `Domain\Anchoring\InlineHtml` | Le texte du HTML en ligne, et un de ses passages remplacé en gardant ses balises |
| `Domain\Model\Thread` | Une discussion sur un passage ou sur la page entière : sa place, son état, sa suggestion ; table `phil_comment_thread` |
| `Domain\Model\Comment` | Un message d'un fil ; table `phil_comment` |
| `Domain\Status\ThreadStatus` | `open`, `resolved` |
| `Domain\Status\SuggestionState` | `pending`, `accepted`, `rejected` |
| `Domain\Repository\ThreadRepository`, `CommentRepository` | Les contrats des stockages |
| `Domain\Exception\CommentsException`, `InvalidComment` | Les exceptions du bundle ; voyez [les exceptions](exceptions.md) |

## Application

| Classe | Rôle |
|---|---|
| `Application\Comments` | Les fils ouverts sur des passages et sur la page, répondus, modifiés, supprimés, résolus, rouverts, décidés, appliqués et réancrés |
| `Application\Event\CommentWritten`, `CommentDeleted` | Un commentaire écrit ou modifié, un commentaire supprimé, signalés au répartiteur d'événements |
| `Application\Discussion` | Un fil avec ses commentaires |
| `Application\UjoPassages` | Les textes d'un document UJO, et un passage qui y est remplacé |
| `Application\CommentPolicy`, `DefaultCommentPolicy` | Qui peut faire quoi |
| `Application\AuthorProvider` | Au nom de qui la requête courante commente |
| `Application\AuthorDirectory` | Les noms affichés pour les auteurs |
| `Application\Exception\NotFound`, `CommentRefused` | Un fil ou un commentaire absent ; un refus de la politique |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Doctrine\DoctrineThreadRepository`, `DoctrineCommentRepository` | Les stockages sur le gestionnaire d'entités |
| `Infrastructure\Memory\InMemoryThreadRepository`, `InMemoryCommentRepository` | Les stockages en mémoire, pour les tests, vidés entre deux requêtes (`kernel.reset`) |
| `Infrastructure\Http\CommentsController` | Les points de terminaison |
| `Infrastructure\Http\CommentsRequest` | Si une requête vient du contrôleur de ce site : son en-tête, le `Sec-Fetch-Site` du navigateur et le jeton CSRF sans état `phil_comments` sauf pour une méthode sûre ; le nom du témoin du jeton |
| `Infrastructure\Http\DiscussionJson` | Un fil tel que les points de terminaison le répondent, pour une personne qui consulte |
| `Infrastructure\Http\ClientConfig` | La configuration du contrôleur pour une page ; si les rôles de WAI-ARIA 1.3 sont écrits ; l'adresse de la recherche de membres |
| `Infrastructure\Http\PageForm` | L'adresse signée des formulaires simples d'une page, et leur vérification CSRF sans état |
| `Infrastructure\Http\PageController` | Les formulaires simples des commentaires sur une page : l'action exécutée, le retour à la page ou une page qui dit ce qui l'a arrêtée |
| `Infrastructure\Twig\Components\PageCommentsComponent` | `PhilComments:Page`, les commentaires sur une page entière dessinés par le serveur |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit sous le sous-répertoire de l'application |
| `Infrastructure\Http\Routes` | Les noms des routes, et leurs paramètres |
| `Infrastructure\DependencyInjection\CommentsServices` | Les services que la configuration enregistre |
| `Infrastructure\DependencyInjection\CsrfProtectionPass` | Arrête le conteneur avec un message qui dit quoi changer quand `framework.csrf_protection` est faux |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les recettes du kit d'après le sous-répertoire du Toolkit, sauf si `kit_subdirectory` le dit |
| `PhilCommentsBundle` | Le bundle : la configuration, le mappage Doctrine, les chemins d'AssetMapper dans l'extension d'AssetMapperBundle |

## Ressources

| Fichier | Rôle |
|---|---|
| `assets/src/comments_controller.js` | Le contrôleur Stimulus `phil--comments-bundle--comments` |
| `assets/src/lib/passage.js`, `api.js`, `view.js`, `i18n.js` | Ses modules ; voyez [JavaScript](javascript.md) |
| `assets/styles/comments.css` | Le panneau, la barre d'outils et le surlignage |
| `config/routes.php` | Les routes des points de terminaison |
| `config/page_routes.php` | La route des formulaires simples d'une page |
| `templates/components/Page.html.twig` | Les commentaires sur une page, dessinés avec les recettes du kit |
| `templates/stopped.html.twig` | Ce qui a arrêté un formulaire simple, avec le texte envoyé |
| `translations/phil_comments.en.php`, `phil_comments.fr.php` | Les libellés de la page |
