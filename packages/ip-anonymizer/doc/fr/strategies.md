<!-- phil-dev source-sha256=43a8fb4760570550799ca59cd68a1c6376adc6367c38561c8c3adf55026a1e3f -->
# Stratégies

Chaque stratégie implémente `Phil\IpAnonymizer\Contract\IpAnonymizer` et retourne une chaîne :

```php
interface IpAnonymizer
{
    public function anonymize(IpAddress $address): string;
}
```

## Troncature

`Truncation` efface les bits d'hôte au-delà d'un préfixe propre à chaque famille :

```php
use Phil\IpAnonymizer\Truncation;

$truncation = new Truncation();                       // /24 for IPv4, /48 for IPv6
$truncation->anonymize(IpAddress::parse('203.0.113.45'));            // '203.0.113.0'
$truncation->anonymize(IpAddress::parse('2001:db8:1234:5678::1'));   // '2001:db8:1234::'

new Truncation(16, 32);                               // shorter prefixes hide more
$truncation->transform($address);                     // the truncated IpAddress itself
```

Un /24 laisse 256 hôtes IPv4 possibles. Un /48 IPv6 est en général toute l'attribution d'une organisation, un /56 ou un
/64 un seul foyer : une troncature IPv6 à /64 équivaut presque à aucune anonymisation pour un client résidentiel.

## Hachage à clé

`KeyedHash` calcule l'empreinte BLAKE2b (`sodium_crypto_generichash()`) des octets de l'adresse, avec comme clé le sel
courant d'un `SaltProvider` :

```php
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\ForgottenSalt;

$hash = new KeyedHash(new ForgottenSalt($saltStore, $clock));   // a new random salt each UTC day
$hash->anonymize($address);    // '86400-20400:5f0c9b1e…', 16 bytes of digest by default
$hash->digest($address);       // the IpDigest: saltId and bytes
new KeyedHash($salts, 32);     // 16 to 64 bytes of digest
```

La même adresse donne la même valeur tant que dure le sel : les visites peuvent être comptées et le débit des clients
limité pendant la période. Un autre sel donne des valeurs sans lien, et son identifiant, en tête du texte, empêche les
valeurs de deux périodes d'être jamais égales. Sans le sel, l'empreinte ne peut pas être inversée en calculant
l'empreinte de chaque adresse : voir [Sels](salts.md).

## Généralisation

### À un réseau

`NetworkGeneralisation` écrit en notation CIDR le réseau qui contient l'adresse, pour que le texte dise quelle part a
été conservée :

```php
use Phil\IpAnonymizer\NetworkGeneralisation;

new NetworkGeneralisation()->anonymize(IpAddress::parse('203.0.113.45'));   // '203.0.113.0/24'
new NetworkGeneralisation(16, 32)->network($address);                       // the IpNetwork itself
```

### À une portée

`ScopeGeneralisation` ne garde que l'`AddressScope` de l'adresse, tirée des registres d'adresses à usage particulier de
l'IANA : `unspecified`, `loopback`, `private`, `shared`, `link-local`, `documentation`, `benchmarking`, `translation`,
`multicast`, `reserved` ou `public`.

```php
use Phil\IpAnonymizer\ScopeClassifier;
use Phil\IpAnonymizer\ScopeGeneralisation;

new ScopeGeneralisation()->anonymize(IpAddress::parse('10.1.2.3'));    // 'private'
new ScopeClassifier()->scopeOf($address);                              // AddressScope::Public
```

Les adresses locales uniques `fc00::/7` et les anciennes adresses de site `fec0::/10` sont `private`, comme les plages
IPv4 privées. Une adresse IPv6 hors de l'unicast global `2000::/3` et des plages listées est `reserved`.

### À un libellé

`LabelGeneralisation` donne le libellé du réseau qui contient l'adresse, tiré d'un `NetworkLabeller`, et interroge sinon
sa stratégie de repli, la portée par défaut :

```php
use Phil\IpAnonymizer\LabelGeneralisation;
use Phil\IpAnonymizer\NetworkLabels;

$labels = new NetworkLabels([
    '192.0.2.0/24' => 'office',
    '198.51.100.0/22' => 'partner-north',
    '2001:db8::/32' => 'region-east',
]);

new LabelGeneralisation($labels)->anonymize(IpAddress::parse('192.0.2.9'));        // 'office'
new LabelGeneralisation($labels)->anonymize(IpAddress::parse('8.8.8.8'));          // 'public'
new LabelGeneralisation($labels, new Truncation())->anonymize($elsewhere);         // the truncated address
```

`NetworkLabels` prend le libellé du plus long préfixe qui contient l'adresse. La table appartient à l'application (ses
bureaux, ses partenaires, des régions qu'elle a compilées) : aucune base de géolocalisation n'est donc nécessaire.
Chaque libellé compte de 1 à 64 octets ; chaque réseau est en notation CIDR, avec ses bits d'hôte effacés, et listé une
seule fois. Une recherche coûte un masque et une consultation de table de hachage par longueur de préfixe distincte de
la famille, au plus 33 pour IPv4 et 129 pour IPv6, quelle que soit la taille de la table.

## Composer les stratégies

`Pipeline` exécute des étapes `IpTransformer` dans leur ordre, puis une stratégie. `Truncation` est à la fois un
transformateur et une stratégie :

```php
use Phil\IpAnonymizer\Pipeline;

// Rate-limit by IPv4 address and by IPv6 /64, without keeping either.
$limiterKey = new Pipeline(new KeyedHash($salts), new Truncation(32, 64));

// Count visits by /24 and /48 networks, with values that change each day.
$networkVisits = new Pipeline(new KeyedHash($dailySalts), new Truncation());
```

`PerFamily` donne à chaque famille sa propre stratégie :

```php
use Phil\IpAnonymizer\PerFamily;

new PerFamily(new Truncation(), new NetworkGeneralisation(24, 56));
```
