<!-- phil-dev source-sha256=26f467c5244bf5d9d027988f927b4ca9926e2026e87b43e367ee5e7a983f7a38 -->
# phil/api-contract

**Les détails de problème, les signaux d'obsolescence, la lecture tolérante et la vérification OpenAPI d'une API HTTP
sans numéro de version.**

phil/api-contract contient les deux côtés du contrat d'une API HTTP sans version : le serveur qui le respecte et le
client qui le lit. La bibliothèque écrit les détails de problème de la RFC 9457 de façon stricte et les lit de façon
tolérante. Elle transporte les signaux `Deprecation` (RFC 9745), `Sunset` (RFC 8594) et `Link: rel="deprecation"`. Elle
lit les énumérations de façon tolérante, et refuse une valeur inconnue dans un champ d'autorité. Elle vérifie un
document OpenAPI selon trois niveaux nommés de règles, d'un document que tout outil lit à un contrat qu'un client lit
sans deviner, et rapporte chaque constat avec sa règle, sa gravité et son emplacement dans le document. Elle n'écoute
pas les requêtes, ne décore pas de client HTTP et ne stocke rien. Les clés d'idempotence vivent dans
`phil/idempotency`.

Le contrat est REST et JSON, sans `/v1` dans le chemin ; GraphQL en est exclu. Chaque changement est un ajout. Un
changement qui ne peut pas être un ajout devient une nouvelle ressource à côté de l'ancienne, qui annonce son propre retrait dans chaque réponse. Le paquet
s'adresse aux développeurs qui publient ou consomment une telle API et qui veulent ces règles dans des types plutôt que
dans des conventions.

C'est une bibliothèque PHP simple, utilisable dans n'importe quel code PHP, sans dépendance. L'enregistrement des
obsolescences observées est un port (`ObservedDeprecations`) que l'appelant implémente, et une règle de vérification
est une interface (`Contract\OpenApiRule`) qu'une application implémente pour ses propres règles. Dans une application
Symfony, tournez-vous vers `phil/api-contract-bundle`, qui y câble des écouteurs, des contrôleurs `#[Idempotent]`, un
client HTTP observateur et une commande de vérification.

## État

| Domaine | Types | État |
|---|---|---|
| Détails de problème | `Problem\ProblemDetails` | Implémenté et testé |
| Signaux d'obsolescence | `Deprecation\DeprecationSignal`, `Deprecation\LinkHeader`, `Http\HttpDate` | Implémenté et testé |
| Enregistrement des obsolescences observées | `Deprecation\ObservedDeprecations` | Port |
| Lecture tolérante | `TolerantReading\TolerantEnum`, `Exception\UnknownAuthorityValue` | Implémenté et testé |
| Vérification OpenAPI | `OpenApi\ContractVerifier`, `OpenApiDocument`, `RuleLevel`, `Severity`, `Finding`, `Report`, les 17 règles de `OpenApi\Rule` | Implémenté et testé |
| Règle de vérification | `Contract\OpenApiRule` | Interface |
| Exceptions | `Exception\ApiContractException`, `InvalidContractValue`, `UnreadableDocument` | Implémenté |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis. |
| [Détails de problème](doc/fr/problem-details.md) | La RFC 9457, `create()` pour le serveur et `fromArray()` pour le client, chaque vérification et sa raison. |
| [Deprecation et Sunset](doc/fr/deprecation-and-sunset.md) | Écrire et lire les trois en-têtes, les dates HTTP, l'analyse de `Link`, l'enregistrement des obsolescences observées. |
| [Lecture tolérante](doc/fr/tolerant-reading.md) | Les valeurs d'énumération inconnues : une valeur par défaut, ou un refus dans un champ d'autorité. |
| [Vérification OpenAPI](doc/fr/openapi-verification.md) | Les trois niveaux, les dix-sept règles, les constats et les rapports, les bornes de la lecture. |
| [Points d'extension](doc/fr/extension-points.md) | `ObservedDeprecations`, avec un exemple d'adaptateur; `OpenApiRule`, avec un exemple de règle. |
| [Tests](doc/fr/testing.md) | Ce que fixe la suite, et comment tester vos adaptateurs. |
| [Référence](doc/fr/reference.md) | Chaque type public. |

## Démarrage rapide

```php
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Problem\ProblemDetails;

$problem = ProblemDetails::create(422, 'https://example.com/problems/out-of-stock', 'Out of stock');
// json_encode($problem->toArray()), Content-Type: application/problem+json

$signal = DeprecationSignal::fromHeaders($headers['deprecation'] ?? null, $headers['sunset'] ?? null, $headers['link'] ?? []);
```

```php
use Phil\ApiContract\OpenApi\{ContractVerifier, OpenApiDocument, RuleLevel};

$report = ContractVerifier::withBuiltInRules()->verify(OpenApiDocument::fromJson($json), RuleLevel::Strict);
$report->fails(); // true when one finding is an error
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
