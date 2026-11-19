<!-- phil-dev source-sha256=e8fafafa794ce07877cca6edb3dc2fb70bbe5412c0a2ca1d9f612095e992dc99 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les types de valeurs et les identifiants de connexion ; la ligne ; l'en-tête de format et
d'algorithme, le chiffrement KeyManagement par-dessus le KMS local du composant et l'enveloppe — le scellement,
l'ouverture selon l'en-tête, les valeurs de l'ancien format, un en-tête changé, les valeurs altérées et mal formées, les
valeurs d'une autre clé maîtresse ou d'une autre ligne, un KMS qui échoue — ; `UserSecrets` sur un stockage qui garde
des copies, comme le fait une base de données — stocker, lister, lire par la tâche du propriétaire, actualiser,
révoquer, supprimer, effacer, les événements — ; la révocation auprès du service — après le changement local, un
service qui échoue, une valeur qui ne s'ouvre pas — ; les courses d'une actualisation entre deux processus — avec et
sans le verrou, une ligne scellée de nouveau, révoquée ou supprimée entre-temps, une ligne qui ne cesse de changer — ;
le verrou d'actualisation sur le composant Lock de Symfony ; la rotation des clés — vers une nouvelle clé maîtresse, hors
de l'ancien format, une valeur qui ne s'ouvre pas nommée pendant que les autres changent de clé, une ligne stockée,
actualisée ou révoquée pendant un lot et laissée telle que l'autre processus l'a faite, le compte de ce qui reste, entre
clés maîtresses d'un KMS — ; un même contrat pour les deux
stockages, compare-and-swap compris, exécuté en mémoire et sur Doctrine avec SQLite en mémoire ; le module de vie
privée, l'auditeur journaliseur et les commandes ; la clé maîtresse générée, lue par un DSN `sodium://` ; et la
configuration, le câblage, la vérification du KMS et de la fabrique de verrous. La suite `tests/Functional` démarre une
petite application avec FrameworkBundle et sa fabrique de verrous, DoctrineBundle sur SQLite, le bundle de vie privée et
KeyManagementBundle avec un client `sodium://` local, sa clé maîtresse lue dans une variable d'environnement, et suit
les secrets d'une personne du stockage jusqu'à l'effacement, un jeton actualisé sous le verrou et révoqué auprès de son
service.

La suite `tests/Integration` exécute le contrat des stockages sur MySQL 8 et PostgreSQL 17 — l'ordre de la rotation des
clés, le compare-and-swap sur une colonne `TEXT` —, et est sautée sans leurs URL.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/user-secrets-bundle/tests
vendor/bin/phil-dev services up && vendor/bin/phil-dev services test -- packages/user-secrets-bundle/tests/Integration
```

Infection impose au bundle un score de mutation couverte de 100 %.

## Tester une application qui l'utilise

Gardez les secrets en mémoire et donnez à l'environnement de test un client local et sa propre clé maîtresse :

```yaml
# config/packages/test/key_management.yaml
key_management:
    clients:
        test: 'sodium://?keys[test]=dGVzdC1rZXktdGVzdC1rZXktdGVzdC1rZXktdGVzdCE'

# config/packages/test/phil_user_secrets.yaml
phil_user_secrets:
    store: memory
    current_key: test
```

```php
$secrets = self::getContainer()->get(UserSecrets::class);
$secrets->store(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), ApiKey::of('sk-test'));
```

Remplacez un `TokenRefresher` par un service qui renvoie un jeton fixe, un `TokenRevoker` par un qui garde les jetons
qu'on lui remet, et enregistrez un `SecretAuditor` qui garde les
événements pour vérifier ce qui a été lu et pourquoi.
