<!-- phil-dev source-sha256=37004f88b4251ed4ae5485137ba5796de86d5074d49be0075c3398b8b0e5fc1c -->
# Configuration

La configuration du bundle se trouve sous `phil_cors` :

```yaml
# config/packages/phil_cors.yaml
phil_cors:
    enabled: true
    paths:
        '^/api/admin/':
            allow_origins: ['https://admin.example.com']
            allow_methods: [GET, POST, PUT, PATCH, DELETE]
            allow_headers: [Content-Type, Authorization, X-Requested-With]
            expose_headers: [ETag, Location]
            allow_credentials: true
            max_age: 3600
        '^/api/':
            allow_origins: ['https://app.example.com', 'http://localhost:5173']
            allow_origin_patterns: ['^https://[a-z0-9-]+\.preview\.example\.com$']
            allow_methods: [GET, POST]
            allow_headers: [Content-Type]
        '^/feed\.json$':
            allow_origins: '*'
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `enabled` | `true` | Enregistre les services du bundle ; `false` n'en enregistre aucun. |
| `paths` | `{}` | Des expressions régulières sur le chemin de la requête, chacune avec sa politique. |

## Chemins

Chaque clé de `paths` est une expression régulière sur le chemin de la requête, son *path info* sans la chaîne de
requête, écrite sans délimiteurs, comme `^/api/`. Le bundle les lit dans l'ordre de la configuration et applique la
politique de la première qui correspond : placez les chemins les plus étroits en premier. Un chemin qu'aucune clé ne
reconnaît est laissé tel quel : aucun en-tête n'est ajouté, et un préambule continue vers le routeur.

Chaque chemin accepte ces options :

| Option | Valeur par défaut | Effet |
|---|---|---|
| `allow_origins` | `[]` | Des origines exactes, comme `https://app.example.com`, ou `*` seul pour toute origine. Une chaîne est lue comme une liste d'un élément. |
| `allow_origin_patterns` | `[]` | Des expressions régulières sur l'origine entière, ancrées par `^` et `$`, écrites sans délimiteurs. |
| `allow_methods` | `[GET, HEAD, POST]` | Les méthodes qu'une requête entre origines peut employer. |
| `allow_headers` | `[]` | Les en-têtes de requête qu'elle peut envoyer, ou `*` seul pour n'importe lesquels. |
| `expose_headers` | `[]` | Les en-têtes de réponse que sa page peut lire, en plus de ceux de la liste sûre, ou `*` seul pour n'importe lesquels. |
| `allow_credentials` | `false` | Si les témoins, les certificats client TLS et l'authentification HTTP accompagnent la requête. |
| `max_age` | `null` | Les secondes pendant lesquelles un navigateur garde la réponse à un préambule, de 0 à 86400 ; `null` n'envoie aucun `Access-Control-Max-Age`, et le navigateur la garde 5 secondes. |
| `allow_private_network` | `false` | Si une page publique peut joindre ce serveur sur un réseau privé, pour les navigateurs qui le demandent encore avec `Access-Control-Request-Private-Network`. Voir [Le réseau privé](#le-réseau-privé). |

Chaque chemin a besoin d'au moins une origine, dans `allow_origins` ou `allow_origin_patterns`, et d'une méthode.

### Origines

Une origine est ce que le navigateur envoie dans l'en-tête `Origin` : un schéma, un hôte et un port quand ce n'est pas
celui par défaut du schéma, comme `https://app.example.com` ou `http://localhost:5173`. Le bundle la compare octet par
octet aux origines exactes, qu'il écrit en minuscules quand la configuration a des majuscules. D'autres schémas sont
aussi des origines, comme `capacitor://localhost` pour une application mobile.

Un motif correspond à l'origine entière : il commence par `^` et se termine par `$`, pour que
`https://app.example.com.evil.test` ou `https://evilexample.com` ne passent jamais pour une origine autorisée. Échappez
les points : `^https://[a-z0-9-]+\.example\.com$`.

Écrivez un sous-domaine `[a-z0-9-]+`, jamais `.*` ni `.+` : une étiquette d'hôte contient des lettres, des chiffres et
des traits d'union, jamais un point, une barre oblique ni un deux-points. `^https://.*example\.com$` autorise
`https://evil-example.com`, un hôte que n'importe qui peut enregistrer, puisque rien n'exige un point avant
`example.com` ; `^https://.+\.example\.com$` autorise tous les niveaux de sous-domaine. Avec `[a-z0-9-]+\.`, le
sous-domaine est une seule étiquette, et le point qui la termine est la frontière. N'ajoutez un port que si l'origine en
a un, comme `(:[0-9]+)?` pour un serveur de développement.

### En-têtes

`allow_headers` énumère les en-têtes qu'une requête peut envoyer en plus de ceux que tout navigateur peut envoyer sans
demander (`Accept`, `Accept-Language`, `Content-Language`, et `Content-Type` avec une valeur de formulaire ou de texte).
Les noms sont comparés sans tenir compte de la casse. Avec `*`, la réponse à un préambule nomme les en-têtes que le
navigateur a demandés, `Authorization` compris, plutôt que d'envoyer `*`, auquel `Authorization` ne correspond pas.

### Le réseau privé

`allow_private_network` répond au préambule de Private Network Access : une page d'une origine publique qui appelle un
serveur sur une adresse privée ou sur la boucle locale envoie `Access-Control-Request-Private-Network: true`, et le
bundle répond `Access-Control-Allow-Private-Network: true` quand l'origine, la méthode et les en-têtes sont autorisés et
que le chemin l'autorise.

Chrome a remplacé ces en-têtes par Local Network Access, qui demande plutôt à l'utilisateur : une page publique qui
joint le réseau local ou la boucle locale a besoin de la permission de l'utilisateur, `local-network` ou
`loopback-network`, et aucun en-tête du serveur ne l'accorde. Chrome n'envoie plus `Access-Control-Request-Private-Network`,
si bien que l'option ne change rien pour lui ; elle garde son effet pour les navigateurs qui envoient encore l'en-tête.
La page demande cette permission comme le décrit
[Local Network Access de Chrome](https://developer.chrome.com/blog/local-network-access).

Laissez l'option désactivée, sauf si une page publique doit appeler ce serveur sur un réseau privé : la permission de
l'utilisateur et les origines du chemin s'appliquent toujours.

### Variables d'environnement

Chaque valeur d'une liste peut venir d'une variable d'environnement, une origine ou un motif par variable. Un motif
permet à une seule variable d'autoriser plusieurs origines :

```yaml
phil_cors:
    paths:
        '^/api/':
            allow_origins: ['%env(FRONT_ORIGIN)%']
            allow_origin_patterns: ['%env(CORS_ALLOW_ORIGIN)%']
```

```sh
# .env
FRONT_ORIGIN=https://app.example.com
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

Une liste entière tirée d'une seule variable, comme `%env(csv:CORS_ALLOW_ORIGIN)%`, n'est pas prise en charge : nommez
une variable par valeur.

Un chemin dont les options lisent l'environnement est vérifié quand l'application construit pour la première fois les
services CORS, à sa première requête, plutôt qu'à la compilation du conteneur : les variables ne sont connues qu'à ce
moment. Les vérifications et leurs messages sont les mêmes, levés sous la forme de
`Phil\CorsBundle\Domain\Exception\InvalidCorsPolicy`.

## Ce qui arrête la construction

Sans variables d'environnement, la configuration est vérifiée à la compilation du conteneur : une erreur arrête
`cache:clear` ou `cache:warmup` avec une `InvalidConfigurationException` qui nomme le chemin et le problème, et
n'atteint jamais une requête.

| Refusé | Pourquoi |
|---|---|
| `*` dans `allow_origins`, `allow_headers` ou `expose_headers` avec `allow_credentials: true` | Les navigateurs refusent un joker avec des identifiants de connexion ; énumérez les valeurs. |
| `*` à côté d'autres valeurs | `*` reste seul. |
| `*` dans `allow_methods` | Énumérez les méthodes par leur nom. |
| L'origine `null` | Les cadres isolés, les fichiers locaux et certaines redirections l'envoient : n'importe quelle page peut la revendiquer. |
| Une origine avec un chemin, une barre oblique finale ou sans schéma | Un en-tête `Origin` n'en a jamais. |
| Une origine avec le port par défaut de son schéma, comme `https://a.test:443`, ou un port supérieur à 65535 | Les navigateurs n'en envoient jamais. |
| Un motif d'origine sans `^` au début ou sans `$` à la fin | Un autre hôte pourrait commencer ou se terminer comme un hôte autorisé. |
| Un chemin ou un motif d'origine qui ne compile pas | Il ne correspondrait à rien, ou échouerait à chaque requête. |
| Un nom de méthode ou d'en-tête qui n'est pas un jeton HTTP | Il ne peut pas être envoyé dans un en-tête. |
| Un chemin sans origine, ou sans méthode | Il n'autoriserait rien. |
| Un `max_age` inférieur à 0 ou supérieur à 86400 | Les navigateurs le plafonnent, Chromium à 7200 secondes, Firefox à 86400. |
