<!-- phil-dev source-sha256=8a02d601e75198b8e0feab187972ed17c4522b5abcd634d360563e5ea0bb467a -->
# Le limiteur des tentatives de paiement

Le limiteur ralentit les tentatives de paiement répétées, par commande et par réseau du client, avec **un seul état
par clé** et aucune mémoire de la raison pour laquelle une tentative a été ralentie. Il est écrit en fonctions
pures ; l'application stocke les états et décide de ce que déclenche un verdict de robot.

| Type | Rôle |
|---|---|
| `Domain\ValueObject\PaymentAttemptPolicy` | Le budget, le pas de pénalité et la fenêtre |
| `Domain\ValueObject\PaymentAttemptKey` | La clé d'un état : une commande, ou un réseau du client |
| `Domain\ValueObject\PaymentAttemptState` | L'unique état d'une clé : les tentatives, l'attente cumulée, la dernière tentative |
| `Domain\ValueObject\PaymentAttemptDecision` | Permise maintenant, ou nouvelle tentative à une heure donnée ; jamais une raison |
| `Domain\Service\PaymentAttemptLimiter` | `decide()` et `record()` sur l'état d'une clé |
| `Application\Port\PaymentAttemptStateStore` | Port : stocke un état par clé avec une durée de vie |

## Les règles

- **Un verdict de robot ajoute de la friction et ne refuse jamais à lui seul.** Un verdict de robot peut être
  falsifié et peut se tromper ; un faux positif doit quand même pouvoir payer. L'application impose soit un défi
  d'authentification plus fort à la tentative (3-D Secure, là où son processeur le permet), soit enregistre la
  tentative **avec une pénalité**, qui ajoute un pas d'attente. Le contrôle des risques du processeur prend la
  décision sur le paiement.
- **L'acheteur voit une attente, jamais un refus qui nomme un verdict.** Une décision porte une heure de nouvelle
  tentative et rien d'autre.
- **Chaque clé tient un état, et un seul.** Une pénalité se fond dans l'attente cumulée : il n'y a pas de compteur de
  pénalités à part, et le verdict n'est jamais stocké. L'état ne peut pas dire pourquoi son attente a augmenté.
- **L'état expire une fenêtre après sa dernière tentative**, et le stockage doit respecter cette durée de vie : une
  adresse IP du client est un renseignement personnel, et la fenêtre borne la durée pendant laquelle elle est gardée.
- **Les deux clés sont évaluées, la décision la plus restrictive l'emporte.** Une commande et le réseau du client ont
  chacun leur état.
- **Une tentative faite pendant une attente n'est pas enregistrée** : réessayer pendant l'attente (un acheteur qui
  rafraîchit la page, un voisin derrière la même adresse) ne repousse jamais l'attente.
- **Les verdicts de robot sont comptés en agrégat seulement, et pas ici** : le verdict vient de
  `phil/bot-detection-bundle`, dont le `VerdictHandler` le compte et retourne la friction à appliquer (voyez
  ci-dessous).

## Le verdict au paiement

