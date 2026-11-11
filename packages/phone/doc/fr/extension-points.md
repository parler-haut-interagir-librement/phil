<!-- phil-dev source-sha256=01985fbf1af94887c89b65b58c0edf61366e4106807a5d0a1a49eda4c2e1fdf6 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service ni aucun port, et ses classes sont finales. Ce
qu'une application choisit passe par des arguments.

| Point | Comment |
|---|---|
| Le pays d'un numéro saisi sans son préfixe | L'argument `$region` de `PhoneNumber::parse()` : le pays du visiteur, celui de la boutique, ou aucun pour exiger un `+` |
| Les numéros acceptés | `isValid()`, `type()` et `region()` disent ce qu'est un numéro ; l'application décide des types et des pays qu'elle accepte |
| La façon d'écrire un numéro | `format()` avec un `PhoneFormat` : E.164 pour stocker, international ou national pour afficher, `tel:` pour un lien |
| Les plans de numérotation | Les données PHP de `data/`, importées d'une version de libphonenumber par `Metadata\MetadataImporter` au moyen de `bin/import-metadata.php` ; une version plus récente s'importe, et ne se modifie jamais à la main — voyez [Métadonnées](metadata.md) |
| Attraper les erreurs de la bibliothèque | Chaque exception implémente `Phil\Phone\Exception\PhoneException` : un seul `catch` couvre `InvalidPhoneNumber` et `InvalidMetadata` |

Les opérateurs, les numéros courts et les numéros d'exemple ne sont pas lus : une application qui en a besoin les
cherche ailleurs à partir de la forme E.164.
