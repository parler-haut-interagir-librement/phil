<!-- phil-dev source-sha256=a42dba7c032f9f7260145d20ee4f049a265320dff776b292f91b0e97bbab5ef5 -->
# Référence de la configuration

L'alias de l'extension est `phil_tenancy`. Chaque clé est facultative. Une clé inconnue arrête le noyau avec
`InvalidConfigurationException` plutôt que d'être ignorée.

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    # Where the chosen tenant is remembered: "session", or the id of an ActiveTenantStore service.
    store: session
    # The id of the ReachableTenantsProvider service. Without it, nothing that resolves a tenant is registered.
    reachable_tenants: App\Tenancy\EntitlementsReachableTenants
    # Registers the phil_tenancy filter, enabled, on the default entity manager.
    doctrine_filter: true
    request:
        enabled: true
        # The route an account that must choose a tenant is sent to; null to carry on in no tenant.
        selector_route: tenant_select
        # Routes resolved in no tenant, such as the login page.
        ignored_routes: [login, login_check]
    # Gives every command a --tenant option.
    console: true
    # The tenant each host serves, entered before the firewall.
    hosts:
        shop.example: general
        rose.example: rose
    # The other names of the hosts, each the canonical host it stands for.
    host_aliases:
        www.rose.example: rose.example
    # Whether a request for an alias is sent to its canonical host.
    redirect_host_aliases: true
    # The shops among the tenants, and the shops each one includes.
    shops:
        general: { includes: [rose] }
    # Sessions scoped to the tenant their accounts signed in on; off by default.
    authentication:
        enabled: false
        user_providers: [security.user.provider.concrete.members]
        account_sharing: null
        shared_sessions: false
        shared_tenants: [[fair, fair-backstage]]
        # Request matchers for the firewalls of security.yaml: phil_tenancy.request_matcher.<name>.
        tenant_firewalls:
            backstage: [fair-backstage]
        # User providers chosen for each tenant: phil_tenancy.user_provider.<name>.
        tenant_user_providers:
            members:
                tenants: { rose: security.user.provider.concrete.rose_members }
                default: security.user.provider.concrete.staff
    # How each tenant's mail leaves.
    mailer:
        settings: null
        platform_sender: 'Shops <mail@platform.example>'
        tenants:
            rose: { transport: rose_smtp, sender: 'Rose <hello@rose.example>' }
            fair: { name: Summer Fair, reply_to: fair.organisers@mail.example }
    # cache.app, and every pool built on it, scoped to the active tenant.
    cache:
        enabled: true
        global_pools: [app.exchange_rates]
    # The row-level security of PostgreSQL, behind the Doctrine filter; off by default.
    row_level_security:
        enabled: false
        connection: default
        entity_manager: default
        setting: phil.tenant_id
        policy: phil_tenant_isolation
