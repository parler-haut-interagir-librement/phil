<!-- phil-dev source-sha256=8d3df4cbce178e66f3e5a19c05e0eaa1dc98b853b61da1426f6b0e3858354101 -->
# Le locataire actif

Un compte d'une application multilocataire peut être membre de plusieurs locataires : détail et gros, deux
organisations, deux boutiques. Le **locataire actif** est celui dans lequel il agit en ce moment. Ce bundle décide
comment il est résolu et changé ; l'application décide où il est mémorisé et d'où vient l'ensemble atteignable.

## La conception

- **Le locataire actif appartient à l'application, jamais au fournisseur d'identité.** La session du fournisseur et
  son jeton d'identité couvrent toutes les applications qui partagent le fournisseur, alors qu'un locataire actif
  appartient à une seule. Le fournisseur sait dans quels locataires un compte *peut* agir. Il ne sait pas, et on ne
  lui demande jamais, dans lequel il agit en ce moment. Rien du locataire actif ne va dans un jeton, une session du
  fournisseur ou un témoin du fournisseur.
- **Le changement est vérifié contre l'ensemble atteignable et rien d'autre.** Aucun aller-retour, aucun nouveau
  jeton, aucune nouvelle connexion : le changement est instantané et continue de fonctionner quand le fournisseur
  d'identité est en panne.
- **Un changement vers un locataire inatteignable est refusé** (`TenantNotReachable`), jamais réduit à un locataire
  atteignable.
- **La résolution ne devine jamais.** Avec plusieurs locataires atteignables et aucun choix utilisable, le compte
  choisit ; le bundle ne prend jamais le premier d'une liste. Voir [resolution.md](resolution.md).
- **L'audit de ce qui se fait dans un locataire revient à l'application.** Le fournisseur d'identité peut auditer
  qui a reçu quoi ; seuls les journaux de l'application peuvent dire ce qu'un compte a fait dans un locataire.

## Les références de locataire : `TenantId`

`Phil\TenancyBundle\Domain\ValueObject\TenantId` est la référence d'un locataire à l'intérieur d'**une** application.
La même référence dans deux applications nomme deux locataires sans lien. Elle n'est donc comparée qu'aux références
de la même application.

| Membre | Comportement |
|---|---|
| `static fromString(string $value): self` | Valide et enveloppe la référence ; lance `InvalidValue` |
| `public string $value` | La référence, exactement telle que donnée |
| `equals(TenantId $other): bool` | Comparaison octet par octet |
| `toString()`, `__toString()` | La référence |
| `const MAX_LENGTH = 255` | La plus longue référence, en octets |

La référence est opaque : un *slug*, un UUID, un nombre, au choix de l'application. `fromString()` ne refuse que ce
qui ne peut pas être une clé stable :

| Refusé | Exemple |
|---|---|
| Vide | `''` |
| Plus de 255 **octets** (pas caractères) | 128 fois `é` font 256 octets |
| UTF-8 invalide | `"\xff\xfe"`, `"retail\xC3"` |
| Des espaces au début ou à la fin, espaces Unicode compris | `' retail'`, `"retail\n"`, `"retail\u{00A0}"`, `"\u{3000}retail"` |
| Un caractère de contrôle n'importe où : C0, DEL, C1 | `"re\x00tail"`, `"re\ttail"`, `"re\u{0085}tail"` |

Une espace intérieure fait partie de la référence (`"north\u{00A0}shore"` est acceptée). **La casse compte** :
`retail` et `Retail` sont deux locataires, car ignorer la casse permettrait au membre de l'un d'agir dans un autre dont
la référence entrerait en collision.

## L'ensemble atteignable : `ReachableTenants`

`Phil\TenancyBundle\Domain\ValueObject\ReachableTenants` est l'ensemble des locataires dans lesquels le compte courant
**peut** agir, selon les droits de l'application. Il est `Countable` et itérable sur des `TenantId`, dans l'ordre
donné.

