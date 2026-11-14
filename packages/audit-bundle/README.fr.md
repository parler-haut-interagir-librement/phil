<!-- phil-dev source-sha256=c49ac3a7448c13e587b8193d9017ae2b04820c3e7527338303c72d41c05bd9ef -->
# phil/audit-bundle

**Une piste d'audit en ajout seul qui révèle toute falsification et permet quand même d'effacer les renseignements
personnels.**

phil/audit-bundle tient une piste d'audit en ajout seul, chaînée par empreintes, dans une table d'une connexion
Doctrine. L'empreinte SHA-256 de chaque ligne est calculée sur ses champs et sur l'empreinte de la ligne précédente,
par segment. Une ligne de discontinuité consigne une restauration. Les empreintes de bornes de chaque segment sont
ancrées par un `AnchorWriter`, et une commande vérifie chaque segment par rapport à ses ancres et échoue
bruyamment en cas de rupture. La piste résiste à un administrateur qui a accès à la base de données seulement quand les
ancres vont vers un stockage en ajout seul que cet administrateur ne peut pas réécrire. Sans ancres, une réécriture
complète de la chaîne passe inaperçue.

Le bundle existe parce qu'un journal qui révèle les falsifications et le droit à l'effacement tirent en sens
contraires : intégrer une valeur en clair dans la chaîne la rend impossible à supprimer. Ici, un champ personnel entre
dans la chaîne seulement sous forme d'engagement salé par ligne, `HMAC-SHA256(key = salt, message = value)`, avec un sel
d'au moins 128 bits tiré d'un générateur cryptographique et une comparaison en temps constant. Pour effacer le champ,
l'application supprime le texte en clair et le sel et garde l'engagement : chaque empreinte et chaque ancre restent
vérifiables. Le bundle n'effectue pas lui-même cet effacement.

Chaque ligne est indexée par ses faits : qui a agi, ce qui a été fait, sur quel enregistrement, dans quel locataire.
Ce sont des identifiants que l'empreinte couvre, de sorte que le journal se relit par acteur, par action, par
enregistrement et par période, une page à la fois sur ses index. L'historique d'un enregistrement contient les
changements d'état que ses workflows Symfony ont traversés, à côté de tous les autres événements qui le concernent. Le
bundle expose l'état de la chaîne pour un afficheur, limite les lignes et les lectures au locataire courant quand
`phil/tenancy-bundle` est installé, et peut donner à chaque site — chaque locataire — une chaîne qui lui est propre,
ancrée et vérifiée séparément. Il alimente les tableaux de bord avec les faits de chaque ligne, des identifiants
seulement et jamais une charge utile, en JSON Lines, en enregistrements de journal OpenTelemetry ou par un exportateur
de votre cru, au moins une fois et à partir de là où l'alimentation s'est arrêtée. Ce sont des services sans écrans :
`phil/audit-viewer` les montre dans une interface d'administration EasyAdmin.

Il s'adresse aux applications Symfony qui doivent montrer qui a fait quoi à des enregistrements contenant des
renseignements personnels, et il se marie bien avec `phil/privacy-bundle`, qui déclare les champs personnels et
assemble les réponses d'accès et d'effacement. C'est un journal, pas une source d'événements, et il est en lecture seule : revenir à une version antérieure d'un
enregistrement relève du bundle qui garde de vraies versions de celui-ci. Par conception, il ne
fournit pas de sérialisation canonique avant que l'application en fige une, ne stocke pas les ancres, ne partitionne
pas les tables, ne définit pas de rôles de base de données, et ne décide pas quels événements sont audités ni quels
champs sont personnels.

Le bundle s'appuie sur `phil/ddd-bundle` et les horloges PSR-20, avec Doctrine DBAL, les composants Console,
HttpClient, Messenger, Security et Workflow et `phil/tenancy-bundle` en option. Il est fait de ports et
d'adaptateurs : il porte ce que la conception décide, et laisse des ports comme `CanonicalSerializer`,
`AnchorWriter`, `AnchorReader` et `AuditRowRepository` pour ce que l'application décide.

## État

| Type | État |
|---|---|
| `Domain\Commitment\Salt`, `Domain\Commitment\Commitment` | Implémenté et testé |
| `Domain\Contract\CanonicalSerializer` | Port seulement; aucune implémentation fournie, à dessein (une porte à sens unique) |
| `Domain\Contract\AnchorWriter`, `AnchorReader` | Ports; `JsonLinesAnchorStore` pour le développement |
| `AuditRow`, `RowKind`, `ChainVerifier`, `ChainBreak`, `Anchor`, `Domain\Contract\AuditRowRepository` | Implémenté et testé |
| `SiteSegment`; `site_segments`, `phil:audit:verify --tenant` | Implémenté et testé |
| `DashboardFeed`, `ExportedFact`, `FactExporter`, `FeedCheckpoints`; `JsonLinesFactExporter`, `OtlpLogExporter`, `DbalFeedCheckpoints`, `phil:audit:feed`, `FeedOnRecorded` | Implémenté et testé |
| `AuditTrail` (événements, discontinuités), `Anchoring`, `TrailVerification` | Implémenté et testé |
| `DbalAuditRowRepository` et sa table; `phil:audit:anchor`, `phil:audit:verify` | Implémenté et testé |
| `Facts`, `Subject`; `AuditQuery`, `Cursor`, `AuditPage`; `AuditLog`, `ChainStatus` | Implémenté et testé |
| `DbalAuditLogReader`, `ScanningAuditLogReader` | Implémenté et testé |
| Locataire, acteur et enregistrement comparés octet par octet (collation binaire, puis `===` en PHP) | Implémenté et testé |
| `WorkflowAuditListener`, `MessengerAuditNotifier`, `TenancyTenantScope`, `SecurityActorResolver` | Implémenté et testé; enregistré quand son composant l'est |
| `PayloadReader` | Port seulement; l'inverse du sérialiseur de l'application |
| Effacement d'un champ | Relève de l'application : le texte en clair et le sel supprimés, l'engagement gardé |
| `PhilAuditBundle` | Configuration `connection`, `serializer`, `anchor_writer`, `anchor_reader`, `anchor_file`, `dispatch_bus`, `workflow_segment`, `site_segments`, `feed` |

