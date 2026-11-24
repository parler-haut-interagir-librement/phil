# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `PhilNewsletterArchiveBundle` | The bundle: its configuration and services. |
| `Domain\Model\ArchivedIssue` | The web version of one sent campaign as the archive keeps it, published, shared by a link, or both. |
| `Domain\ValueObject\Tag` | A tag an issue is filed under: a slug of at most 64 characters. |
| `Domain\ValueObject\ArchiveFilter` | Which page of a site's archive is read: list, tag, page and size. |
| `Domain\ValueObject\IssuePage` | One page of the archive, its total and where it stands. |
| `Domain\Contract\ArchiveRepository` | Where the issues are kept. |
| `Domain\Exception\NewsletterArchiveException` | Every exception of the archive. |
| `Domain\Exception\InvalidArchiveValue` | A value the archive refuses. |
| `Application\Archive` | Publish, unpublish, share, revoke a link, retag. |
| `Application\ArchiveReader` | What the public reads: a page, an issue at its slug, an issue by its link, the lists and tags. |
| `Application\NeutralWebVersion` | The web version of a campaign with neutral data in place of the subscriber's. |
| `Application\LinkTokens` | The tokens of the links, and the hash the archive keeps of them. |
| `Application\IssueFeed` | The latest published issues as `FeedItem`, for a feed. |
| `Application\FeedItem` | One issue as a feed entry reads it. |
| `Application\Contract\WebVersionRenderer` | Renders the web version of a sent campaign. |
| `Application\Contract\IssueUrls` | The absolute addresses of an issue and of a link. |
| `Application\Exception\NotArchivable` | A campaign the archive does not take: unknown, not sent, or not archived. |
| `Infrastructure\Dbal\DbalArchiveRepository` | The issues in the archive's tables, through Doctrine DBAL. |
| `Infrastructure\Dbal\ArchiveSchema` | The archive's tables, added to a schema that lacks them. |
| `Infrastructure\Dbal\ArchiveSchemaListener` | Adds the tables to the schema the ORM generates. |
| `Infrastructure\Http\ArchiveController` | The list, the issue and the shared page. |
| `Infrastructure\Routing\RouterIssueUrls` | `IssueUrls` from the router, and the names of the routes. |
| `Infrastructure\Console\ArchiveCommand` | `phil:newsletter-archive`: publish, unpublish, share, revoke, retag. |
| `Infrastructure\DependencyInjection\ArchiveServices` | Registers the services from the configuration. |
