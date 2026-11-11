<!-- phil-dev source-sha256=622daaa61ff9cccfa58971acaf8577a4b1ecb2a8cbc12f6cf7d126308f24af1a -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port, et ses classes sont finales. Ce que choisit une
application passe par des arguments, et les données sont importées plutôt que configurées.

| Point | Comment |
|---|---|
| La langue d'une adresse | Le `$locale` de `AddressFormatter::format()`, `lines()`, `postalLabel()` et `Countries::name()` : la langue du pays choisit son format local et le nom de ses subdivisions |
| Le pays d'où part le courrier | Le `$origin` de `AddressFormatter::postalLabel()` : la ligne du pays n'est ajoutée que depuis l'étranger |
| Des règles plus strictes que celles du pays | `AddressValidator::violations()` donne ce que dit le format du pays ; les règles propres à l'application — une zone de livraison, un champ qu'elle exige toujours — se vérifient à côté |
| L'endroit où une adresse est conservée | `AddressArray::from()` donne un tableau pour une seule colonne JSON, `AddressArray::to()` le relit |
| Les données structurées | `SchemaOrg::of()` donne un `PostalAddress` de schema.org à intégrer au JSON-LD de l'application |
| Les formats et les subdivisions | Les données PHP de `data/`, importées d'une version de commerceguys/addressing par `Data\DataImporter` avec `bin/import-data.php` ; une version plus récente s'importe, elle ne se modifie jamais à la main — voir [Données](data.md) |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Address\Exception\AddressException` |

Les noms de pays viennent de symfony/intl et suivent ses versions : une application qui nomme un pays autrement garde
ses propres libellés.
