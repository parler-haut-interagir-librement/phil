<!-- phil-dev source-sha256=4f5d0c514d1509abd44695ece3ec36a2a4a63ce2b92759061ef4753e87fc62b0 -->
# Tests

## Les tests du bundle

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/paged-media-bundle/tests
```

`tests/Unit` teste `PagedMedia`, la file d'attente, le *handler*, la purge des tâches des messages en échec, le
stockage des fichiers, le stockage des tâches et le codec, contre un client Gotenberg sur `MockHttpClient`
(`FakeGotenberg`). Les tests utilisent un stockage, un stockage de tâches, un bus de messages et un répartiteur
d'événements qui gardent ce qu'ils reçoivent ; ceux de la file d'attente vérifient qu'un message contient
l'identifiant de la tâche et rien d'autre. `tests/Functional` démarre une petite application — FrameworkBundle, son
client HTTP auquel répond `GotenbergResponses`, Messenger qui traite les messages tout de suite. Il vérifie les
services, les refus de la configuration, les fichiers des messages gardés dans un répertoire, leurs tâches retirées, et
la tâche d'un message en échec oubliée ou, avec `remove_failed: false`, gardée. Infection exige du bundle un score de
mutation couvert de 100 %.

## Tester une application qui l'utilise

Répondez à la place de Gotenberg avec le client HTTP simulé de Symfony :

```yaml
# config/packages/test/framework.yaml
framework:
    http_client:
        mock_response_factory: App\Tests\GotenbergResponses
```

Ou remplacez `PdfStorage` et `JobStore` par vos propres services, et dirigez les messages vers le transport
`sync://`, pour vérifier les fichiers et les tâches gardés. Un gabarit est une simple classe : construisez-le dans un
test unitaire et comparez le PDF rendu à un fichier de référence, puisque le moteur d'écriture natif donne les mêmes
octets pour les mêmes appels.
