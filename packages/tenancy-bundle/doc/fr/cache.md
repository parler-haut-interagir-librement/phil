<!-- phil-dev source-sha256=d3026bcca390f26a3bb508bf113dfbee471291c81657bf369a975ed9007e445a -->
# Cache par locataire

`cache.app`, et chaque pool de cache construit dessus, est limité au locataire actif : chaque locataire lit et écrit
dans son propre sous-espace de noms du pool, et chaque étiquette qu'il enregistre ou invalide est la sienne. La même
clé dans deux locataires contient deux valeurs; invalider une étiquette dans un locataire n'atteint jamais les éléments
d'un autre. Sans locataire actif, le pool est l'espace de noms global. Les pools que tous les locataires partagent,
ceux de Symfony et ceux que l'application énumère, restent tels quels.

La limitation à un locataire est activée par défaut et n'exige aucun code : les services qui reçoivent `cache.app`,
`CacheInterface`, `TagAwareCacheInterface` ou un pool de `framework.cache.pools` reçoivent le pool limité.

```php
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class MenuCache
{
    public function __construct(
        private TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * Computed once per tenant: the key is the same, the namespace is the active tenant's.
     */
    public function menu(callable $compute): array
    {
        return $this->cache->get('menu', static function (ItemInterface $item) use ($compute): array {
            $item->tag('catalogue');

            return $compute();
        });
    }

    /**
     * Invalidates the menu of the active tenant only.
     */
    public function catalogueChanged(): void
    {
        $this->cache->invalidateTags(['catalogue']);
    }
}
```

## Quels pools sont limités

| Pool | Limité | Pourquoi |
|---|---|---|
| `cache.app` | oui | Le cache d'application de Symfony |
| `cache.app.taggable`, `TagAwareCacheInterface` | oui, ses étiquettes | L'adaptateur à étiquettes au-dessus de `cache.app`; ses éléments vivent dans `cache.app` limité |
| Un pool de `framework.cache.pools` sans adaptateur propre | oui | Sa définition hérite de `cache.app` |
| Un pool de `framework.cache.pools` à étiquettes (`tags: true`) | oui, ses éléments et ses étiquettes | Symfony enregistre ses éléments sous `.<pool>.inner`, construit sur `cache.app`, et son adaptateur à étiquettes sous `<pool>` |
| Un pool sur un autre adaptateur (`adapter: cache.adapter.redis`, un DSN, une chaîne) | non | Il n'est pas construit sur `cache.app`; ne l'énumérez nulle part |
| `cache.system` et les pools construits dessus | non | Les métadonnées de validation, de sérialisation et de routage sont les mêmes pour tous les locataires |
| Les pools de Symfony construits sur `cache.app` | non | Le tableau ci-dessous |
| Un pool de `phil_tenancy.cache.global_pools` | non | L'application le partage |

Les pools de Symfony construits sur `cache.app` sont partagés par tous les locataires (`TenantCachePass::SHARED_POOLS`) :

| Pool | Pourquoi il est partagé |
|---|---|
| `cache.messenger.restart_workers_signal` | `messenger:stop-workers` arrête chaque *worker*, quel que soit le dernier locataire qu'il a servi |
| `cache.rate_limiter` | Une limite compte chaque requête d'un client, quel que soit le locataire qu'elle atteint |
| `cache.security`, `cache.security.discovery` | Les preuves, les sessions terminées, les documents de découverte et les clés que chaque serveur doit partager |
| `security.authenticator.cache.expired_links` | Un lien de connexion utilisé une fois est utilisé partout |
| `cache.scheduler` | Les horaires s'exécutent une seule fois pour l'application |
| `cache.http_client.pool` | Les réponses d'autres services, les mêmes pour tous les locataires |
| `cache.translation.providers` | Les traductions de l'application |
| `cache.mailer.webhook` | Les webhooks des fournisseurs de courriel, reçus dans aucun locataire |

Un pool construit sur `cache.app` par un autre bundle est limité : un pool que tous les locataires doivent partager est
énuméré dans `global_pools`. `bin/console debug:container --tag=cache.pool` énumère les pools; un pool limité apparaît
comme `TenantScopedPool` ou `TenantScopedTagAwarePool`.

## Configuration

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    cache:
        enabled: true
        # The pools built on cache.app that every tenant shares, besides Symfony's own.
        global_pools: [app.exchange_rates]
