<!-- phil-dev source-sha256=98a4f09f667670075bc65c4d6c1071b55c006150f40a00e1da038b3bd17165c6 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `CountryFields`, `KnownCountries` | Champs, libellés, destinataires, subdivisions dans leurs langues |
| `AddressType`, `AddressTransformer` | Pays par défaut et soumis, champs masqués, choix, erreurs par champ, pays offerts |
| `ValidAddress`, `AddressColumnType`, `AddressExtension`, `AddressFormatController`, `PhilAddressBundle` | Violations par chemin, JSON, filtres dans leurs langues, le point de terminaison, la configuration |
| Le contrôleur Stimulus | Rangées affichées, libellées, obligatoires, choix et champs texte, récupération, avec l'exécuteur de tests de Node |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle et le bundle, au
Canada. `AddressBundleTest` stocke et valide une adresse, produit le rendu du formulaire en `fr_CA` avec son contrôleur
et lit le point de terminaison. Lorsque le bundle est installé seul, FrameworkBundle n'étant qu'un prérequis de
développement, la suite est sautée.

```sh
vendor/bin/phpunit packages/address-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/address-bundle
```
