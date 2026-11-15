<!-- phil-dev source-sha256=34d4aa5d2100104842a51fc1ac5b41eea29ce3c24491c36403767c16c4f1db36 -->
# phil/lti-bundle

**`phil/lti` dans Symfony : un outil LTI 1.3, une plateforme, ou les deux, à partir d'une seule configuration — les
routes du lancement, un jeton de sécurité pour chaque lancement vérifié, les services de LTI Advantage, des magasins
dans la base de données et des clés qui font leur rotation.**

phil/lti-bundle amène `phil/lti` dans une application Symfony :

- **comme outil** : les routes de l'initiation de connexion et du lancement, un authentificateur qui transforme un
  lancement vérifié en un `LtiToken` qui le porte, un événement pour répondre au lancement, la réponse de Deep Linking
  signée et renvoyée par POST, et les notes envoyées à la plateforme par Messenger, le message ne portant qu'un
  identifiant ;
- **comme plateforme** : le point de terminaison d'autorisation qui signe un lancement pour le membre connecté, le
  retour de Deep Linking, le point de terminaison des jetons, les Assignment and Grade Services et le Names and Role
  Provisioning Service par HTTP, et des événements pour le carnet de notes de l'application ;
- **pour les deux** : la route du jeu de clés, les nonces, les états de connexion, les jetons d'accès, les line items,
  les notes et les notes en attente dans des tables d'une connexion Doctrine DBAL, testées sur SQLite, MySQL 8.4 et
  PostgreSQL 17 ; les clés fabriquées, scellées par le KeyManagement de Symfony et renouvelées par une commande, ou lues
  dans l'environnement ; le formulaire qui envoie un lancement par POST, dessiné avec la recette `Button` du kit et
  envoyé par un contrôleur Stimulus.

Il s'adresse aux applications Symfony qui ouvrent d'autres outils à leurs apprenants, ou que des plateformes
d'apprentissage ouvrent : un site de cours, un système de gestion de l'apprentissage, un quiz, un laboratoire, un outil
de lecture.

Il est bâti sur ce que Symfony fournit : Config et DependencyInjection pour la configuration, Routing et HttpFoundation
pour les routes, Security pour l'authentificateur, Doctrine DBAL pour les magasins, KeyManagement pour les clés
scellées, Messenger pour les notes, Twig et les recettes du UX Toolkit pour le formulaire, AssetMapper et Stimulus pour
son script.

## État

| Partie | État |
|---|---|
| Configuration `phil_lti` : outil, plateforme ou les deux, enregistrements, clés, magasins | Implémenté et testé |
| Outil : initiation de connexion, lancement, authentificateur et `LtiToken`, `LaunchReceived` | Implémenté et testé |
| Outil : réponse de Deep Linking renvoyée par POST, notes envoyées par Messenger ou tout de suite | Implémenté et testé |
| Plateforme : point de terminaison d'autorisation, retour de Deep Linking, point de terminaison des jetons | Implémenté et testé |
| Plateforme : Assignment and Grade Services, Names and Role Provisioning Service, événements | Implémenté et testé |
| Magasins sur Doctrine DBAL, l'écouteur de schéma, `phil:lti:schema` et `phil:lti:purge` | Implémenté et testé sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| Clés scellées par le KeyManagement de Symfony, renouvelées par `phil:lti:rotate-keys` ; clés de l'environnement | Implémenté et testé |
| Le formulaire d'un lancement avec la recette `Button` du kit, et son contrôleur Stimulus | Implémenté et testé |
| Enregistrements gardés dans la base de données, LTI Dynamic Registration | Prévu |
| Un carnet de notes de la plateforme | Laissé à l'application, qui écoute les événements |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, les routes, les pare-feu, les tables, le kit et les ressources. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `phil_lti`, avec un exemple. |
| [L'outil](doc/fr/tool.md) | Les lancements, le jeton et l'utilisateur, répondre à un lancement, Deep Linking, envoyer des notes. |
| [La plateforme](doc/fr/platform.md) | Les lancements, les `LaunchMessages` de l'application, les retours de Deep Linking, les services et leurs événements. |
| [Clés](doc/fr/keys.md) | Les clés dans la table scellées par le KMS, leur rotation, les clés de l'environnement. |
| [Stockage](doc/fr/storage.md) | Les sept tables, leurs colonnes et la purge. |
| [Sécurité](doc/fr/security.md) | Les pare-feu, les témoins dans les cadres, les vérifications du bundle, et ce qui reste à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements, les services à employer, et ce qui reste fermé. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et tester une application. |
| [Référence](doc/fr/reference.md) | Chaque classe, route, service, commande et événement du bundle. |

## Démarrage rapide

```sh
composer require phil/lti-bundle
bin/console doctrine:migrations:diff     # the seven tables
bin/console doctrine:migrations:migrate
```

```yaml
# config/packages/phil_lti.yaml
phil_lti:
    keys: { source: dbal, master_key: lti }
    tool:
        enabled: true
        platforms:
            - issuer: 'https://lms.example.org'
              client_id: '%env(LTI_CLIENT_ID)%'
              authorization_url: 'https://lms.example.org/lti/authorize'
              token_url: 'https://lms.example.org/lti/token'
              key_set_url: 'https://lms.example.org/lti/jwks'
              deployments: ['1']
```

```php
use Phil\LtiBundle\Application\Event\LaunchReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class OpenTheCourse
{
    public function __invoke(LaunchReceived $event): void
    {
        $context = $event->launch->message->context;
        // set $event->setResponse(...) or let the bundle redirect to the target of the launch
    }
}
```

## Prérequis

PHP 8.5 ou plus récent avec l'extension OpenSSL ; `phil/lti`, qui signe par `web-token/jwt-library`, `phil/url` et
`phil/safe-http` ; Config, DependencyInjection, HttpFoundation,
Routing, Security et Twig de Symfony 8.2 ; Doctrine DBAL 4. DoctrineBundle, l'ORM, Console, KeyManagement, Messenger,
AssetMapper et les composants Twig de UX sont facultatifs : chaque partie est enregistrée lorsque son paquet est là.

## Contribuer

Les contributions sont bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
