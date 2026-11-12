<!-- phil-dev source-sha256=f6cd5b235ec30ef268acd07fbd807ea62eb8d606a7d5aaed10199a6d3919f523 -->
# Clés

Une clé, c'est pour qui compte une requête. Un limiteur nomme sa stratégie par défaut avec `key` ; une règle en nomme
une autre avec la sienne.

| Stratégie | La clé |
|---|---|
| `ip` | `ip:` et le HMAC-SHA-256 de l'adresse du client, telle que la lit Symfony derrière ses mandataires de confiance |
| `user` | `user:` et le HMAC-SHA-256 de l'identifiant de l'utilisateur connecté ; aucune clé sans utilisateur |
| `user_or_ip` | La clé `user`, sinon la clé `ip` |
| `ip_route` | `ip_route:` et le HMAC-SHA-256 de la clé `ip` et du nom de la route, ou de son chemin sans route |
| un identifiant de service | Ce que retourne `KeyResolver::keyOf()` du service |

Les empreintes ont pour clé `secret`, le secret de l'application par défaut : le stockage ne contient jamais une adresse
ni un courriel, et un même client donne la même clé d'une requête à l'autre. Changer le secret fait repartir chaque
décompte à zéro.

Les soumissions d'un formulaire comptent sous la clé suivie de `|form:` et du nom du formulaire, à part des autres
requêtes du client. Une clé plus longue que 200 octets est remplacée par `sha256:` et son SHA-256.

## Réseaux

Par défaut, une adresse IPv6 compte par son /64, le réseau que reçoit un abonné, et une adresse IPv4 compte entière : un
client ne peut pas échapper à sa limite en changeant d'hôte dans son réseau.

Avec phil/ip-anonymizer installé et `network.enabled`, l'adresse est tronquée aux préfixes donnés avant le calcul de son
empreinte (/32 en IPv4 et /64 en IPv6 par défaut).

```yaml
phil_rate_limit:
    network: { enabled: true, ipv4_prefix: 32, ipv6_prefix: 64 }
```

## Les clés de l'application

```php
use Phil\RateLimitBundle\Application\Contract\KeyResolver;

final readonly class ApiClientKey implements KeyResolver
{
    public function keyOf(Request $request): ?string
    {
        $client = $request->attributes->get('api_client_id');

        return \is_string($client) ? 'client:'.$client : null;
    }
}
```

Avec la configuration automatique, le service est une stratégie sous son identifiant, que nomme une règle ou un
limiteur : `#[RateLimit('api', key: ApiClientKey::class)]`. Un service qu'un limiteur ou un formulaire nomme comme sa
clé rejoint les stratégies même sans configuration automatique. Une clé ne contient aucun secret ni aucun renseignement
personnel en clair : hachez ce qui identifie une personne.
