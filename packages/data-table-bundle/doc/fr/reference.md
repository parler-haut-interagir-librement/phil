<!-- phil-dev source-sha256=d242cff1c2a6bcd655282cfccf52fe20f50b62a5309e2de1264f0a0cb9f64c3e -->
# Référence

| Type | Rôle |
|---|---|
| `Application\Contract\DataTable` | Un tableau de l'application |
| `Application\Contract\DataSource` | L'origine des lignes |
| `Application\Contract\TableAccess` | Répond aux attributs de *voter* |
| `Application\Contract\TablePreferences` | Garde les choix d'un utilisateur |
| `Application\Definition\TableBuilder` | Déclare un tableau |
| `Application\Definition\TableDefinition` | Un tableau tel que déclaré et vérifié |
| `Application\Definition\Column`, `ColumnType` | Une colonne et ses sortes |
| `Application\Definition\Filter`, `FilterType` | Un filtre et ses sortes |
| `Application\Definition\Action` | Une action sur des lignes |
| `Application\Definition\Editing` | La façon de modifier une colonne |
| `Application\Definition\Choices` | Les choix d'une colonne ou d'un filtre d'énumération |
| `Application\Definition\Export` | L'export qu'offre un tableau : son attribut et son nombre maximal de lignes |
| `Application\Export\ExportRows`, `ExportedRows` | Les lignes d'un export, page par page, selon leurs sortes |
| `Application\Query\TableQuery`, `ResultPage`, `Sort`, `SortDirection` | Ce qui est demandé à une source de données et ce qu'elle retourne |
| `Application\Query\TextCriterion`, `ChoiceCriterion`, `DateRangeCriterion`, `BooleanCriterion` | Les filtres tels que les lit une source de données |
| `Application\Source\InMemoryDataSource` | Des lignes déjà en mémoire |
| `Application\State\TableState`, `ResolvedState`, `StateResolver` | L'adresse, vérifiée selon la déclaration |
| `Application\State\TableLinks` | Les adresses des états d'un tableau |
| `Application\State\TablePreference` | Ce qu'un utilisateur a choisi |
| `Application\Tables`, `OpenTable` | Les tableaux par nom ; un tableau ouvert pour une requête |
| `Application\TableOperations` | Les actions et les modifications, avec leurs vérifications |
| `Application\EditValues`, `EditStart`, `EditOutcome`, `EditStatus` | Les valeurs d'une modification et son déroulement |
| `Application\RowValues` | Lit les valeurs d'une ligne |
| `Application\DenyingTableAccess`, `NoTablePreferences` | Les valeurs par défaut |
| `Application\View\TableViews` et les classes `*View` | Le tableau disposé pour un gabarit |
| `Application\View\RowRenderer`, `Formats` | Les lignes, les nombres et les dates d'un rendu |
| `Application\Exception\*` | `InvalidDefinition`, `UnknownField`, `NotFound`, `AccessDenied`, `ActionRefused`, `EditConflict`, `InvalidEditValue` |
| `Infrastructure\Doctrine\DoctrineDataSource` | Des lignes tirées d'un QueryBuilder |
| `Infrastructure\Http\TableGuard`, `TableController` | Les vérifications d'une modification ; les routes des simples formulaires |
| `Infrastructure\Http\TableExportController`, `Infrastructure\Export\TableExports` | L'export d'un tableau ; ses liens et ses vérifications |
| `Infrastructure\DependencyInjection\DataTablesPass`, `CsrfProtectionPass`, `KitSubdirectoryPass` | Les tableaux par nom ; la protection CSRF exigée ; le sous-répertoire des recettes |
| `Infrastructure\Twig\DataTableExtension`, `KitComponents` | `phil_data_table_kit()`, le nom d'une recette du kit ; `phil_data_table_cell()`, une cellule en bloc |
| `Infrastructure\Twig\CellBlocks` | Les cellules en bloc dessinées par un `Twig\BlockChain` des thèmes de cellules et du gabarit de la colonne, gardé pour la requête et abandonné par `kernel.reset` |
| `Infrastructure\Security\AuthorizationCheckerAccess` | Les *voters* de Symfony Security |
| `Infrastructure\Settings\SettingsTablePreferences` | Les préférences dans phil/settings-bundle |
| `Infrastructure\Twig\Components\TableComponent` | Le composant `PhilDataTable:Table` |

| Route | Rôle |
|---|---|
| `phil_data_table_action` | `POST /{table}/action/{action}` |
| `phil_data_table_edit` | `POST /{table}/edit/{column}` |
| `phil_data_table_export` | `GET /{table}/export.{format}`, avec phil/data-table-export |