## Documentation

- [Installation](doc/fr/installation.md) : les prérequis, y compris la raison pour laquelle `phil/ddd-bundle` et
  `symfony/config` sont déclarés, et l'enregistrement du bundle.
- [Configuration](doc/fr/configuration.md) : les clés sous `phil_audit` (`connection`, `serializer`, `anchor_writer`,
  `anchor_reader`, `anchor_file`, `dispatch_bus`, `workflow_segment`, `site_segments`, `feed`), les services câblés,
  et la façon d'enregistrer vos propres adaptateurs.
- [Lire le journal](doc/fr/reading.md) : les faits qui indexent une ligne, `AuditQuery`, les pages et les curseurs,
  `AuditLog`, l'historique d'un enregistrement, `ChainStatus` et les deux lecteurs.
- [Intégrations](doc/fr/integrations.md) : les changements d'état de Symfony Workflow, le compte courant du composant
  Security, le locataire de `phil/tenancy-bundle`, et l'envoi aux tableaux de bord avec Messenger.
- [Une chaîne par site](doc/fr/sites.md) : les segments de site, ce que la vérification ajoute, ce qu'un locataire
  voit, et `SiteSegment`.
- [Alimenter les tableaux de bord](doc/fr/dashboards.md) : l'alimentation, ses emplacements, au moins une fois et
  bornée, les exportateurs JSON Lines et OpenTelemetry, et un exportateur de votre cru.
- [La chaîne d'empreintes](doc/fr/hash-chain.md) : le modèle de menace et la conception complète (lignes chaînées,
  sérialisation versionnée, effacement qui garde l'empreinte, lignes de discontinuité, vérification par segment), avec
  ce qui existe de chaque élément.
- [Engagements salés et effacement](doc/fr/commitments-and-erasure.md) : pourquoi HMAC, `Salt` et `Commitment` méthode
  par méthode, le stockage et le contrat d'effacement.
- [L'ancrage](doc/fr/anchoring.md) : pourquoi les ancres décident si la piste tient, les destinations acceptables et le
  port `AnchorWriter`.
- [La porte de la sérialisation](doc/fr/serialization-door.md) : pourquoi le bundle ne fournit aucune sérialisation,
  le versionnage, les décisions qu'une implémentation doit figer, et un exemple.
- [Points d'extension](doc/fr/extension-points.md) : chaque port méthode par méthode, les invariants d'un
  adaptateur, un registre de sérialiseurs, un auteur d'ancres sur verrouillage d'objets et un lecteur de charge
  utile.
- [Tests](doc/fr/testing.md) : comment le bundle est testé, ses bancs d'essai et ses cibles de fuzzing, l'exécution de
  ses tests dans un monodépôt ou seul, des vecteurs de référence pour un sérialiseur et des tests de refus pour une
  destination d'ancres.
- [Référence de l'API](doc/fr/reference.md) : chaque type par couche, avec son rôle et son état.

## Démarrage rapide

```bash
composer require phil/audit-bundle:^0.1@dev
```

```php
use Phil\AuditBundle\Domain\Commitment\Commitment;
use Phil\AuditBundle\Domain\Commitment\Salt;

$salt = Salt::generate();                      // 16 bytes from the CSPRNG, stored beside the plaintext
$commitment = Commitment::of($salt, $email);   // 32 bytes, kept forever, hashed into the row

// Erasure: delete the plaintext and the salt, keep the commitment. The row hash does not move.
Commitment::fromBytes($stored)->matches(Salt::fromBytes($storedSalt), $email); // constant time
```

```php
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditQuery;

$trail->record('orders', ['amount' => '10.00'], Facts::of('order.paid', $account->id(), Subject::of('order', '42')));

$page = $log->page(AuditQuery::all()->byActor($account->id())->limitedTo(100));   // the latest first
$history = $log->history(Subject::of('order', '42'));                              // the earliest first
```

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    site_segments: true                                   # one chain per tenant
    feed:
        json_lines: '%kernel.logs_dir%/audit-facts.jsonl' # facts for the dashboards, identifiers only
```

Avant d'ancrer quoi que ce soit, écrivez et figez votre `CanonicalSerializer`, et dirigez votre `AnchorWriter` vers un
stockage en ajout seul.

## Prérequis

PHP 8.5 ou plus récent, `phil/ddd-bundle` 0.1, `psr/clock` 1.0, `symfony/config` et `symfony/dependency-injection`
8.2.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