Le bundle ne lit pas les verdicts : le parcours de paiement remet le verdict qu'a envoyé le navigateur au
`VerdictHandler` de `phil/bot-detection-bundle`, câblé pour le paiement avec son propre `RobotVerdictCounter`, et
transforme la friction qu'il retourne en pénalité — ou en défi d'authentification plus fort
([ci-dessous](#une-tentative-de-paiement-de-bout-en-bout)). Rien d'identifiant n'atteint ce compteur, et le verdict
ne va pas plus loin. La façon de câbler un *handler* par surface est décrite dans le `doc/verdict-handling.md` de ce
bundle.

## `PaymentAttemptPolicy`

| Membre | Comportement |
|---|---|
| `static of(int $budget, int $penaltyStepSeconds, int $windowSeconds): self` | Valide les trois valeurs |
| `public int $budget` | Les tentatives qu'une clé peut faire dans sa fenêtre |
| `public int $penaltyStepSeconds` | L'attente qu'ajoute une tentative pénalisée |
| `public int $windowSeconds` | La durée de vie d'un état, renouvelée par chaque tentative |
| `longestCumulativeWaitSeconds(): int` | `budget × step` |

Refus, tous `InvalidValue` : un budget inférieur à 1 ; un pas inférieur à une seconde ; `budget × step` au-delà d'un
entier natif ; **une fenêtre qui ne dépasse pas `budget × step`**. Une fenêtre égale à l'attente la plus longue
laisserait un état expirer au moment même où se termine sa dernière pénalité, une pénalité qui ne ralentirait jamais
rien.

Les trois valeurs sont la décision de l'application ; le bundle n'a aucune valeur par défaut.

## `PaymentAttemptKey`

| Membre | Comportement |
|---|---|
| `static forOrder(string $orderId): self` | La valeur `order:<id>`. Refuse un identifiant vide, des espaces aux extrémités, un caractère de contrôle (C0 ou DEL), ou plus de 255 octets |
| `static forClientIp(string $ip, int $ipv6PrefixLength = 64): self` | La valeur `ip:<network>`. Refuse ce qui n'est pas une adresse IP (un nom d'hôte, une adresse avec un port), et une longueur de préfixe hors de 1 à 128 |
| `const DEFAULT_IPV6_PREFIX_LENGTH = 64` | |
| `public string $value` | La clé dans le stockage |
| `equals(PaymentAttemptKey $other): bool` | Même valeur |

Comment une adresse du client devient une clé :

| Entrée | Clé |
|---|---|
| `203.0.113.7` | `ip:203.0.113.7` (la longueur du préfixe est ignorée pour IPv4) |
| `::ffff:203.0.113.7`, une adresse IPv4 mappée | `ip:203.0.113.7` |
| `2001:0DB8:0000:0000:0000:0000:0000:0001` | `ip:2001:db8::/64` |
| `2001:db8::1` | `ip:2001:db8::/64` |
| `2001:db8:0:1:ffff:ffff:ffff:ffff` | `ip:2001:db8:0:1::/64` |
| `2001:db8:abcd:1234::1` avec le préfixe 48 | `ip:2001:db8:abcd::/48` |

- **Une clé IPv6 est le réseau du client, pas son adresse.** Un client IPv6 tient au moins un /64 et peut changer
  d'adresse à chaque tentative ; une nouvelle adresse ne doit pas acheter un nouveau budget.
- **Les adresses sont normalisées par leur forme binaire** : deux graphies d'une même adresse partagent un état.
- **Les clés de commande et d'IP n'entrent jamais en collision** : leurs préfixes diffèrent.
- Passez l'adresse du client telle que la résout votre configuration de mandataires de confiance
  (`Request::getClientIp()` avec `trusted_proxies` défini), jamais un en-tête `X-Forwarded-For` brut.

## `PaymentAttemptState`

| Membre | Comportement |
|---|---|
| `static of(int $attempts, int $cumulativeWaitSeconds, DateTimeImmutable $lastAttemptAt): self` | Pour relire un état stocké. Refuse moins d'une tentative et une attente négative (`InvalidValue`) |
| `public int $attempts`, `public int $cumulativeWaitSeconds`, `public DateTimeImmutable $lastAttemptAt` | Les trois champs, et rien d'autre |
| `expiresAt(PaymentAttemptPolicy $policy): DateTimeImmutable` | La dernière tentative plus la fenêtre |
| `isExpiredAt(DateTimeImmutable $now, PaymentAttemptPolicy $policy): bool` | `$now` à `expiresAt()` ou après |
| `nextAttemptAllowedAt(): DateTimeImmutable` | La dernière tentative plus l'attente cumulée |

## `PaymentAttemptDecision`

| Membre | Comportement |
|---|---|
| `static allowed(): self` | La tentative peut avoir lieu maintenant |
| `static waitUntil(DateTimeImmutable $retryAt): self` | La tentative attend |
| `public ?DateTimeImmutable $retryAt` | `null` quand elle est permise |
| `isAllowed(): bool` | |
| `retryAfterSeconds(DateTimeImmutable $now): int` | La valeur d'un en-tête `Retry-After` : des secondes entières, arrondies vers le haut ; zéro quand elle est permise ou que l'heure de nouvelle tentative est passée |
| `static mostRestrictive(self $first, self ...$others): self` | La nouvelle tentative la plus tardive l'emporte ; toute attente l'emporte sur une permission ; en cas d'égalité, la première est gardée |

## `PaymentAttemptLimiter`

```php
final readonly class PaymentAttemptLimiter
{
    public function __construct(PaymentAttemptPolicy $policy);
    public function decide(?PaymentAttemptState $state, DateTimeImmutable $now): PaymentAttemptDecision;
    public function record(?PaymentAttemptState $state, DateTimeImmutable $now, bool $penalise): PaymentAttemptState;
    public function timeToLiveSeconds(): int; // the policy's window
}
```

`decide()` :

| État | Décision |
|---|---|
| Aucun, ou expiré | permise |
| Budget épuisé (`attempts >= budget`) | attendre l'expiration de l'état |
| Sinon, avant `lastAttemptAt + cumulativeWait` | attendre jusque-là |
| Sinon | permise |

`record()` :

- Quand `decide()` ferait attendre la tentative, retourne l'état **inchangé**.
- Sinon, repart de zéro quand il n'y a pas d'état ou qu'il a expiré, ajoute une tentative, fixe la dernière tentative
  à `$now` et, quand `$penalise` est vrai, ajoute un pas de pénalité à l'attente cumulée, plafonnée à
  `budget × step`.

Avec un budget de 3, un pas de 10 s et une fenêtre de 100 s : une première tentative pénalisée à 0 s fait attendre la
suivante jusqu'à 10 s ; une deuxième tentative pénalisée à 10 s fait attendre la suivante jusqu'à 30 s ; une
troisième tentative à 30 s épuise le budget, et la clé attend jusqu'à 130 s, une fenêtre après cette dernière
tentative.

## Une tentative de paiement, de bout en bout

```php
$now = $clock->now();
$keys = [PaymentAttemptKey::forOrder($orderId), PaymentAttemptKey::forClientIp((string) $request->getClientIp())];
$states = array_map($store->find(...), $keys);

$decision = PaymentAttemptDecision::mostRestrictive(...array_map(
    static fn (?PaymentAttemptState $state): PaymentAttemptDecision => $limiter->decide($state, $now),
    $states,
));

if (!$decision->isAllowed()) {
    return new Response('', 429, ['Retry-After' => (string) $decision->retryAfterSeconds($now)]);
}

// The verdict the browser sent, counted and turned into friction by phil/bot-detection-bundle, then dropped.
$friction = $paymentVerdicts->handle(Verdict::fromSubmitted($request->request->get('verdict')));
$penalise = $friction->adds() && !$processorCanForceChallenge;

foreach ($keys as $i => $key) {
    $store->save($key, $limiter->record($states[$i], $now, $penalise), $limiter->timeToLiveSeconds());
}

// Hand the attempt to the processor, with a forced authentication challenge when $friction->adds() and the processor allows it.
```

N'enregistrez qu'après une tentative permise ; une tentative en attente ne change rien.

## Ce que le limiteur ne fait pas

- Il ne détecte pas les robots : le verdict vient de l'application (un verdict robot ou humain évalué par le
  navigateur, par exemple), et seule la décision booléenne de pénaliser atteint le limiteur.
- Il ne refuse pas un paiement, ne décide pas de son risque et ne parle pas à un processeur.
- Il ne stocke rien lui-même ; voyez `PaymentAttemptStateStore` dans [extension-points.md](extension-points.md).
