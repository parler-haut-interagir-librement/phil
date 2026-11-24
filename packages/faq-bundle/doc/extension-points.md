# Extension points

| Point | How | Stable |
|---|---|---|
| The answers' compiler and purifier | `phil_faq.markup`: a `Phil\Markup\Markup` service, with a reader or a purifier of the application's | Yes |
| The rate limits | Declare `phil_faq_votes` or `phil_faq_searches` in `framework.rate_limiter`, or name other factories in `phil_faq.rate_limiters` | Yes |
| The throttle | Replace `phil_faq.throttle.votes` or `phil_faq.throttle.searches` by a service implementing `Application\Contract\Throttle` | Yes |
| The store | Alias `Domain\Contract\FaqRepository`, `UnansweredSearchRepository` or `SpentVoteTokenRepository` to a service of the application | Yes, while the interfaces stay |
| The page's layout | `phil_faq.layout`, a template with the blocks `title` and `content` | Yes |
| The administration's layout | `phil_faq.admin.layout` | Yes |
| The templates | Override them under `templates/bundles/PhilFaqBundle/` | The blocks and variables may change before 1.0 |
| The recipes | Install the PHIL UX kit's, or recipes of the same names and props | Yes |
| The texts | Translate the domain `phil_faq` | Yes |
| The JSON-LD | `FaqStructuredData::graph()` gives a `Graph` to merge with the page's other nodes, and `structuredData: false` keeps the component from writing it | Yes |
| The Markdown export | `FaqMarkdown::export()`, for an `llms.txt` or any page | Yes |
| The questions | `FaqEditor`, `FaqTransfer`, `phil:faq:export` and `phil:faq:import` | Yes |
| An EasyAdmin dashboard | `FaqAdmin::menuItems()` in `configureMenuItems()` | Yes |

The bundle has no event: a site that reacts to a vote reads the totals, which are aggregates by design.
