<!-- phil-dev source-sha256=e32c6a1ff7895d0f5bb30a796e30c12a1eb4afe428ce66a35f8fbdc9eb238044 -->
# Points d'extension

| Point | Comment | Stable |
|---|---|---|
| Le compilateur et le purificateur des réponses | `phil_faq.markup` : un service `Phil\Markup\Markup`, avec un lecteur ou un purificateur de l'application | Oui |
| Les limites de débit | Déclarez `phil_faq_votes` ou `phil_faq_searches` dans `framework.rate_limiter`, ou nommez d'autres fabriques dans `phil_faq.rate_limiters` | Oui |
| Le limiteur de débit | Remplacez `phil_faq.throttle.votes` ou `phil_faq.throttle.searches` par un service qui implémente `Application\Contract\Throttle` | Oui |
| Le stockage | Faites de `Domain\Contract\FaqRepository`, `UnansweredSearchRepository` ou `SpentVoteTokenRepository` un alias d'un service de l'application | Oui, tant que les interfaces restent |
| La mise en page de la page | `phil_faq.layout`, un gabarit avec les blocs `title` et `content` | Oui |
| La mise en page de l'administration | `phil_faq.admin.layout` | Oui |
| Les gabarits | Surchargez-les sous `templates/bundles/PhilFaqBundle/` | Les blocs et les variables peuvent changer avant la 1.0 |
| Les recettes | Installez celles du kit PHIL UX, ou des recettes de mêmes noms et de mêmes props | Oui |
| Les textes | Traduisez le domaine `phil_faq` | Oui |
| Le JSON-LD | `FaqStructuredData::graph()` donne un `Graph` à fusionner avec les autres nœuds de la page, et `structuredData: false` empêche le composant de l'écrire | Oui |
| L'export Markdown | `FaqMarkdown::export()`, pour un `llms.txt` ou n'importe quelle page | Oui |
| Les questions | `FaqEditor`, `FaqTransfer`, `phil:faq:export` et `phil:faq:import` | Oui |
| Un tableau de bord EasyAdmin | `FaqAdmin::menuItems()` dans `configureMenuItems()` | Oui |

Le bundle n'a aucun événement : un site qui réagit à un vote lit les totaux, qui sont des agrégats par conception.
