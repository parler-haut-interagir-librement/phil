<!-- phil-dev source-sha256=331f4821f39ff5e661dc15fb4f3af034bbdcd4768fefdb495942135f5d820a5b -->
# Référence

| Type ou fichier | Rôle |
|---|---|
| `PhilFaqBundle` | Le bundle, sa configuration et ce qu'il préconfigure |
| `Domain\Model\Section`, `SectionTranslation` | Une section, sa clé et sa place ; son titre par langue |
| `Domain\Model\Question`, `QuestionTranslation` | Une question, son ancre, sa place et ses totaux ; par langue sa formulation, sa réponse, sa publication et son moment |
| `Domain\Model\UnansweredSearch` | Le total d'une recherche sans réponse dans une langue |
| `Domain\Model\SpentVoteToken` | L'empreinte d'un jeton de vote utilisé pour une question |
| `Domain\ValueObject\Locale`, `VoteCount` | Une locale et son repli ; les totaux d'un vote |
| `Domain\Contract\FaqRepository`, `UnansweredSearchRepository`, `SpentVoteTokenRepository` | Les ports du stockage |
| `Domain\Exception\FaqException`, `InvalidFaq` | Toutes les exceptions du bundle ; une règle du modèle enfreinte |
| `Application\FaqEditor` | L'écriture des sections et des questions, leur ordre et leur publication |
| `Application\FaqPages`, `QueryNormalizer` | La page d'une langue et sa recherche |
| `Application\View\FaqPage`, `SectionView`, `QuestionView` | Ce qu'une page affiche |
| `Application\HelpfulVotes`, `VoteTokens`, `VoteTicket` | Les votes et leurs jetons |
| `Application\UnansweredSearches` | Le décompte des recherches sans réponse |
| `Application\FaqStructuredData`, `FaqMarkdown` | Le JSON-LD `FAQPage` ; le Markdown d'un `llms.txt` |
| `Application\FaqTransfer` | Les questions en données simples, et le retour |
| `Application\Contract\Throttle` | Le port de la limite de débit |
| `Application\Exception\*` | `UnknownSection`, `UnknownQuestion`, `VoteRefused`, `AlreadyVoted`, `TooManyRequests` |
| `Infrastructure\Doctrine\DoctrineFaqRepository`, `DbalUnansweredSearches`, `DbalSpentVoteTokens` | Le stockage |
| `Infrastructure\RateLimiter\SymfonyThrottle` | symfony/rate-limiter, par question ou par langue |
| `Infrastructure\Http\FaqController`, `FaqGuard` | La page, le vote, le décompte, et leurs vérifications |
| `Infrastructure\Http\FaqAdminController`, `AdminGuard` | L'administration et ses vérifications |
| `Infrastructure\EasyAdmin\FaqAdmin` | Les liens d'un tableau de bord EasyAdmin |
| `Infrastructure\Console\ExportCommand`, `ImportCommand` | `phil:faq:export`, `phil:faq:import` |
| `Infrastructure\Twig\Components\FaqComponent`, `KitComponents` | Le composant `PhilFaq` ; les noms des recettes |
| `Infrastructure\DependencyInjection\FaqServices`, `CsrfProtectionPass`, `KitSubdirectoryPass` | Les services et leurs vérifications |
| `config/routes.php`, `config/admin_routes.php` | Les routes de la page et de l'administration |
| `templates/components/Faq.html.twig` | Le composant |
| `templates/page.html.twig`, `layout.html.twig`, `stopped.html.twig` | La page, sa mise en page par défaut, un vote refusé |
| `templates/admin/*.html.twig` | L'administration |
| `translations/phil_faq.{en,fr}.php` | Les textes |
| `assets/src/faq_controller.js`, `lib/search.js`, `lib/voted.js` | Le contrôleur Stimulus, la recherche, les questions déjà votées |
| `assets/package.json` | Le contrôleur pour Symfony Flex et StimulusBundle, chargé à la demande |
| `recipe/0.1` | La recette Flex |
