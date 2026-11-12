<!-- phil-dev source-sha256=ca096e34424c9bc50deeddfefed161c23770657e8b09376545803bb5750ba446 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ContactCardType` et ses champs | La soumission avec des collections, des entrées et des fiches vides, l'affichage, les options, les entrées incorrectes, le nom structuré |
| `PersonNameType` | La soumission des parties, l'option et le champ de culture, l'ordre, l'affichage, les noms vides et incorrects |
| `ContactCardColumnType` | Le JSON, l'imbrication, les refus, les noms structurés, les fiches enregistrées sans nom |
| `VCardResponse`, `JSContactResponse`, `ContactExtension`, `PhilContactBundle` | Les en-têtes et les noms de fichier, les fonctions, la configuration ajoutée au préalable, le lecteur JSContact et ses limites |
| Le contrôleur Stimulus | Les index, les marqueurs de substitution, le retrait, les événements, avec l'exécuteur de tests de Node |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle, les bundles de
téléphone, d'URL et de messagerie, et celui-ci : `ContactBundleTest` stocke une fiche avec son nom structuré, produit le rendu du
formulaire dans son thème en `fr_CA`, avec et sans les parties du nom, sert et lit une fiche en JSContact, et produit
le rendu des fonctions. Lorsque le bundle est installé seul, où FrameworkBundle est un
prérequis de développement, la suite est ignorée.

```sh
vendor/bin/phpunit packages/contact-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/contact-bundle
```
