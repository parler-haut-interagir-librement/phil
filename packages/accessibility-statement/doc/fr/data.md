<!-- phil-dev source-sha256=8130b115cb970386c214d7d6f7d2a2201ad1b5a8645a2d76b41dcaa8b21888e8 -->
# Données simples

`Statement::toArray()` écrit une déclaration sous forme de données simples, et `StatementReader::fromArray()` les relit
pour retrouver la même déclaration. `StatementReader::fromJson()` lit les mêmes données en JSON, jusqu'à 1 Mio et 16
niveaux de profondeur. Un fichier de configuration, une ligne de base de données ou une API transporte ainsi une
déclaration.

```php
use Phil\AccessibilityStatement\StatementReader;

$statement = StatementReader::fromArray([
    'version' => 2,
    'published_at' => '2026-11-08',
    'entity' => 'Example Org',
    'feedback' => ['email' => 'accessibility@example.com'],
    'non_conformities' => [
        ['criterion' => '1.4.3', 'description' => ['en' => 'Grey text on the event pages.']],
    ],
]);

$statement->status;     // ConformanceStatus::Partial, derived from the non-conformities
$statement->target;     // WCAG 2.2 AA, the default
```

## Les clés

| Clé | Valeur | Si elle est absente |
|---|---|---|
| `version` | entier à partir de 1 | refusé |
| `published_at` | moment | refusé |
| `standard` | `wcag-2.0`, `wcag-2.1`, `wcag-2.2` | `wcag-2.2` |
| `level` | `A`, `AA`, `AAA` | `AA` |
| `status` | `full`, `partial`, `non-conformant` | l'état que justifient les non-conformités |
| `entity` | chaîne | aucune |
| `scope` | texte | aucune |
| `feedback` | table : `email`, `phone`, `url`, `postal_address` (texte), `response_days` | refusé |
| `non_conformities` | liste de tables : `criterion` (obligatoire, par exemple `1.4.3`), `reason` (`non-compliance` par défaut, `disproportionate-burden`, `out-of-scope`), `description`, `alternative` (textes), `fix_by` (moment), `occurrences` (entier), `from_audit` (booléen) | aucune |
| `alternatives` | liste de textes | aucune |
| `enforcement` | table : `procedure` (texte, obligatoire), `authority` (texte), `url` | aucune |
| `evaluation` | table : `evaluated_at` (moment, obligatoire), `method` (`self-assessment` par défaut, `third-party`, `automated`), `pages`, `report_url` | aucune |

Un texte est une table qui associe des étiquettes de langue à des chaînes : `{"en": "…", "fr": "…"}`. Un moment est une
date, `2026-11-08`, lue à minuit UTC, ou une date et une heure avec son décalage, `2026-11-08T10:00:00-05:00` ou `…Z`.
Un moment relatif comme `tomorrow` et une date qui n'existe pas comme `2026-02-30` sont refusés.

`toArray()` omet ce qui n'est pas donné : un null, une liste vide, un zéro, un faux. Il écrit les moments sous la forme
`2026-11-08T10:00:00-05:00`.

## Ce qui est refusé

Une clé inconnue à n'importe quel niveau, une valeur du mauvais type, une valeur hors de ses bornes ou une déclaration
qui se contredit fait refuser toute la déclaration avec une `InvalidStatement` : aucune déclaration n'est lue à moitié,
et aucun avertissement n'est émis. Le message nomme la clé, comme dans `The value of "level" is A, AA or AAA.` ou
`The feedback has no key "fax".`
