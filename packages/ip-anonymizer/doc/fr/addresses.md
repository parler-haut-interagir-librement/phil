<!-- phil-dev source-sha256=dbbcf40dc67624c9e5cb4381b2e8e1d9ddb28604e575bb7bc1d3e25cb87ef3a5 -->
# Adresses et réseaux

## `IpAddress`

Une adresse est représentée par ses octets dans l'ordre réseau (4 pour IPv4, 16 pour IPv6), avec son `IpFamily`.

**Anonymisez l'adresse du client, pas celle du mandataire.** Derrière un répartiteur de charge ou un CDN, `REMOTE_ADDR`
est l'adresse du mandataire ; déclarez les mandataires sous `framework.trusted_proxies` et `trusted_headers`, puis
passez `Request::getClientIp()`. Sans ce réglage, tous les visiteurs partagent l'empreinte du mandataire, et avec un
en-tête transféré accepté de n'importe qui, un visiteur choisit sa propre adresse.

```php
use Phil\IpAnonymizer\IpAddress;

$address = IpAddress::parse('2001:DB8::0001');     // throws InvalidIpAddress on anything else
$address = IpAddress::tryParse($untrusted);        // null on anything else
$address = IpAddress::fromBytes($packed);          // 4 or 16 bytes, as inet_pton() returns them

$address->family;      // IpFamily::V6
$address->bytes;       // "\x20\x01\x0d\xb8…"
$address->toString();  // '2001:db8::1'
```

### Ce que l'analyseur accepte

Le texte est vérifié avant que `inet_pton()` le lise, pour que toutes les plateformes acceptent les mêmes adresses :

| Texte | Lu comme |
|---|---|
| `192.0.2.1` | IPv4 |
| `2001:db8::1`, `2001:0DB8:0:0::1` | IPv6, écrit `2001:db8::1` |
| `::ffff:192.0.2.1`, `::ffff:c000:201` | l'adresse IPv4 `192.0.2.1` |
| `::192.0.2.1` (compatible IPv4, obsolète) | l'adresse IPv6 `::c000:201` |

Et refuse :

| Texte | Raison |
|---|---|
| Plus de 45 caractères | L'adresse la plus longue est `ffff:ffff:ffff:ffff:ffff:ffff:255.255.255.255`. |
| `fe80::1%eth0` | Un index de zone nomme une interface de l'hôte, et certaines plateformes l'intègrent aux octets. |
| `[::1]`, une espace ou un saut de ligne autour de l'adresse | Les crochets, les espaces et les sauts de ligne appartiennent au texte qui entoure une adresse. |
| `010.0.0.1` | Un zéro initial dans une partie pointée est octal pour certains lecteurs et décimal pour d'autres. |
| `192.0.2`, `0x1.0.0.1`, `localhost` | Ce ne sont pas les quatre parties décimales pointées d'une adresse. |

**Une adresse mappée sur IPv4 est son adresse IPv4.** Un socket double pile signale un client IPv4 comme
`::ffff:192.0.2.1` ; le lire comme `192.0.2.1` donne au client la même troncature, la même empreinte et le même libellé,
quel que soit le socket qui l'a accepté.

### Le texte d'une adresse

`toString()` écrit lui-même le texte canonique de la RFC 5952 plutôt que de passer par `inet_ntop()`, dont la sortie
varie d'une plateforme à l'autre : en décimal pointé pour IPv4 ; pour IPv6, des groupes en minuscules sans zéros
initiaux, la plus longue suite de deux groupes nuls ou plus (la première de suites égales) écrite `::`, et jamais de
partie pointée.

## `IpNetwork`

Un réseau est une adresse dont les bits d'hôte sont effacés, accompagnée d'une longueur de préfixe.

```php
use Phil\IpAnonymizer\IpNetwork;

IpNetwork::containing($address, 48);          // the /48 that holds the address
IpNetwork::parse('192.0.2.0/24');             // CIDR notation
IpNetwork::parse('192.0.2.1/24');             // throws InvalidNetwork: host bits set

$network->contains(IpAddress::parse('192.0.2.77'));   // true
$network->toString();                                  // '192.0.2.0/24'
```

La longueur de préfixe va de 0 à 32 pour IPv4 et de 0 à 128 pour IPv6, écrite en décimal sans zéro initial. Une adresse
de l'autre famille n'est jamais contenue.
