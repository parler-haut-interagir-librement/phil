<!-- phil-dev source-sha256=30a7b0817834520e4adf2779079da98132dfc49c55f6075a73b212873f9b075a -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter un style de citation, tel qu'un processeur de fichiers de style CSL que l'application fournit | Implémentez `Contract\CitationStyle` |
| Écrire un style en PHP avec les utilitaires des styles de la bibliothèque | Étendez `Style\AbstractStyle` |
| Citer dans une autre langue | Construisez un `Terms` avec ses mots |
| Lire des fichiers plus grands ou plus petits | Passez `Import\ImportLimits` aux lecteurs, `maxBytes` et `maxItems` à `CslJson` |
| Apporter des références d'ailleurs | Construisez des objets `Model\Reference`, ou du CSL-JSON, et donnez-les à une `ReferenceList` |

Un `CitationStyle` dit comment il ordonne sa bibliographie (`BibliographyOrder::Alphabetical` ou `Citation`), s'il veut
des lettres d'année, et écrit une citation à partir de ses `CitedItem` — chacun avec son `Entry` (référence, numéro,
lettre d'année), son `Cite` et l'indication qu'il s'agit ou non de la première citation de la référence — et une entrée
à partir de son `Entry`. Le processeur fait le reste.

```php
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\RichText;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;
use Phil\Citations\Terms;

final class Keys implements CitationStyle
{
    public function order(): BibliographyOrder { return BibliographyOrder::Citation; }
    public function yearSuffixes(): bool { return false; }

    public function citation(array $items, Terms $terms): RichText
    {
        return RichText::text('['.implode('; ', array_map(static fn (CitedItem $item): string => $item->entry->reference->id, $items)).']');
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        return RichText::concat('['.$entry->reference->id.'] ', RichText::italic((string) $entry->reference->title()));
    }
}
```

Le paquet ne contient aucun fichier de style d'un tiers : une application qui veut des styles CSL fournit les fichiers
et le style qui les lit. Les classes sous `Internal` ne sont pas des points d'extension.
