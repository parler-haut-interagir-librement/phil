<!-- phil-dev source-sha256=bada131b1ad9a3a6f5dfba79c0e89f994e950f17c1e4e57aa55de35bdae17924 -->
# Vérification

## HTML

```php
$report = $checker->checkHtml($html);            // the language of the content is the policy's
$report = $checker->checkHtml($html, 'fr-CA');   // a content that does not say its language is in French
```

Le HTML est une page entière ou un fragment. `Dom\HTMLDocument` l'analyse comme le fait un navigateur, après deux
contrôles : un contenu plus grand que le `maxBytes` de la politique lève `LimitExceeded` avant l'analyse, et un contenu
qui n'est pas en UTF-8 lève `InvalidContent`. Ses éléments sont ensuite indexés une seule fois, dans l'ordre du document
et sans récursion : des éléments imbriqués plus profondément que `maxDepth`, ou plus nombreux que `maxElements`, lèvent
`LimitExceeded`. Chaque règle qui implémente `HtmlRule` et que la politique retient lit ensuite l'index.

Un contenu qui commence — après une marque d'ordre d'octets, des espaces et des commentaires — par `<!doctype` ou
`<html` est une **page entière** : `html-lang` vérifie sa langue, et `heading-h1` signale une page sans titre de premier
niveau. Tout autre contenu est un fragment affiché à l'intérieur d'une page.

## UJO

```php
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);            // read and normalized by phil/ujo
$report = $checker->checkUjo($document, 'fr');
```

Les blocs sont parcourus dans l'ordre du document, chaque bloc imbriqué juste après son parent, que le document ait été
normalisé ou non : un bloc imbriqué est un `Block` de `phil/ujo` ou un tableau de la forme `{id, type, data}`. La langue
d'un bloc est celle de sa *tune* `language`, sinon celle de son parent, sinon celle du document. Chaque règle qui
implémente `UjoRule` lit ce que disent les données d'un bloc : le texte de remplacement d'un bloc `image`, le `title`
d'un `embed`, les pistes d'une `video`, le `level` d'un `header`. Les règles qui lisent le HTML lisent chaque champ de
données contenant du HTML, analysé à part, de sorte qu'un constat nomme le bloc et le champ.

Un texte des données qui contient un `<` est du HTML, sauf s'il s'agit d'un champ brut de son type : la source d'un bloc
`code`, le LaTeX d'un bloc `math`, chaque champ d'un `chart`… ([points
d'extension](extension-points.md#les-champs-bruts-dujo)). Les blocs comptent dans `maxElements`, et l'imbrication des
blocs et des données dans `maxDepth`. Chaque champ HTML est borné comme un contenu HTML.

## Le rapport

| Membre | Donne |
|---|---|
| `passes(Severity $minimum = Error)` | si aucun constat n'atteint `$minimum` ou plus grave : si le contenu peut être publié |
| `findings(Severity $minimum = Notice)` | les constats de sévérité `$minimum` ou plus grave |
| `withSeverity(Severity)` | les constats d'une seule sévérité exacte |
| `ofRule(string)` | les constats d'une règle |
| `results`, `result(string)` | le `RuleResult` de chaque règle exécutée — identifiant, critère, sévérité, nombre de constats, `passed()` — ou d'une seule |
| `message(Finding)` | ce que dit un constat, dans la locale du rapport |
| `inLocale(string)` | le même rapport, avec ses messages dans une autre locale |
| `count()`, `toArray()`, `jsonSerialize()` | le nombre de constats ; le rapport sous forme de tableau et de JSON |

Les constats se suivent règle par règle, dans l'ordre d'exécution des règles, et dans l'ordre du contenu à l'intérieur
d'une règle. Une règle qui ne lit pas le format vérifié — `html-lang` sur un document UJO — ou que la politique
désactive n'a pas de résultat.

## Un constat

| Propriété | Contient |
|---|---|
| `rule` | l'identifiant de la règle, par exemple `image-alt` |
| `criterion` | le `SuccessCriterion` de WCAG 2.2 non respecté, avec `title()`, `level()` et `understandingUrl()` |
| `severity` | `Severity::Error`, `Warning` ou `Notice` |
| `message` | un `Message` : la clé du domaine `phil_accessibility_check` et les valeurs de ses paramètres substituables |
| `location` | un `Location` |

Un `Location` contient ce que l'on sait de l'emplacement du constat. En HTML : la `line` (à partir de 1), le chemin
`element` depuis le corps, par exemple `div:nth-of-type(2) > p > a`, et un `snippet` de sa balise ouvrante coupé à 160
caractères. En UJO : l'identifiant `block`, le `blockType` et le `pointer` du champ, par exemple
`blocks[2].data.items[0].content`, avec le chemin de l'élément dans le champ lorsque le constat se trouve dans son HTML.
`describe()` le rédige pour une personne, et `toArray()` conserve les champs connus.

## JSON

```json
{
    "passed": false,
    "locale": "en",
    "counts": {"error": 1, "warning": 0, "notice": 0},
    "rules": [
        {"rule": "image-alt", "criterion": "1.1.1", "level": "A", "severity": "error", "passed": false, "findings": 1}
    ],
    "findings": [
        {
            "rule": "image-alt",
            "criterion": "1.1.1",
            "level": "A",
            "severity": "error",
            "message": "The image /a.png has no text alternative: describe it in an alt text, or give it alt=\"\" when it is only decoration.",
            "messageKey": "image-alt.missing",
            "parameters": {"source": "/a.png"},
            "location": {"block": "i1", "blockType": "image", "pointer": "blocks[0]"}
        }
    ]
}
```

`passed` correspond à `passes()`, à la sévérité d'erreur. `messageKey` et `parameters` permettent à une application de
traduire un constat avec son propre traducteur.
