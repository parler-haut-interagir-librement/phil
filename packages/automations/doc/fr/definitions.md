<!-- phil-dev source-sha256=c5d85cded76b424d4818f1773f41c3f1e16ac16ac984d74f4ebd68b6f23c4a5c -->
# Définitions

Une définition est une version d'une automatisation : son identifiant, sa version, un nom, le déclencheur sur lequel
elle démarre et ses étapes. Un constructeur sans code l'enregistre en JSON ; `DefinitionReader` lit ce JSON, et
`DefinitionWriter` le réécrit, toujours aux mêmes octets pour une même définition.

```json
{
    "id": "welcome",
    "version": 1,
    "name": "Welcome",
    "trigger": {"type": "newsletter.confirmed", "options": {"list": "news"}},
    "steps": [
        {"id": "hello", "type": "action", "action": "newsletter.send", "options": {"template": "hello"}, "retries": 3},
        {"id": "wait", "type": "delay", "seconds": 86400},
        {"id": "still-there", "type": "filter", "condition": {"language": "expression", "source": "subject != null"}},
        {"id": "plan", "type": "branch",
         "condition": {"language": "expression", "source": "payload['plan'] == 'pro'"},
         "then": [{"id": "pro-tips", "type": "action", "action": "newsletter.send", "options": {"template": "pro"}}],
         "else": [{"id": "upgrade", "type": "action", "action": "newsletter.send", "options": {"template": "upgrade"}}]}
    ]
}
```

```php
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;

$definition = new DefinitionReader()->read($json);
$json = new DefinitionWriter()->write($definition);
```

Une définition peut aussi être construite en code, avec `new AutomationDefinition(...)` et les constructeurs nommés de
`Step`.

## Les étapes

| Sorte | Clés | Ce qu'elle fait |
|---|---|---|
| `action` | `action`, `options`, `retries` | Exécute l'action de ce type avec ses options ; réessayée jusqu'à `retries` fois (0 à 10) quand elle échoue d'une façon qui vaut une nouvelle tentative |
| `delay` | `seconds` | Attend ce temps, de 1 seconde à 366 jours, puis continue |
| `filter` | `condition` | Termine l'exécution, sans échec, sauf si la condition est vraie |
| `branch` | `condition`, `then`, `else` | Suit `then` quand la condition est vraie et `else` sinon, puis les étapes après l'embranchement ; l'une ou l'autre voie peut être vide |

Chaque étape a un `id`, un nom en minuscules unique dans la définition. Le résultat d'une action est conservé sous
l'`id` de son étape, pour les conditions et les actions qui la suivent.

## Les conditions

Une condition a un `language` — `expression` quand il est omis — et une `source`. Le langage `expression` est
l'ExpressionLanguage de Symfony sans une seule fonction : ni `constant()`, ni `enum()`. Il voit trois variables :

| Variable | Contient |
|---|---|
| `payload` | Les identifiants que le déclencheur a donnés à l'exécution : `payload['plan']` |
| `outputs` | Ce que chaque étape d'action précédente a rendu, par `id` d'étape : `outputs['subscribe']['created']` |
| `subject` | L'identifiant de la personne ou de l'enregistrement dont parle l'exécution, ou `null` |

Une condition donne `true` ou `false`. Lisez avec `??` une clé qui peut manquer : `payload['coupon'] ?? null`. Une
condition qui donne une autre valeur, ou qui échoue pendant son évaluation, fait échouer l'exécution, et les étapes
faites sont compensées. Un autre langage — les règles d'une application, par exemple — vient d'un `ConditionEvaluator`
que reçoit le catalogue.

## Les limites

| Limite | Valeur |
|---|---|
| JSON | 262 144 octets, 64 niveaux |
| Étapes, embranchements compris | 200 |
| Imbrication des embranchements | 15 niveaux |
| Options d'un déclencheur ou d'une action | 32, chaînes de 2 048 octets, listes de 64 entiers ou chaînes |
| Une condition | 2 048 octets |
| Un nom | 120 caractères |

Le lecteur refuse tout le reste avec `InvalidDefinition` et ne lève jamais une autre exception : une clé inconnue, une
valeur du mauvais type, une clé manquante. Un secret n'est jamais une option : une option le nomme, et un port de
l'application le donne, comme le fait la `key` de l'action de webhook.

## Les versions

Une exécution garde la version avec laquelle elle a démarré. Pour changer une automatisation, publiez une nouvelle
version : les exécutions déjà démarrées continuent avec leurs propres étapes, les nouvelles démarrent avec les
nouvelles étapes. `Definitions::publish()` fait d'une version la version publiée, activée ou non ; `disable()` empêche
une automatisation de démarrer des exécutions, et celles en cours continuent.

## La validation

`DefinitionValidator` vérifie une définition contre le catalogue avant sa publication : son déclencheur et chacune de
ses actions enregistrés, leurs options données comme leurs descriptions le disent, chaque condition dans un langage
connu et lisible.

```php
use Phil\Automations\Definition\DefinitionValidator;

$problems = new DefinitionValidator($catalogue)->problems($definition);   // list of sentences, none when valid
new DefinitionValidator($catalogue)->validate($definition);               // throws InvalidDefinition with all of them
```
