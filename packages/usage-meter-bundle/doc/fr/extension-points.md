<!-- phil-dev source-sha256=9a06a78cf2141077dfa34f639ec6aa5e19ce0ed66b79fa39bb6d9f48924b52ae -->
# Points d'extension

Le bundle branche la bibliothèque ; ce qui est propre à l'application reste dans ses propres services, nommés dans la
configuration.

## Un stockage

N'importe quel service qui implémente `Phil\UsageMeter\UsageStore`, nommé dans `store`, remplace celui du bundle : un
stockage sur Redis, sur une autre API de base de données. Il tient les promesses de l'interface (une incrémentation
atomique sous un plafond, une clé comptée une fois, une incrémentation refusée qui ne laisse rien derrière elle), et
les tests de contrat de la bibliothèque les vérifient.

```yaml
phil_usage_meter:
    store: App\Usage\RedisUsageStore
```

## Fournisseurs de limites

Un `Phil\UsageMeter\LimitProvider` dit combien un sujet peut utiliser d'un compteur, ou `null` pour laisser le suivant
le dire. Les fournisseurs de `limit_providers` sont interrogés dans l'ordre, les fonctionnalités d'abonnement après
eux, la limite propre au compteur en dernier :

```php
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\LimitProvider;
use Phil\UsageMeter\Meter;

final readonly class AdminOverrides implements LimitProvider
{
    public function __construct(private OverrideRepository $overrides)
    {
    }

    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        $value = $this->overrides->find($subject, $meter->name);

        return null === $value ? null : Limit::parse($value);
    }
}
```

```yaml
phil_usage_meter:
    limit_providers: [App\Usage\AdminOverrides]
    subscription_features: true
```

## Un fournisseur d'ancres

Un `Phil\UsageMeter\AnchorProvider` donne à un sujet sa propre ancre (le jour où il s'est abonné) pour les compteurs
comptés par cycle de facturation ; l'ancre `billing` du compteur sert aux sujets pour lesquels il retourne `null`.

```yaml
phil_usage_meter:
    anchor_provider: App\Usage\SubscriptionAnchors
```

## Clés des limiteurs de débit

Pour qui compte une requête est une stratégie de clé de `phil/rate-limit-bundle`, nommée dans la `key` d'un limiteur :
`ip`, `user`, `user_or_ip` et `ip_route` de ce bundle, ou un service qui implémente son `KeyResolver` (un compte,
l'identifiant d'une clé d'API). La clé est le sujet du compteur, de 1 à 200 octets : un identifiant, jamais des
renseignements personnels en clair. Voir la documentation des clés de ce bundle.

## Le compteur d'utilisation lui-même

`Phil\UsageMeter\UsageMeter` est un service public, injecté automatiquement par sa classe : les contrôleurs, les
*handlers* de messages et les commandes appellent `consume()` avant une action, `record()` après, `usage()` pour
montrer ce qui reste.
