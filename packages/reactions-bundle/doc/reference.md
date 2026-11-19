# Reference

| Type or file | Role |
|---|---|
| `PhilReactionsBundle` | The bundle, its configuration and what it prepends |
| `Domain\Model\Reaction` | A reaction of a member to a content, the check of a member identifier, and the table `phil_reaction` |
| `Domain\Repository\ReactionRepository` | The port of the store |
| `Domain\ValueObject\ReactionDefinition` | A reaction a site offers |
| `Domain\ValueObject\ReactionGroup` | The reactions of a source |
| `Domain\ValueObject\ReactionVisibility` | Who sees what |
| `Domain\Status\Audience` | Everyone, members or nobody |
| `Domain\Exception\ReactionsException` | Every exception of the bundle |
| `Domain\Exception\InvalidReaction`, `AlreadyReacted` | A bad declaration or member identifier; the same reaction given twice, refused by the store |
| `Application\Reactions` | Reacting, withdrawing, erasing a content |
| `Application\ReactionBoard` | What a viewer sees of contents |
| `Application\ReactionCatalog` | The reactions of the site, once per request |
| `Application\ReactionsPerMember` | How many reactions a member gives a content |
| `Application\ReactionSubject` | What a voter is asked about |
| `Application\DeclaredReactionSource` | A source of the configuration |
| `Application\ConfiguredVisibility` | The visibility of the configuration |
| `Application\DenyingReactionAccess`, `NoCurrentMember`, `NoMemberNames`, `PlainEmojiImages` | The safe defaults of the ports |
| `Application\Contract\*` | `ReactionSource`, `ReactionAccess`, `CurrentMember`, `MemberNames`, `VisibilityPolicy`, `ReactionThrottle`, `EmojiImages` |
| `Application\View\*` | `ReactionPanel`, `ReactionItem`, `PickerGroup`, `ReactorsLine` |
| `Application\Event\*` | `ReactionAdded`, `ReactionRemoved` |
| `Application\Exception\*` | `ReactionRefused`, `UnknownReaction`, `TooManyReactions`, `ReactionLimitReached` |
| `Infrastructure\Doctrine\DoctrineReactionRepository`, `Infrastructure\Memory\InMemoryReactionRepository` | The stores: the table, its moments in UTC; memory, emptied between two requests |
| `Infrastructure\Security\AuthorizationCheckerAccess`, `TokenCurrentMember` | Symfony Security |
| `Infrastructure\RateLimiter\SymfonyThrottle` | symfony/rate-limiter, per member |
| `Infrastructure\Messenger\EventsOnTheBus` | The events on the bus |
| `Infrastructure\Privacy\ReactionsPrivacy` | The export and the erasure |
| `Infrastructure\Media\MediaEmojiImages` | Images as media, read in one query |
| `Infrastructure\Settings\SettingsVisibility` | The visibility among the settings |
| `Infrastructure\Http\ReactionController`, `ReactionGuard` | The plain form and its checks |
| `Infrastructure\Twig\Components\ReactionsComponent` | The Live Component `PhilReactions`, and the language its list of names is written in |
| `Infrastructure\Twig\KitComponents`, `ReactionsTwig` | The names of the recipes; `reactions_preload` |
| `Infrastructure\DependencyInjection\*` | `ReactionsServices`, with the extension of twig/intl-extra when TwigExtraBundle is absent; `CsrfProtectionPass`, `KitSubdirectoryPass` |
| `config/routes.php` | The route of the plain form |
| `templates/components/Reactions.html.twig`, `templates/stopped.html.twig` | The component and the page of a stopped reaction |
| `translations/phil_reactions.{en,fr}.php` | The texts |
| `assets/src/reactions_controller.js`, `picker_controller.js`, `lib/search.js` | The Stimulus controllers and the search |
| `assets/styles/reactions.css` | The arrangement around the recipes |
| `assets/package.json` | The controllers for Symfony Flex and StimulusBundle, fetched lazily |
