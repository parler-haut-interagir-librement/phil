<!-- phil-dev source-sha256=3b6b74678f174b85fc730c0bf87c88b436f41a675a8f44069d297b707f2633d9 -->
# Tests

## Comment le bundle est testé

Des tests unitaires PHPUnit 13 sous `tests/Unit/`, en miroir de `src/` :

| Test | Couvre |
|---|---|
| `tests/Unit/PhilBotDetectionBundleTest.php` | L'alias d'extension est `phil_bot_detection` ; charger l'extension n'ajoute aucun service |
| `tests/Unit/Domain/Verdict/VerdictTest.php` | Les deux valeurs transmises ; toute autre entrée (absente, vide, autre cas, espaces, charge utile plus longue, `true`, `unknown`) vaut `Unknown` et ne lève jamais d'exception ; `isRobot()` |
| `tests/Unit/Domain/Friction/FrictionTest.php` | Exactement deux cas et aucun refus ; `adds()` |
| `tests/Unit/Domain/Friction/FrictionPolicyTest.php` | `Human` n'ajoute rien et `Robot` ajoute une étape sous les deux politiques ; `Unknown` sous chaque politique |
| `tests/Unit/Application/VerdictHandlerTest.php` | Un verdict robot est compté une fois et ajoute une étape ; un verdict humain n'est pas compté ; **un verdict inconnu n'est jamais compté, même quand il ajoute de la friction** |
| `tests/Unit/Application/Fixture/InMemoryRobotVerdictCounter.php` | Le compteur en mémoire qu'utilisent les tests du gestionnaire |

Les tests unitaires sont purs : aucun conteneur hors du test de l'extension, aucun réseau, aucun navigateur.

`tests/js/detectors.test.mjs` exécute le côté navigateur avec l'exécuteur de tests de Node : chaque détecteur face à des
environnements qui montrent ou non son signal, leur combinaison en un verdict, et le contrôleur `verdict` qui l'écrit
sur l'élément racine et dans son champ. Stimulus est remplacé par un *stub* (`tests/js/stimulus.stub.mjs`) grâce à
`tests/js/register.mjs` :

```sh
node --import ./packages/bot-detection-bundle/tests/js/register.mjs --test packages/bot-detection-bundle/tests/js
```

Une suite fonctionnelle sous `tests/Functional/` démarre un vrai noyau, `tests/Functional/App/TestKernel.php`
(FrameworkBundle et le bundle sous un bloc `phil_bot_detection` vide), avec son cache dans un répertoire à lui sous le
répertoire temporaire du système. `BundleRegistrationTest` fixe l'alias, la référence vide qu'affiche
`config:dump-reference phil_bot_detection`, et l'échec de construction que cause une clé inconnue. `VerdictHandlingTest`
branche deux surfaces comme le montre [configuration.md](configuration.md), un formulaire d'inscription sous
`robotOrUnknown()` et un paiement sous `robotOnly()`, chacun avec son propre gestionnaire et son compteur en mémoire, et
vérifie qu'un verdict ne fait bouger que le compteur de sa propre surface. Elle n'exige aucune variable
d'environnement : `vendor/bin/phpunit packages/bot-detection-bundle/tests/Functional`.

## Exécuter les tests

### Depuis la racine du monodépôt

```bash
vendor/bin/phpunit packages/bot-detection-bundle/tests
```

Les détecteurs du navigateur et leur contrôleur s'exécutent avec l'exécuteur de tests de Node, Stimulus étant remplacé
par un *stub* grâce à `tests/js/register.mjs` :

```bash
node --import ./packages/bot-detection-bundle/tests/js/register.mjs --test packages/bot-detection-bundle/tests/js
```

### Le bundle installé seul

Ce que voit un projet qui installe le bundle. Le bundle n'a aucune dépendance sœur et aucun `phpunit.xml` :

```bash
cp -R packages/bot-detection-bundle /tmp/bot-detection-bundle && cd /tmp/bot-detection-bundle
rm -rf vendor composer.lock
composer install
vendor/bin/phpunit --no-configuration tests
```

Le monodépôt exécute ceci pour chaque bundle avec `composer packages`, accompagné de `composer-dependency-analyser` et
du `composer-dependency-analyser.php` du bundle, dont la seule exception est `symfony/config`, déclaré mais non importé
(exigé parce que `AbstractBundle` implémente une de ses interfaces).

Dans le monodépôt, le code du bundle est aussi soumis à l'analyse statique du dépôt, à ses règles d'architecture, à son
style de code et aux tests de mutation.

## Tester votre application

### Votre adaptateur de compteur

| Vérification | Raison |
|---|---|
| `increment()` ajoute un à la surface de cette instance seulement | Une instance par surface est la façon de séparer le compte |
| Deux instances branchées pour deux surfaces ne partagent pas de compte | Les erreurs de branchement fusionnent des surfaces en silence |
| Une panne du système ne fait pas sortir d'exception de `increment()` (ou, si c'est le cas, votre appelant l'attrape) | Un verdict ne doit jamais aboutir à un refus |
| Rien de la requête n'atteint la clé ou la valeur stockée | Le compteur est la seule trace que laisse un verdict |

### Vos surfaces

Utilisez `InMemoryRobotVerdictCounter` (copiez-le depuis les tests du bundle, ou écrivez les mêmes cinq lignes) et un
vrai `VerdictHandler` :

```php
$counter = new InMemoryRobotVerdictCounter();
$handler = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $counter);

// Submit the sign-up form with verdict=robot: one step of back-off, one robot verdict counted
// Submit with no verdict field: one step of back-off (robotOrUnknown), nothing counted
// Submit with verdict=human: no friction, nothing counted
```

Et vérifiez, dans un test fonctionnel, que le verdict n'apparaît nulle part après la requête : ni dans la session, ni
dans les journaux, ni dans aucune table.

Quand le paquet est installé seul, la suite fonctionnelle est sautée : comme `symfony/event-dispatcher` n'est exigé que
pour le développement, le conteneur de Symfony laisse de côté le répartiteur d'événements et aucun noyau ne démarre.
Elle s'exécute depuis une application qui exige un noyau HTTP, comme la racine du monodépôt.
