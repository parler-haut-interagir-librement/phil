<!-- phil-dev source-sha256=ce5154d16c034692fbe50dbe8f7706625028e671efda1302afef4e82cf7f92ba -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Épeler des montants dans une autre langue, ou changer les mots de l'une des six | Implémentez `Contract\LanguageRules`, ou étendez `Language\AbstractLanguageRules`, et passez-la à `NumberWords` |
| Nommer une devise que les règles ne nomment pas, ou la nommer autrement | Passez un `CurrencyName` à `amount()` |
| Garder les traits d'union conditionnels, dire `and` en anglais, choisir l'orthographe du français | Passez `SpellingOptions` |

`AbstractLanguageRules` porte la grammaire commune : une table de devises, le mot entre les unités et les sous-unités,
le mot pour moins, le singulier à un, et un partitif facultatif après les noms des grands nombres. Une classe qui
l'étend fixe ces éléments ; `correct()` reçoit les mots d'ICU avec le `Kind` du nombre et peut les réécrire.

```php
use Phil\NumberWords\Gender;
use Phil\NumberWords\Language\AbstractLanguageRules;
use Phil\NumberWords\NumberWords;

final class Dutch extends AbstractLanguageRules
{
    public function language(): string { return 'nl'; }
    protected function currencies(): array
    {
        return ['EUR' => ['euro', 'euro', Gender::Masculine, 2, 'cent', 'cent', Gender::Masculine]];
    }
    protected function connector(): string { return 'en'; }
    protected function minus(): string { return 'min'; }
}

new NumberWords('nl', rules: new Dutch())->amount('21.05', 'EUR'); // eenentwintig euro en vijf cent
```

Les règles passées doivent être celles de la langue de la locale, sinon `NumberWords` lève `UnsupportedLocale`.
L'épellation elle-même reste celle d'ICU : les règles corrigent ses mots, elles ne remplacent pas ses jeux de règles.
