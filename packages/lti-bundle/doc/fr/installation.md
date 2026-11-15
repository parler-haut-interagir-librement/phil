<!-- phil-dev source-sha256=ed41c2cbe2f84eeb199274e0022a98cf122f66e2c8a25caade0f7b1fd30f3dd9 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent ;
- FrameworkBundle, SecurityBundle et TwigBundle de Symfony 8.2 ;
- Doctrine DBAL 4 avec DoctrineBundle, pour le magasin `dbal` ;
- le KeyManagement de Symfony avec un client KMS, pour les clés gardées dans la table ;
- facultatif : l'ORM, pour les migrations ; Console, pour les commandes ; Messenger, pour envoyer les notes par un
  bus ; AssetMapper et StimulusBundle, pour le contrôleur du formulaire ; les composants Twig de UX et la recette
  `Button` du kit, pour son bouton.

## Installation

```sh
composer require phil/lti-bundle
```

La recette Flex du bundle l'enregistre, écrit `config/packages/phil_lti.yaml` avec les deux côtés désactivés et des clés
dans la table sous la clé maîtresse nommée par `LTI_MASTER_KEY`, et `config/routes/phil_lti.yaml` avec la route du jeu
de clés et les routes de chaque côté en commentaire. La recette se trouve dans le dépôt de recettes de PHIL, lu une fois
que l'application liste ce dépôt dans `extra.symfony.endpoint` ; sans cela, enregistrez le bundle et écrivez la
configuration à la main.

## Les routes

Montez les routes du côté que l'application joue, et la route du jeu de clés une seule fois :

```yaml
# config/routes/phil_lti.yaml
phil_lti_jwks:
    resource: '@PhilLtiBundle/config/routes/jwks.php'
    prefix: /lti

phil_lti_tool:
    resource: '@PhilLtiBundle/config/routes/tool.php'
    prefix: /lti

phil_lti_platform:
    resource: '@PhilLtiBundle/config/routes/platform.php'
    prefix: /lti/platform
```

## Les pare-feu

Le lancement de l'outil passe par un pare-feu doté de l'authentificateur du bundle ; le point de terminaison des jetons
et les services de la plateforme sont sans état et hors de tout formulaire de connexion ; le point de terminaison
d'autorisation de la plateforme est derrière le pare-feu de ses membres.

```yaml
# config/packages/security.yaml
security:
    providers:
        lti: { id: Phil\LtiBundle\Infrastructure\Security\LtiUserProvider }
    firewalls:
        lti_services:
            pattern: ^/lti/(jwks|platform/(token|contexts))
            security: false
        lti_launch:
            pattern: ^/lti/(login|launch)
            provider: lti
            custom_authenticators: [Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator]
        main:
            # the application's own, which covers /lti/platform/authorize
```

Lorsque l'application connecte les lancements à ses propres comptes par `LaunchUsers`, le pare-feu des lancements nomme
plutôt le fournisseur de l'application, ou une chaîne des deux.

## Les tables

Avec l'ORM, le bundle ajoute ses sept tables au schéma que l'ORM génère pour la connexion :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, `bin/console phil:lti:schema` affiche le SQL des tables manquantes et `--force` l'exécute. Voir le
[stockage](storage.md).

## Le kit et les ressources

Le formulaire d'un lancement dessine son bouton avec la recette `Button` du kit, installée par le Symfony UX Toolkit,
rien n'étant rendu depuis `vendor/` :

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Le `Button` du kit Shadcn prend les mêmes propriétés. La recette se trouve sous le sous-répertoire des composants de la
configuration `ux_toolkit` ; là où le Toolkit n'est pas activé, comme en production, répétez-le dans
`kit_subdirectory`.

Avec AssetMapper, le bundle sert son contrôleur Stimulus sous le nom `@phil/lti-bundle`. Activez-le dans
`assets/controllers.json` :

```json
{
    "controllers": {
        "@phil/lti-bundle": {
            "auto-submit": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

## Tâches planifiées

Exécutez `phil:lti:purge` chaque jour, pour retirer les nonces, les états de connexion et les jetons d'accès expirés, et
`phil:lti:rotate-keys --if-due` chaque jour, pour renouveler la clé de signature une fois qu'elle atteint sa durée de
vie.
