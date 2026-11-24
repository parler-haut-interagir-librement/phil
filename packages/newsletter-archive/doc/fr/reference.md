<!-- phil-dev source-sha256=82dbab518a7ec8f3b7df184c4474dbe17f492271c3f0ca9110ac69f220a32c09 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `PhilNewsletterArchiveBundle` | Le bundle : sa configuration et ses services. |
| `Domain\Model\ArchivedIssue` | La version Web d'une campagne envoyée telle que l'archive la garde, publiée, partagée par un lien, ou les deux. |
| `Domain\ValueObject\Tag` | Une étiquette sous laquelle un numéro est classé : un slug d'au plus 64 caractères. |
| `Domain\ValueObject\ArchiveFilter` | Quelle page de l'archive d'un site est lue : liste, étiquette, page et taille. |
| `Domain\ValueObject\IssuePage` | Une page de l'archive, son total et sa position. |
| `Domain\Contract\ArchiveRepository` | Où les numéros sont gardés. |
| `Domain\Exception\NewsletterArchiveException` | Toute exception de l'archive. |
| `Domain\Exception\InvalidArchiveValue` | Une valeur que l'archive refuse. |
| `Application\Archive` | Publier, dépublier, partager, révoquer un lien, changer les étiquettes. |
| `Application\ArchiveReader` | Ce que le public lit : une page, un numéro à son slug, un numéro par son lien, les listes et les étiquettes. |
| `Application\NeutralWebVersion` | La version Web d'une campagne avec des données neutres à la place de celles de l'abonné. |
| `Application\LinkTokens` | Les jetons des liens, et l'empreinte que l'archive en garde. |
| `Application\IssueFeed` | Les derniers numéros publiés sous forme de `FeedItem`, pour un fil. |
| `Application\FeedItem` | Un numéro tel qu'une entrée de fil le lit. |
| `Application\Contract\WebVersionRenderer` | Rend la version Web d'une campagne envoyée. |
| `Application\Contract\IssueUrls` | Les adresses absolues d'un numéro et d'un lien. |
| `Application\Exception\NotArchivable` | Une campagne que l'archive ne prend pas : inconnue, non envoyée ou non archivée. |
| `Infrastructure\Dbal\DbalArchiveRepository` | Les numéros dans les tables de l'archive, par Doctrine DBAL. |
| `Infrastructure\Dbal\ArchiveSchema` | Les tables de l'archive, ajoutées à un schéma qui en manque. |
| `Infrastructure\Dbal\ArchiveSchemaListener` | Ajoute les tables au schéma que génère l'ORM. |
| `Infrastructure\Http\ArchiveController` | La liste, le numéro et la page partagée. |
| `Infrastructure\Routing\RouterIssueUrls` | `IssueUrls` à partir du routeur, et les noms des routes. |
| `Infrastructure\Console\ArchiveCommand` | `phil:newsletter-archive` : publier, dépublier, partager, révoquer, changer les étiquettes. |
| `Infrastructure\DependencyInjection\ArchiveServices` | Enregistre les services à partir de la configuration. |
