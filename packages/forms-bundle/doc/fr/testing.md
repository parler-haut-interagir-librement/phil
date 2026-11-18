<!-- phil-dev source-sha256=9bdc8a61e950ebd3919e4122aa6de16ff8be0583546f96801cb05c0c89655eb5 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Field`, `FieldType` | Chaque règle ; ce que chaque type accepte et refuse, une fois les espaces retirées ; les dates de naissance bornées par le jour ; les âges comptés en années complètes, les deux bornes incluses, le jour de l'entrée ou une date de référence, l'anniversaire, la veille, le 29 février en année commune et bissextile ; les paliers, les choix disqualifiants, les capacités, la sensibilité, et une définition relue depuis le JSON |
| `BasicConditions`, `Notification` | Les trois formes, un champ laissé vide qui ne satisfait que `!=`, chaque condition mal formée, les types vérifiés ; les destinataires, les sujets sur une ligne, les paramètres substituables |
| `Form`, `Entry`, `HmacEmailDigester` | Les clés, les titres, les champs construits une fois tant que leurs définitions tiennent, les destinataires, les webhooks, la conservation, l'ouverture et la fermeture ; toutes les erreurs d'un coup ; les tables et les colonnes ; le sujet, le champ de courriel désigné et l'empreinte de son adresse ; les conditions en cascade, les paliers, les autres notifications, les valeurs scellées |
| `Submissions`, `Retention`, `DoctrineFormRepository` | Les entrées conservées puis notifiées, un notificateur en échec journalisé ; la liste ; la suppression à la fin de la conservation et avec le formulaire ; les places prises sous la capacité, rendues après un refus, un enregistrement échoué et un retrait |
| `EmailNotifier`, `WebhookNotifier` | L'expéditeur, les destinataires, `Reply-To` ; les notifications sous conditions, chaque courriel tenté ; les valeurs sensibles retenues ; le corps signé vérifié, les réponses, les refus ; un hôte de webhook qui se résout en adresse privée, IPv4 ou cachée en IPv6, refusé |
| `KeyManagementFieldSealer`, `SealedValues`, `UnsealedSensitiveField` | Le scellement par le KMS libsodium local du composant KeyManagement de Symfony, une entrée scellée, conservée puis rouverte, le contexte lié, une autre clé maîtresse, la mauvaise clé sous le même nom, une clé maîtresse absente qui ne conserve aucune entrée, un nom de clé qui n'est pas celui de l'enveloppe, un KMS qui échoue, chaque valeur mal formée ; un formulaire qui a un champ sensible refusé sans scelleur, à la construction et à la soumission, sauf si l'application le permet |
| `EntryType`, `FormController`, `LogicSteps` | Les champs et les options tirés de la définition, la protection, les paliers, les choix complets désactivés, la date de naissance ; la page, la redirection, les erreurs sur leurs champs, l'âge d'une date de naissance refusée, le message de disqualification, les `404` ; avec les conditions du navigateur, les rangées conditionnelles nommées et les étapes, un champ lu par une autre condition exigé affiché, un champ d'un autre palier lu comme `false`, rien pour un formulaire sans conditions, ni sans `phil/form-logic` |
| `PurgeEntriesCommand`, `PhilFormsBundle` | Les lots ; les services, les valeurs par défaut, la protection contre le pourriel exigée, le mappage, l'utilisateur connecté comme sujet, le scelleur câblé depuis `sealing`, nul ou refusé quand il est vide, refusé sans le composant ou son client, `allow_unsealed_sensitive` |
| La recette Flex | Le bundle enregistré, sa configuration lue comme les valeurs par défaut du bundle, les routes sous `/forms` |
| `FormEntriesPrivacy`, `TokenCurrentSubject` | L'exportation et l'effacement par identifiant et par adresse, un effacement exécuté deux fois, les valeurs scellées ouvertes ou listées comme illisibles ; l'utilisateur connecté |
| `BinaryKeysListener` | Les clés en `utf8mb4_bin` dans le schéma généré pour MySQL et MariaDB, la collation par défaut sur SQLite, les options qu'un champ a déjà conservées |

La suite `tests/Integration` exécute `BinaryKeysListener` sur un vrai MySQL 8.4 et un vrai PostgreSQL 17 : quatre clés de
formulaire qui ne diffèrent que par la casse ou l'accent sont quatre formulaires, chacun trouvé par sa seule clé, et sur
MySQL les clés sont en `utf8mb4_bin` et les tables de la version précédente y arrivent par le diff du schéma. Le test
de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou `PHIL_TEST_POSTGRESQL_URL` et est sauté sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/forms-bundle/tests/Integration
```

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et la protection
contre le pourriel, le bundle étant configuré en YAML comme le fait une application : une page est produite en français,
une entrée est soumise comme le ferait un navigateur, conservée, envoyée par courriel et publiée ; la page d'un
formulaire à conditions porte le contrôleur de `phil/form-logic` et ses étapes, et sans les conditions du navigateur
elle est produite comme avant. Dans le monodépôt, `vendor/bin/phil-dev lint-apps` exécute `lint:container`,
`lint:expressions` et `lint:yaml` sur ce noyau.

`JsonLogicConditions`, l'adaptateur de `phil/form-logic`, est testé dans cette bibliothèque, sur les champs de ce
bundle, qui vérifie aussi que les champs que masquent les étapes de `LogicSteps` sont ceux que le bundle ne demande pas.

```sh
vendor/bin/phpunit packages/forms-bundle/tests
```

## La suite du navigateur

`tests/Browser/FormConditionsBrowserTest.php` pilote la page d'un formulaire dans Chrome avec Panther, dans une
application où `phil/form-logic` est installé comme cette bibliothèque le décrit : AssetMapper associe son
`assets/src` à `@phil/form-logic`, StimulusBundle active son contrôleur dans `assets/controllers.json`, et la page de
l'application charge l'import map et inclut `_form.html.twig`. Le formulaire vit dans SQLite. Un champ demandé à
l'équipe Bleue est masqué, puis affiché quand Bleue est choisie ; le champ qui en dépend suit en cascade, leurs
contrôles désactivés tant qu'ils sont masqués ; un champ demandé sauf si l'équipe est Verte est affiché tant que rien
n'est choisi.

Elle a besoin du chromedriver du système et de Stimulus, installé dans `tests/Browser` par `npm ci --ignore-scripts` ;
elle est sautée sans eux. Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit --configuration=phpunit.browser.dist.xml packages/forms-bundle/tests/Browser
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À la racine du
monodépôt :

```sh
composer bench -- packages/forms-bundle/tests/Benchmark
```
