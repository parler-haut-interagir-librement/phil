<!-- phil-dev source-sha256=4f3c6d16ec0c2f9a728e388af8a920fdc0a09b8f77121a599c873eae4d9eff0a -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque n'a aucune interface à implémenter : le vocabulaire est celui de l'IPTC, et chaque type est une valeur.

| Pour | Faites |
|---|---|
| Libeller les types dans une autre langue | Traduisez les codes dans le catalogue de l'application, par `DigitalSourceType::value` ; `label()` est l'anglais de l'IPTC |
| Combiner les vues de plusieurs œuvres | Appelez `GenerativeAi::combine()` avec leurs vues |
| Conserver une déclaration | Gardez `toArray()` dans une colonne JSON et relisez-la avec `fromArray()` |
| Demander des déclarations | Construisez une `DisclosureRequirement` par concours, boutique ou catalogue, depuis la configuration de l'application |
