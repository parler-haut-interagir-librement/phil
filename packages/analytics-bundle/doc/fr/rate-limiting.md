<!-- phil-dev source-sha256=4f5d4a665bc5f5af2f59c5fc9ba6807702f44743464d8a35398f32d535cd8a36 -->
# Limitation de débit et empreinte du client

Le point de collecte est public et fonctionne sans identifiants : un limiteur de débit **par site et par empreinte de
client à clé** le borne. L'empreinte est la seule trace de l'adresse du client que garde le limiteur.

| Partie | État |
|---|---|
| `ClientDigester`, `ClientDigest` | Implémenté et testé |
| `Phil\IpAnonymizer\Contract\SaltProvider` (le sel de la période) | De `phil/ip-anonymizer` ; `ForgottenSalt` par défaut |
| `CollectionRateLimiter` (le limiteur) | Contrat ; adaptateur par défaut `SymfonyCollectionRateLimiter` |
| `CacheSaltStore` (où le sel du jour est partagé) | Implémenté et testé, l'`ExpiringSaltStore` par défaut |

## `ClientDigester`

`Phil\AnalyticsBundle\Domain\Collection\ClientDigester` compose `phil/ip-anonymizer` : un `Pipeline` qui applique
`Truncation(32, 64)`, puis `KeyedHash` sous le fournisseur de sel qu'on lui donne.

```php
use Phil\IpAnonymizer\ForgottenSalt;

$digester = new ClientDigester(new ForgottenSalt($saltStore, $clock));
$digest = $digester->digestOf('192.0.2.1');
$digest->toString();  // '86400-20400:<32 hex characters>' — the form a rate limiter keys on
$digest->equals($digester->digestOf('::ffff:192.0.2.1')); // true
```

| Entrée | Ce qui est condensé |
|---|---|
| IPv4 `192.0.2.1` | Les quatre octets de l'adresse |
| IPv6 qui représente une IPv4 `::ffff:192.0.2.1` | L'adresse IPv4 qu'elle représente |
| Toute autre adresse IPv6 | **Son préfixe /64**, le reste mis à zéro |
| Tout le reste (vide, un nom d'hôte, `1.2.3.4.5`) | Lance `InvalidValue` |

Pourquoi le /64 : un seul hôte IPv6 change librement d'adresse dans son /64, et un budget par adresse ne le bornerait
pas. Toutes les adresses d'un /64 sont donc une seule empreinte. `::1` et la forme obsolète compatible IPv4
`::192.0.2.1` sont traitées comme de l'IPv6.

**Le sel.** `ForgottenSalt` tire un sel aléatoire par jour UTC et le partage entre les serveurs par un
`ExpiringSaltStore`, qui l'oublie à la fin du jour : personne ne peut relier les empreintes de deux jours, pas même
l'exploitant. Ses sels vivent dans le même genre de stockage que l'état du limiteur. `RotatingSalt`, dérivé d'une clé
maîtresse, ne stocke rien, et son détenteur peut relier toutes les périodes. Un nouveau sel commence un nouvel ensemble
d'empreintes et l'identifiant du sel fait partie du texte : deux sels ne partagent jamais une entrée du limiteur, et la
fin d'une période remet simplement à zéro les comptes du limiteur.

## `ClientDigest`

`Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest` est le texte de l'empreinte, dans son propre type : on ne peut
pas donner par erreur une adresse brute à un limiteur, et `fromString()` refuse tout ce qui n'est pas
`<salt id>:<hexadecimal digest>`.

L'empreinte n'est pas un secret : c'est une clé que le limiteur stocke en clair, et le sel n'y passe jamais. `equals()`
compare quand même en temps constant (`hash_equals()`).

## Le contrat

```php
interface CollectionRateLimiter
{
    /** Whether one more send from this digest is within the site's budget. */
    public function consume(SiteId $site, ClientDigest $digest): bool;
}
```

Invariants d'un adaptateur `CollectionRateLimiter` :

- **Il est fermé en cas d'échec.** Quand son stockage ne peut pas répondre, `consume()` retourne `false` (ou lance une
  exception) : une page vue perdue vaut mieux qu'un compteur sans borne.
- Son état contient l'empreinte et **jamais l'adresse**, avec une durée de vie qui ne dépasse pas la fenêtre du budget,
  dans un stockage qui ne la persiste pas (aucun fichier en ajout seul, aucun instantané).
- **Calibrez le budget sur un groupe derrière une seule adresse** (le Wi-Fi d'un congrès, une traduction d'adresses
  d'opérateur), pas sur un seul visiteur. Une page vue est suivie d'un second envoi quand on quitte la page, et d'un
  envoi par événement : comptez plusieurs envois par page.
- Il n'est consommé **qu'après** la vérification de l'état du site : un site suspendu ou retiré ne dépense aucun budget
  et ne laisse aucune empreinte ([collection-and-admission.md](collection-and-admission.md)).

## L'adaptateur par défaut

`SymfonyCollectionRateLimiter` prend un limiteur d'une fabrique de Symfony Rate Limiter pour chaque site et chaque
empreinte, avec pour clé le hachage des deux, et consomme un envoi. Le bundle configure la fabrique en fenêtre glissante
de `rate_limiter.limit` envois par `rate_limiter.interval` (600 par 10 minutes par défaut), son état dans
`rate_limiter.cache_pool` ([configuration.md](configuration.md#le-limiteur-de-débit)). Toute exception du limiteur ou de
son stockage répond `false` : la page vue est abandonnée.

Le sel de l'empreinte vient de `ForgottenSalt` sur `CacheSaltStore` : un sel aléatoire par jour UTC dans
`salt.cache_pool`, réclamé sous un verrou quand une fabrique de verrous est donnée, et oublié par la réserve à la fin du
jour ([configuration.md](configuration.md#le-sel)).

Un autre adaptateur, écrit par l'application, est dans [extension-points.md](extension-points.md).
