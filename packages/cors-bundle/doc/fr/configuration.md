<!-- phil-dev source-sha256=2cd1547243b4924b0a9482584ab49a29b3d4a877654fb74d79d3161f974b4eb2 -->
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
| `allow_private_network` | `false` | Si une page publique peut joindre ce serveur sur un réseau privé, pour les navigateurs qui le demandent avec `Access-Control-Request-Private-Network`. |

Chaque chemin a besoin d'au moins une origine, dans `allow_origins` ou `allow_origin_patterns`, et d'une méthode.

### Origines

Une origine est ce que le navigateur envoie dans l'en-tête `Origin` : un schéma, un hôte et un port quand ce n'est pas
celui par défaut du schéma, comme `https://app.example.com` ou `http://localhost:5173`. Le bundle la compare octet par
octet aux origines exactes, qu'il écrit en minuscules quand la configuration a des majuscules. D'autres schémas sont
aussi des origines, comme `capacitor://localhost` pour une application mobile.

Un motif correspond à l'origine entière : il commence par `^` et se termine par `$`, pour que
`https://app.example.com.evil.test` ou `https://evilexample.com` ne passent jamais pour une origine autorisée. Échappez
les points : `^https://[a-z0-9-]+\.example\.com$`.

### En-têtes

`allow_headers` énumère les en-têtes qu'une requête peut envoyer en plus de ceux que tout navigateur peut envoyer sans
demander (`Accept`, `Accept-Language`, `Content-Language`, et `Content-Type` avec une valeur de formulaire ou de texte).
Les noms sont comparés sans tenir compte de la casse. Avec `*`, la réponse à un préambule nomme les en-têtes que le
navigateur a demandés, `Authorization` compris, plutôt que d'envoyer `*`, auquel `Authorization` ne correspond pas.

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
