# Reference

| Type | Role |
|---|---|
| `PhilAccessibilityStatementBundle` | The bundle and its configuration. |
| `Domain\Contract\StatementRepository` | The store of the versions. |
| `Domain\Exception\StatementNotStored` | A version that could not be published: the source is read only, or the number is taken. |
| `Application\StatementRegenerator` | The next version from the version in force and the latest audit, shown or published. |
| `Application\Exception\NothingToRegenerate` | No statement or no audit to regenerate from. |
| `Application\StatementPublisher` | The draft of the next version, and its publication from a document. |
| `Application\Exception\PublicationRefused` | A document not published: unreadable, not the next number, or not kept. |
| `Infrastructure\Config\ConfiguredStatements` | The statement of the configuration, read only. |
| `Infrastructure\Doctrine\DbalStatements` | The versions in `phil_accessibility_statement`, through DBAL. |
| `Infrastructure\Doctrine\StatementTables` | The table, added to a schema. |
| `Infrastructure\Doctrine\StatementSchemaListener` | Adds the table to the schema the ORM generates. |
| `Infrastructure\Audit\ReportDirectory` | The latest audit as a directory of JSON reports. |
| `Infrastructure\Http\StatementController` | The page: the version in force and each version. |
| `Infrastructure\Http\StatementAdminController` | The administration page, behind a role and a CSRF token. |
| `Infrastructure\LegalNotices\StatementFooterLink` | The statement among the legal links of `phil/legal-notices-bundle` |
| `Infrastructure\Twig\StatementExtension` | `phil_accessibility_statement_link()`, `phil_accessibility_statement_path()`, `phil_accessibility_kit()` and the `phil_accessibility_text` filter. |
| `Infrastructure\Twig\KitComponents` | The names under which the recipes of the kit are installed. |
| `Infrastructure\Console\RegenerateCommand` | `phil:accessibility-statement:generate`. |
| `Infrastructure\DependencyInjection\StatementServices` | The services of the configuration. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Reads the components subdirectory of the Toolkit. |
