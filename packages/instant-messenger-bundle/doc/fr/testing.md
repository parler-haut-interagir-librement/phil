<!-- phil-dev source-sha256=438a776b60a66ae94dd4dec6658856128e9018a6d970e0c31218408941cf6713 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `AcceptedMessengers` | Chaque forme de services acceptés, les choix dans l'ordre, les refus |
| `HandleType`, `HandleTransformer` | La soumission, les choix et leurs valeurs, l'affichage, un identifiant vide ou invalide |
| `HandleOfType` | Des identifiants et des textes sur des services acceptés ou non, les messages et les codes |
| `MessengerExtension`, `PhilInstantMessengerBundle` | Les filtres, les services |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, TwigBundle et le bundle : `InstantMessengerBundleTest`
soumet le champ, valide en `fr_CA` et produit le rendu des filtres. Quand le bundle est installé seul, FrameworkBundle
n'étant qu'un prérequis de développement, la suite est ignorée.

```sh
vendor/bin/phpunit packages/instant-messenger-bundle/tests
```
