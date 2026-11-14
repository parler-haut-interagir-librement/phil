<!-- phil-dev source-sha256=e9e9c90ed8fd7794e85e562d8b2b04ebcab701143d42abf8c3370a38238ccbd8 -->
# Points d'extension

L'application implémente les locataires atteignables et marque ses entités ; elle peut remplacer le stockage et
ajouter des activations.

| Point d'extension | Sorte | Implémenté par | Branchement |
|---|---|---|---|
| `Application\Contract\ReachableTenantsProvider` | port | l'application | son identifiant de service dans `phil_tenancy.reachable_tenants` |
| `Application\Contract\ActiveTenantStore` | port | le bundle (`SessionActiveTenantStore`), ou l'application | `phil_tenancy.store` : `session`, ou l'identifiant de service de l'adaptateur |
| `Application\Contract\TenantActivation` | port | le bundle (`DoctrineTenantActivation`), et l'application pour tout ce qui suit aussi le locataire | configuration automatique, ou l'étiquette `phil_tenancy.activation` |
| `Application\Contract\HostTenants` | port | le bundle (`ConfiguredHostTenants`), ou l'application | `phil_tenancy.hosts`, ou un alias du port vers l'adaptateur |
| `Application\Contract\HostAliases` | port | le bundle (`ConfiguredHostTenants`), ou l'application | `phil_tenancy.host_aliases`, ou un alias du port vers l'adaptateur |
| `Application\Contract\AccountSharing` | port | le bundle (`ConfiguredAccountSharing`), ou l'application | `phil_tenancy.authentication.account_sharing` |
| `Application\Contract\TenantPlacement` | port | un bundle de base de données, ou l'application | le bundle qui déplace la connexion le lit ; le bundle lui-même ne le lit jamais |
| `Infrastructure\Mailer\Contract\TenantMailSettings` | port | le bundle (`ConfiguredTenantMailSettings`), ou l'application | `phil_tenancy.mailer.settings` |
| `Application\Event\HostTenantResolved` | événement | le bundle l'envoie | un écouteur de l'application ou d'un bundle de commerce |
| `Domain\Attribute\TenantScoped` | attribut de classe | chaque entité racine appartenant à un locataire | aucun ; lu par `ActiveTenantFilter` |
| `Infrastructure\Doctrine\ActiveTenantFilter` | filtre de l'ORM | le bundle | enregistré activé par le bundle (`doctrine_filter`), ou par l'application sous `ActiveTenantFilter::NAME` |

`TenantActivation` est décrit dans [integrations.md](integrations.md), `HostTenants` et le réseau de boutiques dans
[hosts-and-shops.md](hosts-and-shops.md), `AccountSharing` dans [authentication.md](authentication.md), et
`TenantMailSettings` dans [mail.md](mail.md).

## `HostTenants`

| Méthode | Contrat |
|---|---|
| `tenantFor(string $host): ?TenantId` | Le locataire que l'hôte sert, ou `null` ; l'hôte arrive en minuscules et sous sa forme ASCII |

Invariants : appelé une fois par requête principale, avant le pare-feu — gardez-le peu coûteux, depuis la mémoire ou un
cache ; jamais un locataire pour un hôte que l'application ne sert pas. Branchez un adaptateur en donnant un alias au
port, avec `hosts` laissé vide et l'écouteur enregistré comme le bundle l'enregistre :

```php
$services->alias(HostTenants::class, StoredHostTenants::class);
$services->set(ResolveHostTenant::class)
    ->args([service(HostTenants::class), service(TenantContext::class), service('event_dispatcher')])
    ->tag('kernel.event_listener', ['event' => 'kernel.request', 'before' => ['security.firewall', 'debug.security.firewall', ResolveActiveTenant::class]]);
```

## `HostAliases`

| Méthode | Contrat |
|---|---|
| `canonicalHostOf(string $host): ?string` | L'hôte canonique que l'alias représente, ou `null` pour un hôte qui n'est pas un alias ; les deux en minuscules et sous leur forme ASCII |

Invariants : appelé une fois par requête principale, avant `ResolveHostTenant` — gardez-le peu coûteux ; l'hôte canonique
est l'un des hôtes de l'application, jamais une valeur prise de la requête. Le `HostTenants` de l'adaptateur répond à un
alias par le locataire de son hôte canonique. Branchez-le comme `HostTenants` :

```php
$services->alias(HostAliases::class, StoredHostTenants::class);
$services->set(RedirectHostAlias::class)
    ->args([service(HostAliases::class)])
    ->tag('kernel.event_listener', ['event' => 'kernel.request', 'before' => [ResolveHostTenant::class, 'security.firewall', 'debug.security.firewall']]);
```

## `TenantPlacement`

Où les données de chaque locataire vivent : dans la base partagée, séparées des autres locataires par le filtre
Doctrine, ou dans une base à elles. Le bundle ne déclare que la question et la réponse ; un bundle qui déplace la
connexion Doctrine avec le locataire actif, ou l'application, l'implémente.