```

| Clé | Valeur par défaut | Effet |
|---|---|---|
| `cache.enabled` | `true` | Limite `cache.app` et les pools construits dessus, quand `symfony/cache` est installé; `false` laisse chaque pool partagé |
| `cache.global_pools` | `[]` | Les pools que tous les locataires partagent, nommés comme `framework.cache.pools` les nomme; `cache.app` lui-même peut y figurer. Un nom qui n'est pas un pool de cache arrête la compilation |

Un pool énuméré dans `global_pools` est partagé avec ses étiquettes : au-dessus d'un `cache.app` partagé,
`cache.app.taggable` l'est aussi.

## Espaces de noms et étiquettes

| Locataire actif | Sous-espace de noms | L'étiquette `catalogue` devient |
|---|---|---|
| aucun | le pool lui-même | `catalogue` |
| `acme` | `tenant.acme` | `tenant.acme.catalogue` |
| `shop-2_b` | `tenant.shop-2_b` | `tenant.shop-2_b.catalogue` |
| `Acme Shop`, `a.b`, `café`, 65 caractères ou plus | `tenant-` et le SHA-256 hexadécimal de la référence | le sous-espace de noms, un point, `catalogue` |

Une référence d'au plus 64 lettres, chiffres, traits d'union et soulignés reste lisible dans le système de stockage;
toute autre est mise en empreinte, de sorte qu'une référence qui contient un caractère qu'une clé de cache ne peut
contenir, ou deux références qui ne diffèrent que par un caractère que le système de stockage ignorerait, ne partagent
jamais un espace de noms. Les deux formes ne se rencontrent jamais : l'une suit un point, l'autre un trait d'union, et
une référence lisible ne contient aucun point.

Les noms qui commencent par `tenant.` ou `tenant-` appartiennent aux locataires : `withSubNamespace()` les refuse, tout
comme une étiquette enregistrée ou invalidée dans ceux-ci, avec
`Symfony\Component\Cache\Exception\InvalidArgumentException`. Rien de ce qui est écrit dans l'espace de noms global ne
peut donc atterrir dans celui d'un locataire.

Les métadonnées d'un élément lu par un pool limité nomment les étiquettes avec lesquelles il a été enregistré, sans le
préfixe.

## Ce que fait chaque opération

| Opération | Dans un locataire | Sans locataire actif |
|---|---|---|
| `get()`, `getItem()`, `save()`, `delete()` | Le sous-espace de noms du locataire | Le pool lui-même |
| `invalidateTags()` | Les étiquettes du locataire | Les étiquettes globales; jamais celles d'un locataire |
| `clear()` | Le sous-espace de noms du locataire | Tout le pool, tous les locataires compris, comme le fait `cache:pool:clear` |
| `prune()` | Tout le pool | Tout le pool |
| `withSubNamespace('x')` | `x` sous le sous-espace de noms du locataire | `x` sous le pool |
| `saveDeferred()` puis `commit()` | Validé dans le locataire où l'élément a été différé | Validé dans l'espace de noms global |

Sur un adaptateur dont le versionnement est activé (`enableVersioning()`), vider change la version de tout le pool : un
`clear()` dans un locataire vide alors tous les locataires. Aucun adaptateur de `framework.cache` ne l'active par
défaut.

## Dans un *worker*

Le locataire actif est lu depuis `TenantContext` à chaque appel, jamais conservé d'un appel au suivant : un *worker* qui
sert `acme`, puis `rose`, écrit les entrées de chaque requête dans son propre locataire, et un message traité dans le
locataire de son `TenantStamp` met en cache dans ce locataire. Entrer dans un locataire ou en sortir valide ce qui a été
enregistré en différé, dans le locataire où il l'a été, et oublie les pools des locataires; la réinitialisation entre
les requêtes (`kernel.reset`) fait de même. Au plus 32 pools de locataires sont conservés à la fois; le plus ancien est
validé et abandonné pour faire de la place.

Au-dessus d'un pool limité, un adaptateur à étiquettes enregistre tout de suite un élément différé : il le validerait
sinon plus tard par le pool limité, dans le locataire qui est actif à ce moment-là. Un pool nativement à étiquettes,
comme un adaptateur à étiquettes de Redis, est limité par le décorateur à étiquettes lui-même et garde ses éléments
différés par locataire.

Une commande lancée sans `--tenant` agit dans l'espace de noms global; `cache:pool:clear cache.app` vide tous les
locataires.

## Tester l'isolation d'une application

```php
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Contracts\Cache\CacheInterface;

$context = static::getContainer()->get(TenantContext::class);
$cache = static::getContainer()->get(CacheInterface::class);

$context->within(TenantId::fromString('acme'), static fn () => $cache->get('menu', static fn (): string => 'acme'));

self::assertSame('rose', $context->within(TenantId::fromString('rose'), static fn () => $cache->get('menu', static fn (): string => 'rose')));
```

Les tests d'isolation du bundle s'exécutent sur deux locataires et l'espace de noms global : la même clé, la même
étiquette, une invalidation, un vidage, un enregistrement différé au travers d'un changement de locataire, un pool
partagé ([testing.md](testing.md)).
