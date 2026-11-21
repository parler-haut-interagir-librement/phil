<!-- phil-dev source-sha256=cf8109ddeb9ab2dc15c550ddc95402c6f6f79c010c85290495ece1605836e7f3 -->
# Référence

## `Phil\ImportWizard\Profile`

| Type | Rôle |
|---|---|
| `ImportProfile` | Un type importable : colonnes, clé naturelle, règles, correspondance ; `withMapping()`, `toJson()`, `fromJson()`. |
| `Column` | Une colonne : nom, type, obligatoire, en-tête, alias, longueur, choix, référence ; `parse()` lit une cellule. |
| `ColumnType` | `Text`, `Integer`, `Decimal`, `Boolean`, `Date`, `Email` ; `parse()`, `text()`. |
| `Reference` | Le type dont une colonne contient la clé naturelle. |
| `ColumnMapping` | Quel en-tête remplit quelle colonne ; `guess()`, `completedFor()`, `with()`, `without()`, `resolve()`. |
| `HeaderName` | Interne : un en-tête normalisé pour la comparaison. |
| `ProfileData` | Interne : les champs typés d'un profil sérialisé. |

## `Phil\ImportWizard\Csv`

| Type | Rôle |
|---|---|
| `CsvRowSource` | Les lignes d'un texte ou d'un fichier CSV, lues dans des limites, l'en-tête d'abord. |
| `CsvReader` | Le lecteur de la RFC 4180, chaque enregistrement avec sa ligne physique. |
| `CsvDialect` | Délimiteur, guillemet, encodage, marque d'ordre des octets, protection des formules ; `detect()`. |
| `CsvWriter` | Écrit des lignes en CSV, formules protégées. |

## `Phil\ImportWizard\Source`

| Type | Rôle |
|---|---|
| `SourceRow` | Un enregistrement : sa ligne physique, ses cellules, s'il est mal formé ; `isBlank()`. |
| `SourceLimits` | Les octets, les lignes et les cellules que peut avoir un fichier. |

## `Phil\ImportWizard\Plan`

| Type | Rôle |
|---|---|
| `ImportPlanner` | L'essai à blanc : planifie une source avec un profil et une recherche. |
| `ImportPlan` | Les lignes à écrire dans l'ordre, les refus, la correspondance, l'empreinte ; `preview()`, `describe()`. |
| `PlannedRow` | Une ligne à écrire : ligne, action, clé, valeurs, `dependsOn`, cellules ; `has()`, `value()`. |
| `RowRejection` | Une ligne refusée : ligne, cellules, erreurs. |
| `CellError` | Pourquoi une cellule ou une ligne est refusée : colonne, raison, détail, ligne liée ; `message()`. |
| `Reason` | Les codes des refus ; `message()`. |
| `RowAction` | `Create`, `Update`, `Refuse`. |
| `PreviewLine` | Une ligne de l'aperçu : ligne, action, erreurs. |
| `NaturalKey` | Les valeurs qui identifient un enregistrement, en texte ; `toString()`, `equals()`. |
| `Planning` | Interne : l'état de travail d'un essai à blanc. |

## `Phil\ImportWizard\Import`

| Type | Rôle |
|---|---|
| `Importer` | Écrit un plan par sa cible, par lots, l'empreinte vérifiée. |
| `Batch` | Des lignes écrites ensemble : type, numéro, lignes ; `creates()`, `updates()`. |
| `ImportResult` | Les enregistrements créés et mis à jour, les lots, chaque refus. |

## `Phil\ImportWizard\Output`

| Type | Rôle |
|---|---|
| `RejectedRowsFile` | Les lignes refusées sous forme d'un fichier CSV à corriger puis à importer de nouveau. |
| `TemplateFile` | Le gabarit d'un profil réduit à ses en-têtes, et son nom de fichier. |

## `Phil\ImportWizard\Lms`

| Type | Rôle |
|---|---|
| `LmsProfiles` | Les profils des personnes, des inscriptions et des unités organisationnelles ; `all()`. |

## `Phil\ImportWizard\Rule`

| Type | Rôle |
|---|---|
| `DateOrder` | Une date de fin jamais antérieure à sa date de début. |

## `Phil\ImportWizard\Contract`

| Type | Rôle |
|---|---|
| `ImportTarget` | L'écrivain d'un type : `type()`, `write()`, et la recherche. |
| `KeyLookup` | Quelles clés naturelles d'un type existent. |
| `RowSource` | L'en-tête et les lignes d'un fichier. |
| `RowRule` | Une vérification portant sur les cellules d'une ligne, sérialisée avec son profil. |

## `Phil\ImportWizard\Exception`

| Type | Rôle |
|---|---|
| `ImportWizardException` | Toute exception de la bibliothèque. |
| `SourceRefused` | Un fichier refusé en entier. |
| `MappingIncomplete` | Des colonnes obligatoires sans en-tête ; `columns`. |
| `InvalidProfile` | Un profil, une colonne, une règle, un dialecte ou une limite inutilisable, ou des données sérialisées de mauvaise forme. |
| `PlanChanged` | Un plan qui n'est pas celui de l'aperçu, ou qui n'est pas du type de la cible. |
