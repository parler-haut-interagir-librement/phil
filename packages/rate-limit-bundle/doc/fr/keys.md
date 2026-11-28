<!-- phil-dev source-sha256=bce7415b7f37196927ee320d6b49dea2433cb0ddc2dae225edc074a3140d20a7 -->
# Clés

Une clé, c'est pour qui compte une requête. Un limiteur nomme sa stratégie par défaut avec `key` ; une règle en nomme
une autre avec la sienne.

| Stratégie | La clé |
|---|---|
| `ip` | `ip:` et le HMAC-SHA-256 de l'adresse du client, telle que la lit Symfony derrière ses mandataires de confiance |
| `user` | `user:` et le HMAC-SHA-256 de l'identifiant de l'utilisateur connecté, normalisé ; aucune clé sans utilisateur |
| `user_or_ip` | La clé `user`, sinon la clé `ip` |
| `ip_route` | `ip_route:` et le HMAC-SHA-256 de la clé `ip` et du nom de la route, ou de son chemin sans route |
| un identifiant de service | Ce que retourne `KeyResolver::keyOf()` du service |

Les empreintes ont pour clé `secret`, le secret de l'application par défaut : le stockage ne contient jamais une adresse
ni un courriel, et un même client donne la même clé d'une requête à l'autre. Changer le secret fait repartir chaque
décompte à zéro.

## Identifiants normalisés

Une clé construite à partir de ce qu'une personne tape ou sous quoi on la connaît compte cette personne une seule fois,
quelle que soit la forme du texte, comme la limitation de connexion de Symfony compte un nom d'utilisateur. Avant que la
stratégie `user` calcule l'empreinte d'un identifiant, `IdentifierKey` décompose ses formes de compatibilité (NFKD),
uniformise sa casse, retire les signes combinants, les caractères de format (espaces et liants de largeur nulle, marques
de direction, traits d'union conditionnels) et les caractères de contrôle, puis le rogne :

| Saisi | Compté comme |
|---|---|
| `Ada@Example.org`, `ADA@EXAMPLE.ORG` | `ada@example.org` |
| `Àda`, `A` suivi d'un accent grave combinant | `ada` |
| `ａda` (pleine chasse), `a` + espace de largeur nulle + `da` | `ada` |
| `Straße` | `strasse` |

La décomposition et l'uniformisation de la casse passent par `symfony/string` : les extensions intl et mbstring sont
donc facultatives. Avec elles le travail est plus rapide, sans elles les polyfills de `symfony/string` le font, et
chaque serveur construit la même clé pour chaque caractère que Unicode 13.0 attribue. L'uniformisation complète de la
casse de l'extension intl (`Normalizer::NFKC_CF`) n'est pas employée, parce que son polyfill ne l'a pas, et un sigma
final compte comme un sigma, parce que l'extension mbstring l'écrit et que son polyfill ne l'écrit pas. Un caractère
attribué après Unicode 13.0 est lu comme les données Unicode de chaque implémentation le connaissent : un serveur avec
les extensions et un serveur sans elles peuvent le compter à part.

Un identifiant qui n'est pas de l'UTF-8 valide est rogné et mis en minuscules octet par octet. Une stratégie de
l'application qui construit sa clé à partir d'une telle valeur appelle `IdentifierKey::normalize()` avant de calculer
son empreinte ; une clé construite à partir d'un jeton opaque, dont la casse compte, le garde tel quel.

Une adresse IPv4 écrite comme une adresse IPv6, `::ffff:203.0.113.7`, comme le rapporte un socket à double pile pour un
client IPv4, compte comme `203.0.113.7` : un même client ne détient jamais deux décomptes parce que deux serveurs
écoutent différemment.

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
limiteur : `#[RateLimit('api', key: ApiClientKey::class)]` avec l'attribut de Symfony, ou
`{ limiter: api, key: App\RateLimit\ApiClientKey }` dans une valeur par défaut de route. Un service qu'un limiteur ou un formulaire nomme comme sa
clé rejoint les stratégies même sans configuration automatique. Une clé ne contient aucun secret ni aucun renseignement
personnel en clair : hachez ce qui identifie une personne.
