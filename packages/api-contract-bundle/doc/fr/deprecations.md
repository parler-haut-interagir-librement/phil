<!-- phil-dev source-sha256=80516a6099bfa609ceae707ad5e356bb6b2f874174205dcca0ce2c25d514100a -->
# Obsolescences

## Côté serveur

Un contrôleur marqué `#[DeprecatedEndpoint]` annonce son obsolescence sur chaque réponse, comme le prévoient la RFC 9745
et la RFC 8594 :

```php
use Phil\ApiContractBundle\Infrastructure\Http\DeprecatedEndpoint;

#[DeprecatedEndpoint(deprecatedAt: '2026-11-01', sunsetAt: '2027-05-01', link: 'https://example.com/changes')]
```

```http
Deprecation: @1793491200
Sunset: Sat, 01 May 2027 00:00:00 GMT
Link: <https://example.com/changes>; rel="deprecation"
```

Les dates sont en ISO 8601, en UTC sauf indication contraire ; l'une ou l'autre peut être omise, mais pas les deux.
L'attribut construit son signal à sa lecture, et le bundle lit tous ceux des services de contrôleur (étiquetés
`controller.service_arguments`, comme le fait la configuration automatique) à la compilation du conteneur. Une date
illisible, un retrait antérieur à l'obsolescence ou un lien contenant une espace fait échouer la construction avec une
`LogicException` qui nomme le contrôleur, plutôt que chaque requête au point de terminaison. Un contrôleur qui n'est pas
un tel service est vérifié à sa première requête. `Deprecation` et `Sunset` remplacent ceux que le contrôleur a définis.
Le `Link` s'ajoute à côté des propres en-têtes `Link` de la réponse, comme la pagination ou le préchargement, jamais à
leur place.

## Côté client

`DeprecationObservingHttpClient` décore le service `http_client`. Lorsqu'une réponse porte `Deprecation` ou `Sunset`, le
signal est consigné pour son point de terminaison dans `ObservedDeprecations`, et un avertissement est journalisé une
fois par jour et par point de terminaison. Le point de terminaison est l'option `extra.endpoint` de la requête, sinon sa
méthode et son chemin, où chaque segment qui ne se lit pas comme un nom est remplacé par `{id}`. Un nom est une lettre,
suivie de lettres, de chiffres, de `-` et de `_`, avec au plus deux chiffres et 32 caractères : les nombres, les UUID,
les empreintes, les adresses de courriel, les valeurs encodées et les noms de fichiers sont remplacés.

Le chemin est celui que lit un navigateur, par l'analyseur d'URL WHATWG de PHP : une barre oblique inverse est une barre
oblique, les segments `.` et `..` sont résolus, qu'ils soient écrits tels quels ou échappés, une lettre hors de l'ASCII
est encodée en pourcentage, et le nom d'utilisateur et le mot de passe d'une URL ne l'atteignent jamais. N'importe quel
hôte est lu, une adresse IP ou `localhost` compris ; une URL relative, que le client résout par rapport à son
`base_uri`, est lue à partir de la racine.

```php
$client->request('GET', 'https://partner.example/orders/42', ['extra' => ['endpoint' => 'partner.order']]);
// without the option: "GET /orders/{id}"
```

Une valeur qui s'écrit comme un mot — un nom d'utilisateur, un *slug* — ne peut pas être distinguée d'un nom :
`GET /users/jdoe` est consigné et journalisé tel quel. Donnez à de tels appels un `extra.endpoint`, pour qu'aucun
identifiant n'atteigne le registre ni le journal.

`CacheObservedDeprecations` conserve le registre dans `cache.app`, un élément par point de terminaison et un index de
leurs noms. L'élément d'un point de terminaison est écrit lorsque son avertissement quotidien est dû ou que son signal a
changé, pas à chaque appel, et conservé huit jours à partir de cette écriture. Un point de terminaison que l'application
n'appelle plus, ou dont l'API a retiré son signal, quitte le registre une semaine après sa dernière observation. La
commande l'énumère :

```bash
bin/console phil:api-contract:deprecations
# GET /orders/{id}: deprecated since 2026-11-01, goes away on 2027-05-01 — https://example.com/changes
```

Elle échoue tant qu'un point de terminaison a une date de retrait : exécutée en intégration continue, elle prévient
l'équipe avant que le point de terminaison ne disparaisse.