```

| Clé | Valeur par défaut | Effet |
|---|---|---|
| `store` | `session` | `session` enregistre `SessionActiveTenantStore`, qui garde le choix dans la session Symfony de l'application ; toute autre valeur est l'identifiant de l'`ActiveTenantStore` de l'application, donné comme alias du port |
| `reachable_tenants` | `null` | L'identifiant du `ReachableTenantsProvider` de l'application, donné comme alias du port. Sans lui, `ActiveTenant`, le changement, l'écouteur de requêtes et les fonctions Twig ne sont pas enregistrés |
| `doctrine_filter` | `true` | Ajoute au début `doctrine.orm.filters.phil_tenancy` avec `enabled: true` quand DoctrineBundle est enregistré ; `false` laisse le filtre à l'application (plusieurs gestionnaires d'entités, un filtre activé plus tard) |
| `request.enabled` | `true` | Enregistre `ResolveActiveTenant` sur `kernel.request`, après le routeur et le pare-feu |
| `request.selector_route` | `null` | L'endroit où `Outcome::SelectionRequired` redirige, avec la page demandée comme `target` sur une requête sûre ; `null` laisse la requête continuer hors de tout locataire |
| `request.ignored_routes` | `[]` | Les routes que l'écouteur ne résout pas : elles s'exécutent hors de tout locataire. La route du sélecteur et `phil_tenancy_switch` sont toujours ajoutées |
| `console` | `true` | Enregistre l'option `--tenant`, quand `symfony/console` est installé |
| `hosts` | `{}` | La référence du locataire de chaque hôte ; enregistre `ResolveHostTenant` avant le pare-feu ([hosts-and-shops.md](hosts-and-shops.md)). Un hôte mal formé arrête la compilation |
| `host_aliases` | `{}` | L'hôte canonique de chaque alias, l'un des `hosts` ; un alias sert le locataire de son hôte. Un alias qui est un hôte, listé deux fois ou d'aucun hôte arrête la compilation |
| `redirect_host_aliases` | `true` | Enregistre `RedirectHostAlias`, qui envoie une requête pour un alias vers son hôte canonique, de façon permanente, avant qu'aucun locataire ne soit entré |
| `shops.<shop>.includes` | `[]` | Les boutiques dont la boutique inclut les catalogues et dont elle encaisse le paiement ; un réseau mal formé arrête la compilation |
| `authentication.enabled` | `false` | Limite les sessions de `user_providers`, quand `symfony/security-http` est installé ([authentication.md](authentication.md)) |
| `authentication.user_providers` | `[]` | Les identifiants de service des fournisseurs d'utilisateurs à décorer |
| `authentication.account_sharing` | `null` | L'identifiant de l'`AccountSharing` de l'application ; `ConfiguredAccountSharing` par défaut |
| `authentication.shared_sessions` | `false` | Si une session ouverte hors de tout locataire, comme sur un back-office central, est connectée sur chaque locataire ; lu par `ConfiguredAccountSharing` |
| `authentication.shared_tenants` | `[]` | Les groupes de locataires dont les comptes sont connectés sur chaque locataire du groupe |
| `authentication.tenant_firewalls` | `{}` | Chaque nom enregistre `phil_tenancy.request_matcher.<name>`, un `TenantRequestMatcher` de ses locataires, pour le `request_matcher` d'un pare-feu. Un pare-feu sans locataire, ou une référence mal formée, arrête la compilation |
| `authentication.tenant_user_providers` | `{}` | Chaque nom enregistre `phil_tenancy.user_provider.<name>`, un `TenantUserProviders` au-dessus du fournisseur de chaque locataire (`tenants`, par référence) et du `default` des autres, pour l'`id` d'un fournisseur de `security.yaml` |
| `mailer.settings` | `null` | L'identifiant du `TenantMailSettings` de l'application, au lieu de `mailer.tenants` ([mail.md](mail.md)) |
| `mailer.platform_sender` | `null` | L'expéditeur des locataires sans domaine propre |
| `mailer.tenants.<tenant>` | `{}` | `transport`, `sender`, `name`, `reply_to` ; n'importe lequel enregistre `TenantTransports`, quand `symfony/mailer` est installé |
| `cache.enabled` | `true` | Limite `cache.app`, et chaque pool construit dessus, au locataire actif, quand `symfony/cache` est installé ([cache.md](cache.md)) |
| `cache.global_pools` | `[]` | Les pools construits sur `cache.app` que tous les locataires partagent, en plus de ceux de Symfony ; un nom qui n'est pas un pool de cache arrête la compilation |
| `row_level_security.enabled` | `false` | Enregistre la variable de session, son intergiciel DBAL, les politiques et leurs commandes ([row-level-security.md](row-level-security.md)) |
| `row_level_security.connection` | `default` | La connexion Doctrine dont les sessions portent le locataire actif |
| `row_level_security.entity_manager` | `default` | Le gestionnaire d'entités dont les entités par locataire reçoivent une politique |
| `row_level_security.setting` | `phil.tenant_id` | La variable de session : deux identifiants en minuscules joints par un point ; un autre nom arrête la compilation |
| `row_level_security.policy` | `phil_tenant_isolation` | La politique de chaque table par locataire : un identifiant en minuscules |

## Ce qui est enregistré

| Service | Quand | Rôle |
|---|---|---|
| `Application\TenantContext` | toujours ; public | Le locataire de la requête, du message ou de la commande courante ; réinitialisé entre les requêtes (`kernel.reset`) |
| `Infrastructure\Doctrine\DoctrineTenantActivation` | toujours ; ne fait rien sans Doctrine | Règle le locataire du contexte sur le filtre de chaque gestionnaire d'entités où il est activé |
| `Infrastructure\Http\CurrentTenantValueResolver` | toujours | L'argument de contrôleur `#[CurrentTenant]` |
| `Infrastructure\Session\SessionActiveTenantStore` | `store: session` | Le choix mémorisé, dans la session |
| `Infrastructure\Messenger\TenantMiddleware` | `symfony/messenger` installé | Le *middleware* qui transmet le locataire à travers les transports ; autoconfiguré, sur chaque bus par son `#[AsMessageMiddleware]` |
| `Infrastructure\Console\TenantOption` | `console: true`, `symfony/console` installé | L'option `--tenant` |
| `Domain\Shop\ShopNetwork` | toujours | Les boutiques et les catalogues que chacune inclut |
| `Application\Shop\ShopContext` | toujours ; public | La boutique de la requête, ses catalogues et l'origine d'une commande |
| `Infrastructure\Http\ConfiguredHostTenants`, `ResolveHostTenant` | `hosts` ou `host_aliases` réglé | Le locataire de chaque hôte et de chaque alias, entré avant le pare-feu ; `HostTenants` et `HostAliases` en alias de `ConfiguredHostTenants` |
| `Infrastructure\Http\RedirectHostAlias` | `host_aliases` réglé, `redirect_host_aliases` | Chaque alias envoyé vers son hôte canonique, avant `ResolveHostTenant` et le pare-feu |
| `Infrastructure\Security\ConfiguredAccountSharing`, `RememberSignInTenant` | `authentication.enabled`, `symfony/security-http` installé | Les comptes partagés ; le locataire de chaque connexion, dans la session |
| `phil_tenancy.scoped_user_provider.<provider>` | `authentication.enabled`, un par fournisseur d'utilisateurs | `TenantScopedUserProvider`, qui décore le fournisseur |
| `phil_tenancy.request_matcher.<name>` | `authentication.enabled`, un par pare-feu de locataire | `TenantRequestMatcher` |
| `phil_tenancy.user_provider.<name>` | `authentication.enabled`, un par entrée de `tenant_user_providers` | `TenantUserProviders` ; le fournisseur de chaque locataire n'est construit que lorsqu'on le demande |
| `Infrastructure\Mailer\ConfiguredTenantMailSettings` | `mailer.tenants` réglé | Les réglages de courrier de chaque locataire |
| `Infrastructure\Mailer\TenantTransports` | `mailer.tenants` ou `mailer.settings` réglé, `symfony/mailer` installé | Décore `mailer.transports` |
| `<pool>.phil_tenancy` | `cache.enabled`, `symfony/cache` installé ; un par pool limité | `Infrastructure\Cache\TenantScopedPool` ou `TenantScopedTagAwarePool`, qui décore `cache.app`, `cache.app.taggable` et chaque pool construit dessus ; étiqueté `phil_tenancy.activation` |
| `Application\ActiveTenant` | `reachable_tenants` réglé ; public | `resolve()`, `canReach()`, `switchTo()`, `leave()` |
| `Infrastructure\Http\SwitchActiveTenant` | `reachable_tenants` réglé | Le contrôleur de la route `phil_tenancy_switch` |
| `Infrastructure\Http\ResolveActiveTenant` | `reachable_tenants` réglé, `request.enabled` | L'écouteur de requêtes |
| `Infrastructure\Twig\TenancyExtension` | `reachable_tenants` réglé, `twig/twig` installé | `active_tenant()`, `reachable_tenants()` |
| `Infrastructure\Doctrine\RowLevelSecurity\TenantSession` | `row_level_security.enabled` | L'activation qui pose la variable de session sur chaque connexion ouverte |
| `Infrastructure\Doctrine\RowLevelSecurity\TenantSessionMiddleware` | `row_level_security.enabled` | L'intergiciel DBAL de la connexion, étiqueté `doctrine.middleware` |
| `Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies` | `row_level_security.enabled` | Les tables par locataire et leurs instructions |
| `Infrastructure\Console\RowLevelSecurityPoliciesCommand`, `RowLevelSecurityCheckCommand` | `row_level_security.enabled`, `symfony/console` installé | `phil:tenancy:rls:policies`, `phil:tenancy:rls:check` |

