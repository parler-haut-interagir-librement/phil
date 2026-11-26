<!-- phil-dev source-sha256=1242d42fced0dc9b5414b72a6da2979cc5b2d676a110155d43805050d0d012ff -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : chaque valeur refusée
d'une politique avec son message, les origines exactes, par motif et aux bornes, la réponse à un préambule autorisé ou
refusé, les en-têtes d'une réponse, l'écouteur sur les requêtes principales et les sous-requêtes, et la configuration,
y compris depuis l'environnement. La suite fonctionnelle, sous `tests/Functional`, démarre une petite application —
FrameworkBundle et deux règles — et lui envoie des préambules et des requêtes : un préambule répondu sans route, le
joker d'un contrôleur retiré pour une autre origine, une page d'erreur avec les en-têtes, et un chemin hors des règles
laissé tel quel. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Tester les règles d'une application

Un test fonctionnel d'une application vérifie ses propres règles de la même façon, avec les en-têtes du navigateur :

```php
$client->request('OPTIONS', '/api/books', server: [
    'HTTP_ORIGIN' => 'https://app.example.com',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
]);

self::assertResponseStatusCodeSame(204);
self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://app.example.com');
```
