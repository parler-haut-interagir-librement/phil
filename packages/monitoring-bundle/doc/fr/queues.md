<!-- phil-dev source-sha256=ef0b77e8517648a71b64857eb9c5b5b09e8a6eb18295274ee4e4e5f454a8c33c -->
# Files d'attente Messenger

## La page

Importez `config/routes/queues.php` derrière le pare-feu de l'application. La page montre :

- chaque transport que Messenger connaît, avec les messages qu'il contient — `not counted` pour un transport qui ne sait
  pas compter —, les transports d'échec étant marqués ;
- pour chaque transport d'échec, ses premiers messages en échec, jusqu'à `queues.failed_limit` : l'identifiant, la
  classe du message, la classe de la dernière erreur, le transport sur lequel il a échoué, ses nouvelles tentatives, et
  le moment de son dernier échec.

Ni le contenu d'un message ni le texte de son erreur ne sont lus : l'un comme l'autre peut contenir des renseignements
personnels. Utilisez `bin/console messenger:failed:show <id>` pour examiner un message, là où s'appliquent les accès de
la console.

## Ses actions

Chaque message en échec a deux formulaires, envoyés avec un jeton CSRF d'identifiant `phil_monitoring_queues` :

- **Relancer** répartit de nouveau le message, vers le transport sur lequel il a échoué, et le retire du transport
  d'échec ;
- **Retirer** le retire du transport d'échec.

Les deux redirigent vers la page avec `303 See Other`. Un transport qui n'est pas un transport d'échec, ou un message
absent, répond `404` ; un jeton absent ou erroné, `400`. Avec des jetons CSRF sans état, ajoutez l'identifiant à
`framework.csrf_protection.stateless_token_ids`.

## Qui la voit

La page et ses actions demandent `queues.role`, `ROLE_ADMIN` par défaut, au vérificateur d'autorisation. Un
utilisateur qui ne l'a pas reçoit la réponse du pare-feu à une page refusée : une page de connexion pour un visiteur
anonyme, `403` pour un utilisateur connecté.

La page n'est servie que si l'application a un bus Messenger, Twig, le routeur, le vérificateur d'autorisation et le
gestionnaire de jetons CSRF. S'il en manque un, `MonitoringPass` retire son contrôleur, et ses routes, si elles sont
importées, n'en trouvent aucun. `queues.enabled: false` retire complètement la page et les files d'attente.

## Sa mise en page

La page étend `queues.layout`, qui définit les blocs `title` et `content`. La mise en page du bundle est une page
simple. Donnez la mise en page de l'administration de l'application pour y placer la page, avec sa navigation et ses
styles.
