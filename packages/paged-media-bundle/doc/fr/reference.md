<!-- phil-dev source-sha256=4d35108c8c81f9d20d6f254ffe9e8cb0d9256a531082d883bc3468a80a41e503 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilPagedMediaBundle` | `final class` | Le bundle : sa configuration et l'attribut des gabarits |
| `Application\PagedMedia` | `final readonly class` | Le moteur d'écriture natif et Gotenberg derrière un seul service |
| `Application\Output` | `final readonly class` | Un fichier rendu, son nom, son type de média et les comptes de sa remédiation |
| `Application\DocumentTemplate` | `interface` | Un document du moteur d'écriture natif dessiné à partir de paramètres |
| `Application\AsDocumentTemplate` | `attribute` | Enregistre un gabarit sous un nom |
| `Application\DocumentTemplates` | `final readonly class` | Les gabarits, par nom |
| `Application\SignatureDefaults` | `final readonly class` | Ce que dit chaque signature, sauf si un appel dit autre chose |
| `Application\PagedMediaQueue` | `final readonly class` | Garde une tâche et répartit son message |
| `Application\Job\RenderJob` | `final readonly class` | Ce dont un *worker* a besoin pour produire le rendu d'un gabarit |
| `Application\Job\PipelineJob` | `final readonly class` | Ce dont un *worker* a besoin pour exécuter une liste d'étapes |
| `Application\Job\ConversionJob` | `final readonly class` | Ce dont un *worker* a besoin pour convertir des documents bureautiques |
| `Application\Job\StoredStage` | `final readonly class` | Une étape relue à partir d'une tâche gardée |
| `Application\Message\RenderPdf` | `final readonly class` | Produit le rendu du gabarit d'une tâche dans un *worker* ; ne transporte que l'identifiant de la tâche |
| `Application\Message\RunPipeline` | `final readonly class` | Exécute les étapes d'une tâche dans un *worker* ; ne transporte que l'identifiant de la tâche |
| `Application\Message\ConvertDocuments` | `final readonly class` | Convertit les documents d'une tâche dans un *worker* ; ne transporte que l'identifiant de la tâche |
| `Application\Port\JobStore` | `interface` | Où les tâches attendent un *worker* : le *claim check* |
| `Application\Port\PdfStorage` | `interface` | Où les fichiers des messages sont gardés |
| `Application\Event\PdfStored` | `final readonly class` | Réparti une fois le fichier d'un message gardé |
| `Application\Event\PrintStarting` | `final readonly class` | Réparti avant une impression qui nomme son œuvre |
| `Application\Event\UnnamedPrintStarting` | `final readonly class` | Réparti avant une impression qui ne nomme aucune œuvre |
| `Application\PrintSubject` | `final readonly class` | L'œuvre qu'imprime une impression, par type et par identifiant |
| `Application\PrintableHtml` | `final readonly class` | Une page pour le moteur d'impression |
| `Application\Contract\PrintEngine` | `interface` | Le moteur de médias paginés de l'application |
| `Infrastructure\Notes\PrintedNotes`, `NotesPage` | `final readonly class` | Un document UJO (Universal JSON Output) avec ses notes, pour le moteur |
| `Application\Exception\NotConfigured` | `final class` | Une fonctionnalité que la configuration n'active pas |
| `Application\Exception\UnknownTemplate` | `final class` | Un nom de gabarit sous lequel aucun service n'est enregistré |
| `Application\Exception\InvalidTemplateParameters` | `final class` | Le paramètre d'un gabarit d'imprimé manquant ou d'un genre incorrect, par son chemin |
| `Application\Exception\StorageFailed` | `final class` | Un fichier que le stockage n'a pas pu garder, une tâche que le stockage n'a pas pu écrire ou retirer |
| `Application\Exception\JobUnavailable` | `final class` | Une tâche que le stockage ne peut pas donner |
| `Infrastructure\DependencyInjection\PagedMediaServices` | `final class` | Les services de la configuration |
| `Infrastructure\Messenger\PagedMediaHandler` | `final readonly class` | Traite les trois messages |
| `Infrastructure\Messenger\FailedJobPurger` | `final readonly class` | Oublie la tâche d'un message en échec définitif |
| `Infrastructure\Storage\DirectoryStorage` | `final readonly class` | Garde les fichiers dans un répertoire |
| `Infrastructure\Storage\DirectoryJobStore` | `final readonly class` | Garde les tâches dans un répertoire, un fichier JSON chacune |
| `Infrastructure\Storage\CacheJobStore` | `final readonly class` | Garde les tâches dans un pool de cache PSR-6 partagé par les *workers* |
| `Infrastructure\Template\NameBadgesTemplate`, `AddressLabelsTemplate`, `ProductLabelsTemplate`, `ShelfLabelsTemplate`, `GiftCardsTemplate`, `DownloadCardsTemplate`, `CalendarTemplate`, `LargePrintTemplate` | `final readonly class` | Les gabarits d'imprimés de la bibliothèque comme gabarits de documents, dessinés à partir de paramètres |
| `Infrastructure\Template\Parameters`, `PrintedTemplates` | `final readonly class`, `final class`, `@internal` | Les paramètres lus par chemin ; les gabarits par nom et leurs polices |
| `Infrastructure\Storage\JobCodec` | `final readonly class` | Écrit une tâche en JSON et la relit |
