<!-- phil-dev source-sha256=2d90e7e2bde015ac65f1045ccdcc3031cb17ccf5492bd8c426ac2a9c1283143c -->
# phil/svg-blocks

**Les formules, diagrammes, graphiques et codes QR des documents par blocs, dessinés une seule fois, sur le serveur, et
conservés avec leur bloc.**

phil/svg-blocks dessine les blocs `math`, `mermaid`, `chart` et `qrCode` des documents UJO (Universal JSON Output) au
moment de leur enregistrement. Il conserve chaque dessin avec son bloc, sous `rendered` : le MathML d'une formule, le
SVG d'un diagramme, d'un graphique ou d'un code QR. Chaque dessin est signé d'un HMAC du type, de la source et du
balisage du bloc. La page d'un lecteur ne l'écrit donc que si ce serveur l'a produit pour la source que le bloc
contient maintenant. Un dessin envoyé par un client, ou laissé par une ancienne source, est retiré au prochain
enregistrement et n'est jamais affiché. Les lecteurs, les liseuses EPUB et les moteurs d'impression obtiennent le
dessin sans script.

Les codes QR, les graphiques et les formules sont dessinés en PHP par `phil/qr-code`, `phil/svg-chart` et
`phil/latex-mathml`. La mise en page de Mermaid exige un moteur de navigateur : les diagrammes sont dessinés par un
petit service Node.js, appelé en HTTP par un port et un adaptateur, comme un client Gotenberg. Sa réponse est réécrite
par `phil/svg-sanitizer` avant d'être conservée. Les diagrammes peuvent attendre une passe en arrière-plan : un
enregistrement n'attend jamais le service.

C'est une simple bibliothèque PHP ; `phil/svg-blocks-bundle` la branche sur les formulaires Symfony, le
moteur de rendu UJO, Messenger et un point de terminaison d'aperçu.

## État

| Partie | État |
|---|---|
| `BlockMarkup` : dessins produits, conservés, signés, redessinés quand ils sont périmés, blocs imbriqués compris | Implémenté et testé |
| `Signer` : clés HMAC-SHA256, versionnées, comparées en temps constant | Implémenté et testé |
| Moteurs de rendu des codes QR, des graphiques et des formules | Implémenté et testé |
| Port `MermaidService`, adaptateur `HttpMermaidService`, réponse assainie | Implémenté et testé ; vérifié contre le service |
| Types différés listés comme en attente d'une passe en arrière-plan | Implémenté et testé |
| Aperçus d'un bloc, sans le conserver | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Dessiner les blocs](doc/fr/drawing.md) | `BlockMarkup`, la source de chaque type, les types différés, les échecs. |
| [Signature](doc/fr/signing.md) | Pourquoi les dessins sont signés, la clé, le secret, et le rendu avec `phil/ujo`. |
| [Service Mermaid](doc/fr/mermaid.md) | Le port, l'adaptateur HTTP, le protocole du service et sa réponse assainie. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, le test d'intégration contre le service, et son banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/svg-blocks
```

```php
use Phil\SvgBlocks\BlockMarkup;
use Phil\SvgBlocks\Renderer\{ChartRenderer, MathRenderer, QrCodeRenderer};
use Phil\SvgBlocks\Signer;
use Phil\Ujo\Html\HtmlRenderer;

$signer = new Signer($secret);                              // 32 bytes or more
$markup = new BlockMarkup($signer, ['qrCode' => new QrCodeRenderer(), 'chart' => new ChartRenderer(), 'math' => new MathRenderer()]);

$document = $markup->render($submitted)->document;          // when saving
$html = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...))->render($document);   // when reading
```

## Prérequis

PHP 8.5 ou plus récent, `phil/ujo`, `phil/qr-code`, `phil/svg-chart`, `phil/latex-mathml` et `phil/svg-sanitizer`.
Symfony HttpClient 8.2, pour `HttpMermaidService`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