| Membre | Comportement |
|---|---|
| `static of(TenantId ...$tenants): self` | L'ensemble, dans l'ordre donné ; un doublon lance `InvalidValue` plutôt que d'être fusionné |
| `static none(): self` | L'ensemble vide |
| `contains(TenantId $tenant): bool` | L'appartenance, par `TenantId::equals()` |
| `isEmpty(): bool` | Aucun locataire atteignable |
| `single(): ?TenantId` | Le locataire quand un seul est atteignable, `null` sinon |
| `toList(): list<TenantId>` | Les locataires, en liste |
| `count(): int`, `getIterator()` | `Countable` et `IteratorAggregate` |

- **Un doublon est une erreur.** Il signifie que les droits ont été mal lus, et le retirer en silence le cacherait.
- **Un ensemble vide est légitime.** C'est ce que donnent des droits périmés quand ils échouent en mode fermé, et il
  rend tout locataire inatteignable.

## Le service : `ActiveTenant`

`Phil\TenancyBundle\Application\ActiveTenant` applique les règles aux deux ports. Le bundle l'enregistre, public, dès
que `reachable_tenants` est configuré (voir [configuration.md](configuration.md)).

| Méthode | Comportement |
|---|---|
| `__construct(ActiveTenantStore $store, ReachableTenantsProvider $provider)` | |
| `resolve(): Resolution` | Résout à partir du choix mémorisé et de l'ensemble atteignable ; appelle `$store->forget()` quand le choix mémorisé n'est plus atteignable |
| `canReach(TenantId $tenant): bool` | Si l'ensemble atteignable contient le locataire : ce que `ResolveActiveTenant` vérifie pour un compte connecté sur le locataire d'un hôte |
| `switchTo(TenantId $tenant): void` | Vérifie le locataire contre l'ensemble atteignable et le mémorise ; lance `TenantNotReachable` et laisse le choix mémorisé intact quand il n'est pas atteignable |
| `leave(): void` | Oublie le choix mémorisé |

## Une requête, de bout en bout

1. Le pare-feu authentifie le compte (`kernel.request`).
2. `ResolveActiveTenant`, placé après le pare-feu, appelle `ActiveTenant::resolve()` : le choix mémorisé vient du
   stockage, l'ensemble atteignable du fournisseur de l'application. Un choix qui n'est plus atteignable est oublié.
3. Sur `Outcome::Active`, le locataire est entré dans `TenantContext`, et `DoctrineTenantActivation` le règle sur le
   filtre : chaque requête SQL de la requête HTTP ne voit que les lignes de ce locataire.
4. Sur `Outcome::SelectionRequired`, le compte est envoyé à la route du sélecteur, qui liste `reachable_tenants()` et
   envoie vers le changement. Le changement vérifie le locataire, le mémorise et revient à la page demandée au départ.
5. Sur `Outcome::NoneReachable`, le compte reste connecté et n'agit dans aucun locataire : les entités liées à un
   locataire ne correspondent à rien.
6. Le contrôleur lit le locataire avec `#[CurrentTenant]`, un message réparti le porte dans un `TenantStamp`, et on
   quitte le contexte avant la requête suivante.

Chaque étape se trouve dans [integrations.md](integrations.md). Une application qui veut son propre écouteur désactive
celui du bundle (`request: false`) et appelle elle-même `ActiveTenant::resolve()` et `TenantContext::enter()`, après
l'authentification et avant la première requête liée à un locataire.

## Exceptions

| Exception | Sorte | Lancée par | Quand |
|---|---|---|---|
| `Domain\Exception\TenantNotReachable` | `DomainException`, `DomainError` | `ActiveTenantRule::switchTo()`, `ActiveTenant::switchTo()` | Le locataire demandé n'est pas dans l'ensemble atteignable |
| `Domain\Exception\NoActiveTenant` | `DomainException`, `DomainError` | `Resolution::tenant()`, `TenantContext::require()` | Aucun locataire n'est actif ; son message dit si une sélection est requise, si aucun n'est atteignable, ou si l'unité de travail n'agit dans aucun locataire |
| `InvalidValue` (de `phil/ddd-bundle`) | `InvalidArgumentException`, `DomainError` | `TenantId::fromString()`, `ReachableTenants::of()`, `new TenantScoped('')` | Une référence mal formée, un locataire en double, un nom de champ vide |
