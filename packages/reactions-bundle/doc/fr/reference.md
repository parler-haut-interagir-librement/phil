<!-- phil-dev source-sha256=041c9f96f702e6d0e65255518d22f115b7fe29539c5fc36cbaa80d22c13eeacd -->
# Référence

| Type ou fichier | Rôle |
|---|---|
| `PhilReactionsBundle` | Le bundle, sa configuration et ce qu'il ajoute en tête |
| `Domain\Model\Reaction` | Une réaction d'un membre à un contenu, la vérification d'un identifiant de membre, et la table `phil_reaction` |
| `Domain\Repository\ReactionRepository` | Le port du stockage |
| `Domain\ValueObject\ReactionDefinition` | Une réaction qu'offre un site |
| `Domain\ValueObject\ReactionGroup` | Les réactions d'une source |
| `Domain\ValueObject\ReactionVisibility` | Qui voit quoi |
| `Domain\Status\Audience` | Tout le monde, les membres ou personne |
| `Domain\Exception\ReactionsException` | Chaque exception du bundle |
| `Domain\Exception\InvalidReaction`, `AlreadyReacted` | Une mauvaise déclaration ou un mauvais identifiant de membre ; la même réaction donnée deux fois, refusée par le stockage |
| `Application\Reactions` | Réagir, retirer, effacer un contenu |
| `Application\ReactionBoard` | Ce que voit celui qui regarde des contenus |
| `Application\ReactionCatalog` | Les réactions du site, une fois par requête |
| `Application\ReactionsPerMember` | Combien de réactions un membre donne à un contenu |
| `Application\ReactionSubject` | Ce au sujet de quoi un *voter* est interrogé |
| `Application\DeclaredReactionSource` | Une source de la configuration |
| `Application\ConfiguredVisibility` | La visibilité de la configuration |
| `Application\DenyingReactionAccess`, `NoCurrentMember`, `NoMemberNames`, `PlainEmojiImages` | Les valeurs par défaut sûres des ports |
| `Application\Contract\*` | `ReactionSource`, `ReactionAccess`, `CurrentMember`, `MemberNames`, `VisibilityPolicy`, `ReactionThrottle`, `EmojiImages` |
| `Application\View\*` | `ReactionPanel`, `ReactionItem`, `PickerGroup`, `ReactorsLine` |
| `Application\Event\*` | `ReactionAdded`, `ReactionRemoved` |
| `Application\Exception\*` | `ReactionRefused`, `UnknownReaction`, `TooManyReactions`, `ReactionLimitReached` |
| `Infrastructure\Doctrine\DoctrineReactionRepository`, `Infrastructure\Memory\InMemoryReactionRepository` | Les stockages : la table, ses instants en UTC ; la mémoire, vidée entre deux requêtes |
| `Infrastructure\Security\AuthorizationCheckerAccess`, `TokenCurrentMember` | Symfony Security |
| `Infrastructure\RateLimiter\SymfonyThrottle` | symfony/rate-limiter, par membre |
| `Infrastructure\Messenger\EventsOnTheBus` | Les événements sur le bus |
| `Infrastructure\Privacy\ReactionsPrivacy` | L'exportation et l'effacement |
| `Infrastructure\Media\MediaEmojiImages` | Les images comme médias, lues en une requête |
| `Infrastructure\Settings\SettingsVisibility` | La visibilité parmi les réglages |
| `Infrastructure\Http\ReactionController`, `ReactionGuard` | Le simple formulaire et ses vérifications |
| `Infrastructure\Twig\Components\ReactionsComponent` | Le Live Component `PhilReactions`, et la langue dans laquelle sa liste de noms est écrite |
| `Infrastructure\Twig\KitComponents`, `ReactionsTwig` | Les noms des recettes ; `reactions_preload` |
| `Infrastructure\DependencyInjection\*` | `ReactionsServices`, avec l'extension de twig/intl-extra quand TwigExtraBundle est absent ; `CsrfProtectionPass`, `KitSubdirectoryPass` |
| `config/routes.php` | La route du simple formulaire |
| `templates/components/Reactions.html.twig`, `templates/stopped.html.twig` | Le composant et la page d'une réaction arrêtée |
| `translations/phil_reactions.{en,fr}.php` | Les textes |
| `assets/src/reactions_controller.js`, `picker_controller.js`, `lib/search.js` | Les contrôleurs Stimulus et la recherche |
| `assets/styles/reactions.css` | La disposition autour des recettes |
| `assets/package.json` | Les contrôleurs pour Symfony Flex et StimulusBundle, chargés paresseusement |
