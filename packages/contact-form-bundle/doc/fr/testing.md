<!-- phil-dev source-sha256=6a3d3ca492b72e19f169c292af19b797710e9b98300720204a39e924781aa27b -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ContactMailer` | L'expéditeur, les destinataires, `Reply-To`, les sujets, les en-têtes protégés des sauts de ligne, les gabarits, la locale |
| `ContactFormController`, `ContactFormType`, `ContactMessage` | La page, un message envoyé, un message incomplet, un transport en panne, les options, chaque vérification |
| `PhilContactFormBundle` | Les services, la configuration et ses refus, la protection contre le pourriel requise |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle — un service d'envoi de courriels qui n'envoie nulle part
—, TwigBundle, la protection contre le pourriel et le bundle sous `/contact` : `ContactFormBundleTest` produit le rendu
de la page en français, la soumet comme le ferait un navigateur, preuve de travail résolue, lit le courriel envoyé et
constate qu'une soumission sans protection est retenue. Lorsque le bundle est installé seul, où FrameworkBundle est un
prérequis de développement, la suite est ignorée.

```sh
vendor/bin/phpunit packages/contact-form-bundle/tests
```
