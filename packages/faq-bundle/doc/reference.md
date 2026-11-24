# Reference

| Type or file | Role |
|---|---|
| `PhilFaqBundle` | The bundle, its configuration and what it prepends |
| `Domain\Model\Section`, `SectionTranslation` | A section, its key and place; its title per language |
| `Domain\Model\Question`, `QuestionTranslation` | A question, its anchor, place and totals; per language its wording, answer, publication and moment |
| `Domain\Model\UnansweredSearch` | The total of a search without answer in a language |
| `Domain\Model\SpentVoteToken` | The hash of a vote token used for a question |
| `Domain\ValueObject\Locale`, `VoteCount` | A locale and its fallback; the totals of a vote |
| `Domain\Contract\FaqRepository`, `UnansweredSearchRepository`, `SpentVoteTokenRepository` | The ports of the store |
| `Domain\Exception\FaqException`, `InvalidFaq` | Every exception of the bundle; a rule of the model broken |
| `Application\FaqEditor` | Writing sections and questions, their order and publication |
| `Application\FaqPages`, `QueryNormalizer` | The page of a language and its search |
| `Application\View\FaqPage`, `SectionView`, `QuestionView` | What a page shows |
| `Application\HelpfulVotes`, `VoteTokens`, `VoteTicket` | The votes and their tokens |
| `Application\UnansweredSearches` | The count of the searches without answer |
| `Application\FaqStructuredData`, `FaqMarkdown` | The `FAQPage` JSON-LD; the Markdown of an `llms.txt` |
| `Application\FaqTransfer` | The questions as plain data, and back |
| `Application\Contract\Throttle` | The port of the rate limit |
| `Application\Exception\*` | `UnknownSection`, `UnknownQuestion`, `VoteRefused`, `AlreadyVoted`, `TooManyRequests` |
| `Infrastructure\Doctrine\DoctrineFaqRepository`, `DbalUnansweredSearches`, `DbalSpentVoteTokens` | The store |
| `Infrastructure\RateLimiter\SymfonyThrottle` | symfony/rate-limiter, per question or language |
| `Infrastructure\Http\FaqController`, `FaqGuard` | The page, the vote, the count, and their checks |
| `Infrastructure\Http\FaqAdminController`, `AdminGuard` | The administration and its checks |
| `Infrastructure\EasyAdmin\FaqAdmin` | The links of an EasyAdmin dashboard |
| `Infrastructure\Console\ExportCommand`, `ImportCommand` | `phil:faq:export`, `phil:faq:import` |
| `Infrastructure\Twig\Components\FaqComponent`, `KitComponents` | The component `PhilFaq`; the names of the recipes |
| `Infrastructure\DependencyInjection\FaqServices`, `CsrfProtectionPass`, `KitSubdirectoryPass` | The services and their checks |
| `config/routes.php`, `config/admin_routes.php` | The routes of the page and of the administration |
| `templates/components/Faq.html.twig` | The component |
| `templates/page.html.twig`, `layout.html.twig`, `stopped.html.twig` | The page, its default layout, a refused vote |
| `templates/admin/*.html.twig` | The administration |
| `translations/phil_faq.{en,fr}.php` | The texts |
| `assets/src/faq_controller.js`, `lib/search.js`, `lib/voted.js` | The Stimulus controller, the search, the questions voted for |
| `assets/package.json` | The controller for Symfony Flex and StimulusBundle, fetched lazily |
| `recipe/0.1` | The Flex recipe |
