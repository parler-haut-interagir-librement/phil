<!-- phil-dev source-sha256=a83523ed7ac2d3a06986bbf0751aad1e520d84c83c3229a3d68d6e962fc74c8a -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ContactMailer` | L'expéditeur, les destinataires, `Reply-To`, les sujets, les en-têtes protégés des sauts de ligne, les gabarits, la locale |
| `ContactFormController`, `ContactFormType`, `ContactMessage` | La page, un message envoyé, un message incomplet, un transport en panne, les options, chaque vérification |
| `PhilContactFormBundle` | Les services, la configuration et ses refus, la protection contre le pourriel requise |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle — un service d'envoi de courriels qui n'envoie nulle part,
`SendEmailMessage` acheminé vers un transport asynchrone en mémoire —, TwigBundle, la protection contre le pourriel et
le bundle sous `/contact` : `ContactFormBundleTest` produit le rendu de la page en français, la soumet comme le ferait
un navigateur, preuve de travail résolue, lit le courriel envoyé, constate que rien n'attend dans la file d'attente et
qu'une soumission sans protection est retenue. Lorsque le bundle est installé seul, où FrameworkBundle est un
prérequis de développement, la suite est ignorée.

```sh
vendor/bin/phpunit packages/contact-form-bundle/tests
```