Chaque service qui implémente `Application\Contract\TenantActivation` reçoit l'étiquette `phil_tenancy.activation` par
configuration automatique et est appelé par le contexte.

## Ce que l'application écrit encore

| Quoi | Pourquoi | Où c'est documenté |
|---|---|---|
| Les locataires atteignables | Seule l'application sait d'où viennent ses droits | [extension-points.md](extension-points.md) |
| La page de sélection | Son rendu, et si l'entrée dans un locataire exige une nouvelle authentification | [resolution.md](resolution.md) |
| L'importation de la route du changement | Son chemin et son préfixe appartiennent à l'application | [integrations.md](integrations.md) |
| Le *middleware* sur ses bus | Seule l'application connaît ses bus | [integrations.md](integrations.md) |

## Constantes

| Constante | Valeur | Usage |
|---|---|---|
| `ActiveTenantFilter::NAME` | `phil_tenancy` | Le nom sous lequel le filtre est enregistré, activé et désactivé |
| `ActiveTenantFilter::PARAMETER` | `tenant` | Le paramètre du filtre que règle `activate()` ; ne le réglez que par `activate()` |
| `TenantId::MAX_LENGTH` | `255` | La plus longue référence de locataire, en octets |
| `SwitchActiveTenant::ROUTE` | `phil_tenancy_switch` | Le nom de la route du changement |
| `SessionActiveTenantStore::KEY` | `_phil_tenancy.active_tenant` | L'attribut de session qui contient le choix |
| `RowLevelSecurityConfig::DEFAULT_SETTING` | `phil.tenant_id` | La variable de session par défaut |
| `RowLevelSecurityConfig::DEFAULT_POLICY` | `phil_tenant_isolation` | Le nom de politique par défaut |
| `TenantOption::NAME` | `tenant` | L'option de la console |
| `PhilTenancyBundle::ACTIVATION_TAG` | `phil_tenancy.activation` | L'étiquette des activations |
| `TenantCacheNamespace::PREFIX` | `tenant` | Le début du sous-espace de noms et du préfixe d'étiquette de chaque locataire : `tenant.` ou `tenant-` |
| `TenantCacheNamespace::READABLE_LENGTH` | `64` | La plus longue référence gardée lisible dans un sous-espace de noms |
| `TenantPools::MAX_TENANTS` | `32` | Le plus grand nombre de pools de locataires qu'un pool limité garde à la fois |
| `TenantCachePass::SHARED_POOLS` | Les pools de Symfony construits sur `cache.app` | Les pools que tous les locataires partagent sans être énumérés ([cache.md](cache.md)) |
| `TenantCachePass::DECORATOR_SUFFIX` | `.phil_tenancy` | Le suffixe du décorateur de chaque pool limité |
| `RememberSignInTenant::SESSION_KEY` | `_phil_tenancy.signed_in_on` | L'attribut de session qui contient le locataire où un compte s'est connecté |
