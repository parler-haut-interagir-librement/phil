<!-- phil-dev source-sha256=099bdbd8190e513e8d848433fb19c77148860c14207f90bfc38d9fd30d4972e3 -->
# phil/paged-media

**Des documents PDF en PHP, avec de vraies polices, et rien d'autre à installer.**

phil/paged-media écrit des fichiers PDF et travaille sur eux. Son moteur d'écriture natif place du texte, des lignes,
des rectangles, des images et des champs de formulaire remplissables sur des pages de toute taille, dans les polices
Helvetica standard ou dans une police TrueType qu'il intègre, réduite aux glyphes que le document imprime : un reçu, un
billet ou une feuille imprimable peut donc être écrit dans n'importe quelle écriture ; ses gabarits d'imprimés dessinent
à partir de données des porte-noms, des étiquettes, des cartes-cadeaux et des cartes de téléchargement, des calendriers
et des éditions en gros caractères. Son client Gotenberg envoie des PDF à un service
[Gotenberg](https://gotenberg.dev) 9 pour les fusionner, les scinder, les estampiller, les remplir, les caviarder, les
baliser, les chiffrer, les signer et les horodater, pour les rendre PDF/A, PDF/UA ou Factur-X et vérifier qu'ils le
sont, pour lire leurs informations, et pour convertir des documents bureautiques avec LibreOffice. Il ne met pas en page
de HTML ni de CSS : c'est le travail d'un moteur de média paginé, pour lequel son `PageRule` écrit la règle `@page`
d'une page imprimée, avec son fond perdu et ses marques d'imprimeur.

Il sert à tout code PHP qui doit produire un PDF : factures et reçus, billets, étiquettes, certificats et rapports avec
le seul moteur d'écriture natif, sans navigateur, binaire ni service ; documents archivés, accessibles, signés ou de
facturation électronique, et documents écrits dans une suite bureautique, par Gotenberg. `phil/commerce-bundle` imprime
ses reçus avec lui.

C'est une bibliothèque PHP simple. Le moteur d'écriture natif n'exige que `ext-ctype`, `ext-mbstring`
et `ext-zlib`, et sa sortie est déterministe : les mêmes appels donnent les mêmes octets, ce qui rend les documents
comparables et testables. Le client Gotenberg passe par Symfony HttpClient, une dépendance suggérée, et vérifie chaque
requête avant de l'envoyer — les options d'une étape, l'ordre d'une liste d'étapes, les adresses que Gotenberg va
chercher, lues par `phil/url` — : une requête que Gotenberg refuserait n'est donc jamais envoyée.

## État

| Partie | État |
|---|---|
| `Document` : pages, texte, texte aligné à droite, lignes, rectangles, images, flux compressés, dictionnaire d'information | Implémenté et testé |
| `StandardFont` : Helvetica et Helvetica Bold en Windows-1252, avec leurs largeurs | Implémenté et testé |
| Champs de formulaire : zones de texte, cases à cocher, groupes de boutons radio, listes déroulantes et listes, avec leurs apparences ; gardés remplissables ou aplatis | Implémenté et testé |
| Gabarits d'imprimés : porte-noms, étiquettes d'adresse, de produit et de tablette, cartes-cadeaux, cartes de téléchargement, calendriers, gros caractères, sur des planches d'étiquettes | Implémenté et testé |
| `TrueTypeFont` : lecture, mesure, intégration réduite aux glyphes utilisés, licence d'intégration de la police respectée | Implémenté et testé |
| `Image` : JPEG gardé tel quel ; PNG gardé compressé, ou séparé en couleur et en alpha | Implémenté et testé |
| PDF/A-2u pour l'archivage : métadonnées XMP, intention de sortie sRGB, identifiant de fichier, et refus de ce que la norme interdit | Implémenté et testé |
| Client Gotenberg 9 : les 28 étapes PDF en liste ou seules, typées et vérifiées, et les règles d'ordre de Gotenberg | Implémenté et testé, et exécuté contre Gotenberg 9.1.0 |
| Gotenberg : remédiation PDF/A, PDF/UA et Factur-X avec ses comptes résiduels, et validation | Implémenté et testé, et exécuté contre Gotenberg 9.1.0 |
| Gotenberg : signatures à partir de sources de certificats (fichiers, texte, HashiCorp Vault ou OpenBao) et horodatages RFC 3161 | Implémenté et testé, et exécuté contre Gotenberg 9.1.0 |
| Gotenberg : la conversion LibreOffice avec ses options natives, les routes de lecture, les *webhooks*, les téléchargements, l'authentification de base, les échecs par statut | Implémenté et testé, et exécuté contre Gotenberg 9.1.0 |
| `PageRule` : la règle `@page` d'un moteur HTML et CSS, avec le format, les marges, le fond perdu et les marques d'imprimeur | Implémenté et testé |
| Moteurs HTML et CSS : WeasyPrint, dans `phil/paged-media-weasyprint`, derrière le port `PrintEngine` de `phil/paged-media-bundle` | Implémenté et testé dans son paquet |
| D'autres moteurs derrière le même port, une limite de pages pour un moteur, des formules dessinées en SVG pour un moteur sans MathML | Pas commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Documents](doc/fr/documents.md) | `Document`, `PageSize`, `Color` et `Metadata` : pages, texte, formes, déterminisme. |
| [Archivage](doc/fr/archiving.md) | PDF/A-2u : ce que le document ajoute, et ce qu'il refuse. |
| [Formulaires](doc/fr/forms.md) | Les champs remplissables, leurs apparences et leurs polices, PDF/A et l'aplatissement. |
| [Gabarits d'imprimés](doc/fr/templates.md) | Porte-noms, étiquettes, cartes-cadeaux et de téléchargement, calendriers et gros caractères, à partir de données. |
| [Moteurs HTML et CSS](doc/fr/html-and-css.md) | `PageRule` pour un moteur de média paginé, et ce qu'ajoutent les moteurs. |
| [Polices](doc/fr/fonts.md) | `StandardFont` et `TrueTypeFont` : mesure, retour à la ligne, intégration et licences. |
| [Images](doc/fr/images.md) | `Image` : les fichiers JPEG et PNG qu'elle lit, et la façon dont ils sont écrits. |
| [Gotenberg](doc/fr/gotenberg.md) | Le client : fichiers, résultats, *webhooks*, téléchargements, identifiants de connexion et échecs. |
| [Étapes](doc/fr/stages.md) | Chaque étape, les sélections de pages, et les règles d'ordre d'une liste. |
| [Conformité](doc/fr/conformance.md) | PDF/A, PDF/UA et Factur-X : remédiation et validation. |
| [Signature](doc/fr/signing.md) | Les certificats et leurs sources, les signatures, les horodatages. |
| [LibreOffice](doc/fr/libreoffice.md) | La conversion des documents bureautiques, et leurs options natives. |
| [Lire un PDF](doc/fr/reading.md) | Informations, métadonnées, signets, pièces jointes et champs de formulaire. |
| [Exceptions](doc/fr/exceptions.md) | `PagedMediaException`, les exceptions du moteur d'écriture et celles du client. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests et le seuil de mutation de la bibliothèque, et comment tester du code qui écrit des PDF. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/paged-media
```

```php
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

$font = TrueTypeFont::fromFile('/path/to/NotoSans-Regular.ttf');

$pdf = new Document(PageSize::a5(), new Metadata(title: 'Ticket'));
$pdf->addPage();
$pdf->text(36, 540, 'Admit one', 18, StandardFont::HelveticaBold);
$pdf->text(36, 515, 'Élise Tremblay — 東京', 12, $font);
$pdf->line(36, 505, 384, 505, 0.5, Color::gray(128));
$pdf->textRight(384, 480, '12,50 $', 12, $font);

file_put_contents('ticket.pdf', $pdf->render());
```

Par Gotenberg, le même billet archivé et signé :

```php
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Signing\FileCertificateSource;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Symfony\Component\HttpClient\HttpClient;

$gotenberg = new Gotenberg(HttpClient::create(), 'http://gotenberg:3000');

$result = $gotenberg->pipe(
    Files::of(InputFile::fromDocument('ticket.pdf', $pdf)),
    new Pipeline(new Remediate(Profile::PdfA3b), Sign::with(new FileCertificateSource('/run/secrets/signer.p12', null, $password))),
);
$result->saveTo('ticket.pdf');   // $result->residual === 0: it conforms
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-ctype`, `ext-mbstring`, `ext-uri` et `ext-zlib`, et `phil/url` ; Symfony HttpClient et
un service Gotenberg 9 pour le client Gotenberg. Voyez [Installation](doc/fr/installation.md).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
