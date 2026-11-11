<!-- phil-dev source-sha256=b68d11d0180f6a2f760ec16398f3d1fd6e5e0afa7411ef4a440a9fd725c98df4 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `AcceptedUrlTypes` | Chaque forme de genres acceptés, la fusion par enum, les refus |
| `TypedUrlType`, `TypedUrlTransformer` | La détection, le protocole par défaut, les genres acceptés seulement, un champ vide |
| `UrlOfType` | Des textes, des adresses et des adresses typées de genres acceptés ou non, des textes invalides, les messages et les codes |
| `UrlColumnType`, `UrlExtension`, `PhilUrlBundle` | La colonne, les aides Twig, les services et le type de colonne ajouté en préfixe |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle :
`UrlBundleTest` stocke une adresse, valide en `fr_CA` et produit le rendu des aides Twig. Quand le bundle est installé
seul, FrameworkBundle n'étant qu'un prérequis de développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/url-bundle/tests
```
