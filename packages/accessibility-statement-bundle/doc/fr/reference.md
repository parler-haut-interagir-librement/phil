<!-- phil-dev source-sha256=5d8e48e205e77468a9de24bdbfa1e247393875f9d00afb900ee768ba8eeb87db -->
# Référence

| Type | Rôle |
|---|---|
| `PhilAccessibilityStatementBundle` | Le bundle et sa configuration. |
| `Domain\Contract\StatementRepository` | Le stockage des versions. |
| `Domain\Exception\StatementNotStored` | Une version qui n'a pas pu être publiée : la source est en lecture seule, ou le numéro est déjà pris. |
| `Application\StatementRegenerator` | La version suivante, tirée de la version en vigueur et du dernier audit, montrée ou publiée. |
| `Application\Exception\NothingToRegenerate` | Aucune déclaration ou aucun audit à partir duquel régénérer. |
| `Application\StatementPublisher` | Le brouillon de la version suivante, et sa publication à partir d'un document. |
| `Application\Exception\PublicationRefused` | Un document non publié : illisible, sans le numéro suivant, ou non gardé. |
| `Infrastructure\Config\ConfiguredStatements` | La déclaration de la configuration, en lecture seule. |
| `Infrastructure\Doctrine\DbalStatements` | Les versions dans `phil_accessibility_statement`, par DBAL. |
| `Infrastructure\Doctrine\StatementTables` | La table, ajoutée à un schéma. |
| `Infrastructure\Doctrine\StatementSchemaListener` | Ajoute la table au schéma que l'ORM génère. |
| `Infrastructure\Audit\ReportDirectory` | Le dernier audit sous forme de répertoire de rapports JSON. |
| `Infrastructure\Http\StatementController` | La page : la version en vigueur et chaque version. |
| `Infrastructure\Http\StatementAdminController` | La page d'administration, derrière un rôle et un jeton CSRF. |
| `Infrastructure\LegalNotices\StatementFooterLink` | La déclaration parmi les liens juridiques de `phil/legal-notices-bundle` |
| `Infrastructure\Twig\StatementExtension` | `phil_accessibility_statement_link()`, `phil_accessibility_statement_path()`, `phil_accessibility_kit()` et le filtre `phil_accessibility_text`. |
| `Infrastructure\Twig\KitComponents` | Les noms sous lesquels les recettes du kit sont installées. |
| `Infrastructure\Console\RegenerateCommand` | `phil:accessibility-statement:generate`. |
| `Infrastructure\DependencyInjection\StatementServices` | Les services de la configuration. |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Lit le sous-répertoire des composants du Toolkit. |