| Méthode | Contrat |
|---|---|
| `placementOf(TenantId $tenant): Placement` | `Placement::shared()`, ou `Placement::dedicated('tenant_retail')` |

Invariants :

- **Demandé à chaque entrée dans un locataire** : gardez-le peu coûteux — depuis la mémoire, un cache, ou une requête
  indexée sur une connexion qui ne suit pas elle-même le locataire.
- **Un locataire que l'application ne connaît pas est partagé**, où le filtre ne lui montre rien.
- **Un emplacement nomme une base, jamais comment l'atteindre.** `Placement::dedicated()` accepte des lettres ASCII
  minuscules, des chiffres et des tirets bas, en commençant par une lettre, 63 octets au plus : un nom identique sur
  toutes les plateformes et qui ne peut jamais être lu comme du SQL. Le serveur et le compte de cette base regardent
  celui qui déplace la connexion.

`Placement::equals()` compare les bases, et `toString()` donne `shared` ou le nom de la base, pour une ligne de
journal.

## `ActiveTenantStore`

L'endroit où l'application mémorise le locataire que le compte a choisi.

| Méthode | Contrat |
|---|---|
| `remembered(): ?TenantId` | Le choix mémorisé, ou `null` quand il n'y en a pas |
| `remember(TenantId $tenant): void` | Mémorise ce choix, en remplaçant le précédent |
| `forget(): void` | Oublie le choix ; un `remembered()` ultérieur retourne `null` |

Invariants :

- **La sémantique appartient à l'application** : persistant par onglet du navigateur, par session, porté dans l'URL.
  Aucune de ces options ne peut être décidée honnêtement pour toutes les applications.
- **Jamais dans la session du fournisseur d'identité, un jeton d'identité ou un témoin du fournisseur**, qui couvrent
  toutes les applications alors qu'un locataire actif n'appartient qu'à une seule.
- `remember()` ne reçoit que des locataires que la règle a déjà vérifiés ; le stockage ne vérifie pas de nouveau.

Le `SessionActiveTenantStore` du bundle garde le choix dans la session Symfony de l'application (jamais dans celle du
fournisseur d'identité), sous `_phil_tenancy.active_tenant` : un choix par navigateur, pour la durée de la session.
Une requête sans témoin de session ne mémorise rien et ne démarre aucune session ; une valeur enregistrée qui n'est
plus une référence valide est abandonnée. Écrivez un autre stockage pour une autre sémantique (un choix par onglet du
navigateur, porté dans l'URL) et nommez-le dans `phil_tenancy.store` :

```php
final readonly class UrlActiveTenantStore implements ActiveTenantStore
{
    public function __construct(private RequestStack $requests) {}

    public function remembered(): ?TenantId
    {
        $value = $this->requests->getMainRequest()?->attributes->get('tenant');

        return \is_string($value) ? TenantId::fromString($value) : null;
    }

    public function remember(TenantId $tenant): void {} // the next URL carries it

    public function forget(): void {}
}
```

## `ReachableTenantsProvider`

Les locataires dans lesquels le compte courant peut agir.

| Méthode | Contrat |
|---|---|
| `reachable(): ReachableTenants` | L'ensemble lu dans les droits que l'application détient déjà |

Invariants :

- **Échec en mode fermé.** Des droits qui ne peuvent plus être servis (trop périmés, illisibles) donnent
  `ReachableTenants::none()`, jamais le dernier ensemble vu.
- **Aucun appel au fournisseur d'identité sur le chemin de la requête pour un changement.** Lisez les droits en cache :
  c'est ce qui garde le changement instantané et fonctionnel quand le fournisseur est en panne.
- **Aucun doublon.** `ReachableTenants::of()` les refuse ; un doublon signifie que les droits ont été mal lus.

```php
namespace App\Tenancy;

use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

final readonly class EntitlementsReachableTenants implements ReachableTenantsProvider
{
    public function __construct(private EntitlementCache $entitlements) // the application's own cache
    {
    }

    public function reachable(): ReachableTenants
    {
        $nodes = $this->entitlements->forCurrentAccount(); // null once it can no longer be served

        if (null === $nodes) {
            return ReachableTenants::none();
        }

        return ReachableTenants::of(...array_map(
            static fn (int|string $reference): TenantId => TenantId::fromString((string) $reference),
            array_keys($nodes),
        ));
    }
}
```

## `#[TenantScoped]`

- Contrat : `new TenantScoped(string $field = 'tenant')`, sur la classe ; le champ est un champ associé ou une
  association sur une seule colonne dont la colonne contient la référence du locataire.
- Invariants : sur la racine d'une hiérarchie d'héritage ; jamais sur une sous-classe seule.
- Voir [doctrine-filter.md](doctrine-filter.md).

## Doublures de test

Les tests du bundle utilisent deux doublures qui sont un bon point de départ pour les tests d'une application : un
stockage en mémoire qui compte les appels à `forget()`, et un fournisseur qui retourne un ensemble fixe. Voir
[testing.md](testing.md).
