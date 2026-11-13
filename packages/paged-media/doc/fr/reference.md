<!-- phil-dev source-sha256=6a189664b7c004c3ff70bf514c1ac391f51f571429e5f283f6d52f62f2d9d732 -->
# Référence

| Type | Nature | Rôle | État |
|---|---|---|---|
| `Pdf\Document` | `final class` | Le moteur d'écriture natif : pages, texte, formes, images, champs de formulaire, rendu | Implémenté et testé |
| `Pdf\PageSize` | `final readonly class` | La largeur et la hauteur d'une page en points | Implémenté et testé |
| `Pdf\Color` | `final readonly class` | Un niveau de gris ou une couleur RGB | Implémenté et testé |
| `Pdf\Metadata` | `final readonly class` | Le dictionnaire d'information, et le paquet XMP d'un document conforme | Implémenté et testé |
| `Pdf\Conformance` | `enum` | Une norme à laquelle un document est conforme : PDF/A-2u | Implémenté et testé |
| `Pdf\Form\TextField` | `final readonly class` | Une zone de texte que le lecteur remplit, sur une ligne ou plusieurs | Implémenté et testé |
| `Pdf\Form\CheckBox` | `final readonly class` | Un carré que le lecteur coche, avec sa valeur d'exportation | Implémenté et testé |
| `Pdf\Form\RadioGroup`, `RadioButton` | `final readonly class` | Des cercles dont le lecteur choisit un, et chaque choix | Implémenté et testé |
| `Pdf\Form\ChoiceField` | `final readonly class` | Une liste déroulante, modifiable ou non, ou une liste | Implémenté et testé |
| `Pdf\Form\Fields`, `Widget`, `FormWriter`, `Check` | `final class`, `@internal` | Les widgets et leurs flux d'apparence, le formulaire interactif ou son aplatissement, les vérifications d'un champ | Implémenté et testé |
| `Template\LabelSheet` | `final readonly class` | Une grille d'étiquettes ou de cartes sur une page, et les formats courants de stock | Implémenté et testé |
| `Template\TemplateFonts` | `final readonly class` | Les polices normale et grasse dans lesquelles un gabarit écrit | Implémenté et testé |
| `Template\NameBadges`, `Badge` | `final readonly class` | Les porte-noms d'un événement, et un porte-nom | Implémenté et testé |
| `Template\AddressLabels`, `AddressLabel` | `final readonly class` | Des étiquettes d'adresse, et une adresse | Implémenté et testé |
| `Template\ProductLabels`, `ProductLabel` | `final readonly class` | Des étiquettes de produit, et un produit | Implémenté et testé |
| `Template\ShelfLabels`, `ShelfLabel` | `final readonly class` | Des étiquettes de tablette, et une étiquette de tablette | Implémenté et testé |
| `Template\GiftCards`, `GiftCard` | `final readonly class` | Des cartes-cadeaux, et une carte | Implémenté et testé |
| `Template\DownloadCards`, `DownloadCard` | `final readonly class` | Des cartes de téléchargement, une par code | Implémenté et testé |
| `Template\Calendar`, `CalendarMonth` | `final readonly class` | Un calendrier imprimé, un mois par page, et un mois | Implémenté et testé |
| `Template\LargePrint`, `LargePrintBlock` | `final readonly class` | Une édition en gros caractères, et ses titres et paragraphes | Implémenté et testé |
| `Template\Layout` | `final class`, `@internal` | Des étiquettes disposées sur des planches, du texte ajusté à une largeur | Implémenté et testé |
| `Css\PageRule` | `final readonly class` | La règle `@page` d'un moteur de média paginé : format, marges, fond perdu, marques d'imprimeur | Implémenté et testé |
| `Css\PrinterMarks` | `enum` | Les marques tracées dans le fond perdu : aucune, de coupe, en croix, ou les deux | Implémenté et testé |
| `Pdf\SrgbProfile` | `final class`, `@internal` | Le profil ICC sRGB de l'intention de sortie | Implémenté et testé |
| `Pdf\Objects` | `final class`, `@internal` | Les objets numérotés et la table de références croisées | Implémenté et testé |
| `Pdf\Number` | `final class`, `@internal` | Les nombres tels que PDF les écrit | Implémenté et testé |
| `Pdf\Bytes` | `final class`, `@internal` | Des utilitaires hexadécimaux et gros-boutistes | Implémenté et testé |
| `Font\Font` | `interface` | Ce dans quoi le texte est écrit et mesuré | Implémenté et testé |
| `Font\StandardFont` | `enum` | Helvetica et Helvetica Bold en Windows-1252 | Implémenté et testé |
| `Font\TrueTypeFont` | `final class` | Une police TrueType lue, mesurée, réduite pour l'intégration, ou intégrée en entier pour un formulaire | Implémenté et testé |
| `Image\Image` | `final readonly class` | Une image JPEG ou PNG prête à placer | Implémenté et testé |
| `Exception\PagedMediaException` | `interface` | Implémentée par chaque exception de la bibliothèque | Implémenté et testé |
| `Exception\InvalidDocument` | `final class` | Un document construit d'une façon qui ne peut pas être écrite | Implémenté et testé |
| `Exception\InvalidFont` | `final class` | Une police qui ne peut être ni lue ni intégrée | Implémenté et testé |
| `Exception\InvalidImage` | `final class` | Une image qui ne peut pas être lue | Implémenté et testé |
| `Gotenberg\Gotenberg` | `final readonly class` | Le client : étapes en liste ou seules, conversion LibreOffice, routes de lecture, version | Implémenté et testé |
| `Gotenberg\BasicAuth` | `final readonly class` | Les identifiants de connexion d'un Gotenberg avec authentification de base | Implémenté et testé |
| `Gotenberg\InputFile` | `final readonly class` | Un fichier envoyé, lu par morceaux à partir d'un chemin, gardé en mémoire, ou produit à partir d'un `Document` ; `path()` donne le chemin, null en mémoire | Implémenté et testé |
| `Gotenberg\Files` | `final readonly class` | Les fichiers, archives et téléchargements sur lesquels travaille une requête | Implémenté et testé |
| `Gotenberg\Download` | `final readonly class` | Une URL que Gotenberg télécharge d'abord, avec ses en-têtes | Implémenté et testé |
| `Gotenberg\Pages` | `final readonly class` | Une sélection de pages | Implémenté et testé |
| `Gotenberg\Pipeline` | `final readonly class` | Une liste d'étapes, vérifiée selon les règles d'ordre de Gotenberg | Implémenté et testé |
| `Gotenberg\Profile` | `enum` | Un profil PDF/A, PDF/UA ou Factur-X, et les paires qu'ils forment | Implémenté et testé |
| `Gotenberg\RequestOptions` | `final readonly class` | Le nom du résultat, la trace et le *webhook* d'une requête | Implémenté et testé |
| `Gotenberg\Webhook`, `Gotenberg\WebhookMethod` | `final readonly class`, `enum` | L'endroit où Gotenberg envoie un résultat au lieu de répondre avec lui | Implémenté et testé |
| `Gotenberg\WebhookEvent` | `final readonly class` | L'événement envoyé à l'URL des événements d'un *webhook*, une fois lu | Implémenté et testé |
| `Gotenberg\Result` | `final readonly class` | Un fichier ou un ZIP que Gotenberg a renvoyé, avec son nom et ses comptes de remédiation | Implémenté et testé |
| `Gotenberg\Stage\Stage` | `interface` | Une opération sur un PDF, ses options et ses fichiers | Implémenté et testé |
| `Gotenberg\Stage\Operation` | `enum` | Les opérations, leurs routes et les règles de leur ordre | Implémenté et testé |
| `Gotenberg\Stage\Merge`, `Split`, `Select`, `Delete`, `Insert`, `Rotate` | `final readonly class` | Les étapes qui assemblent des pages | Implémenté et testé |
| `Gotenberg\Stage\StampText`, `StampImage`, `StampPdf`, `StampFont` | `final readonly class`, `enum` | Les estampilles, et les 14 polices standard d'une estampille de texte | Implémenté et testé |
| `Gotenberg\Stage\SetMetadata`, `SetBookmarks`, `Bookmark`, `SetForm`, `FlattenForm` | `final readonly class` | Les étapes qui écrivent les informations, le plan et le formulaire | Implémenté et testé |
| `Gotenberg\Stage\FlattenTransparency`, `ConvertColors`, `ColorTarget`, `Redact`, `Region`, `Autotag` | `final readonly class`, `enum` | Les étapes qui changent le contenu : transparence, couleurs, caviardage, balises | Implémenté et testé |
| `Gotenberg\Stage\Attach`, `Relationship`, `Detach` | `final readonly class`, `enum` | Les fichiers intégrés | Implémenté et testé |
| `Gotenberg\Stage\Optimize`, `Encrypt`, `EncryptionAlgorithm`, `Permissions`, `Decrypt` | `final readonly class`, `enum` | La taille et la protection | Implémenté et testé |
| `Gotenberg\Stage\Remediate`, `FacturXLevel` | `final readonly class`, `enum` | La réparation vers PDF/A, PDF/UA ou Factur-X | Implémenté et testé |
| `Gotenberg\Stage\Sign`, `Timestamp` | `final readonly class` | La signature et l'horodatage de document | Implémenté et testé |
| `Gotenberg\Stage\Render`, `ImageFormat`, `ExtractText`, `ExtractAttachments` | `final readonly class`, `enum` | Les étapes terminales : images, texte, fichiers intégrés | Implémenté et testé |
| `Gotenberg\Signing\Certificate` | `final readonly class` | Un certificat de signature PKCS#12 ou PEM | Implémenté et testé |
| `Gotenberg\Signing\CertificateSource` | `interface` | La provenance du certificat de signature | Implémenté et testé |
| `Gotenberg\Signing\FileCertificateSource`, `InlineCertificateSource`, `VaultCertificateSource` | `final readonly class` | Un certificat sur le disque, gardé sous forme de texte, ou dans HashiCorp Vault ou OpenBao | Implémenté et testé |
| `Gotenberg\Signing\TimestampAuthority`, `TimestampAuthorityUrl` | `interface`, `final readonly class` | Une autorité d'horodatage RFC 3161, connue par son URL | Implémenté et testé |
| `Gotenberg\LibreOffice\LibreOfficeOptions` | `final readonly class` | Les options d'une conversion LibreOffice | Implémenté et testé |
| `Gotenberg\LibreOffice\PaperSize`, `InitialView`, `Magnification`, `PageLayout` | `enum` | Les valeurs des options de la conversion | Implémenté et testé |
| `Gotenberg\Read\PdfInfo`, `DocumentInformation`, `Attachment`, `FormField`, `FormFieldType` | `final readonly class`, `enum` | Ce que répondent les routes de lecture | Implémenté et testé |
| `Gotenberg\Read\ValidationReport`, `Violation`, `Severity` | `final readonly class`, `enum` | Ce qu'une validation a trouvé | Implémenté et testé |
| `Gotenberg\Read\JsonValue` | `final readonly class`, `@internal` | Du JSON lu avec ses types attendus | Implémenté et testé |
| `Gotenberg\Internal\Multipart`, `Check`, `Url` | `final class`, `@internal` | Le corps multipart, les vérifications des étapes, les adresses de service et publiques et les en-têtes | Implémenté et testé |
| `Gotenberg\Exception\GotenbergException` | `interface` | Implémentée par chaque exception du client | Implémenté et testé |
| `Gotenberg\Exception\InvalidStage`, `InvalidPipeline`, `InvalidRequest` | `final class` | Une requête refusée avant d'être envoyée | Implémenté et testé |
| `Gotenberg\Exception\GotenbergFailure` | `abstract class` | Une réponse qui n'est pas un succès, avec son statut et sa trace | Implémenté et testé |
| `Gotenberg\Exception\RequestRefused`, `AccessDenied`, `RouteUnavailable`, `ServiceUnavailable`, `ConversionFailed`, `UnexpectedResponse` | `final class` | Les échecs par statut | Implémenté et testé |
| `Gotenberg\Exception\CertificateUnavailable` | `final class` | Un certificat que sa source n'a pas pu donner | Implémenté et testé |
