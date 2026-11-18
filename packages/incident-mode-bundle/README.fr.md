<!-- phil-dev source-sha256=6f09b3f6ca3ab7c24b6dd16d1e3474d757eae4569f340cb05115a67ef5632fe6 -->
# phil/incident-mode-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/incident-mode-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/incident-mode-bundle)

**Le mode incident de phil/incident-mode dans une application Symfony : suspendre les inscriptions, les invitations, les
nouveaux messages ou tout sauf la lecture pendant une durée toujours fixée, depuis une page d'administration ou la
console, et le dire honnêtement à chaque visiteur jusqu'à la fin.**

phil/incident-mode-bundle branche la bibliothèque `phil/incident-mode` dans Symfony. Sa table vit sur la connexion DBAL
que choisit l'application, et l'écouteur de schéma de la bibliothèque l'ajoute au schéma que génère Doctrine. Les
préréglages viennent de la configuration : les cinq préréglages intégrés, ceux de l'application, certains remplacés ou
laissés de côté. Un incident se démarre, se prolonge dans les limites de son préréglage et se lève depuis une page
d'administration — protégée par un rôle, chaque formulaire envoyé avec un jeton CSRF, construite avec les recettes du
kit PHIL UX ou du kit Shadcn — ou depuis quatre commandes de console, au nom d'un acteur connu par un identifiant
stable, jamais une adresse courriel.

Tant qu'un incident est en vigueur, les points de contrôle refusent ce qu'il suspend : un contrôleur qui porte
`#[SuspendedDuring('signups')]`, une route dont les valeurs par défaut nomment une restriction, un chemin de code qui
appelle `Checkpoint::guard()`, un formulaire avec l'option `suspended_during`. Un refus est une page qui dit ce qui est
suspendu et jusqu'à quand, dans la langue du visiteur et le fuseau horaire configuré, avec le statut 503 et un en-tête
`Retry-After`. Une bannière et une page d'état publique disent la même chose. Les membres établis sont épargnés des
restrictions que la configuration énumère, par le port `EstablishedMembers` de la bibliothèque, que l'application
branche sur ses propres niveaux de confiance.

Chaque incident est levé à sa fin par le message Messenger différé de la bibliothèque, et un balayage récurrent de
Symfony Scheduler consigne la fin de tout incident dont un transport a perdu le message. Les événements de la
bibliothèque passent par le répartiteur d'événements de Symfony ; avec `phil/audit-bundle` enregistré, chaque début,
chaque prolongation et chaque levée est écrit dans sa piste, et avec `phil/privacy-bundle`, les incidents qu'un membre a
démarrés ou levés répondent à ses demandes d'accès et d'effacement. Les services ne gardent rien entre deux requêtes.

## État

| Partie | État |
|---|---|
| La table sur une connexion configurable, l'écouteur de schéma, les préréglages de la configuration | Implémentés et testés sur SQLite, MySQL et PostgreSQL |
| `phil:incident:start`, `phil:incident:extend`, `phil:incident:lift`, `phil:incident:list` | Implémentées et testées |
| La page d'administration : démarrer depuis un préréglage, prolonger dans ses limites, lever, le journal une page à la fois | Implémentée et testée avec le kit PHIL UX et le kit Shadcn |
| La levée par le message Messenger différé et le balayage récurrent de Symfony Scheduler | Implémentés et testés |
| La page de refus, la page d'état, le fragment d'avis, la bannière, les fonctions Twig | Implémentés et testés en anglais et en français |
| `#[SuspendedDuring]`, la valeur par défaut de route `_suspended_during`, `Checkpoint`, l'option `suspended_during` des formulaires | Implémentés et testés |
| Les membres établis épargnés par `EstablishedMembers`, jamais les visiteurs | Implémentés et testés |
| L'audit par phil/audit-bundle, l'export et l'effacement par phil/privacy-bundle | Implémentés et testés |
| Un nom de table au choix de l'application | Non disponible : la bibliothèque nomme sa table `phil_incident_activation` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, la table et sa migration, les routes, les transports. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_incident_mode`, avec un exemple. |
| [Administration](doc/fr/administration.md) | La page, le rôle, l'acteur, le jeton CSRF, les kits, la mise en page et les commandes de console. |
| [Points de contrôle](doc/fr/checkpoints.md) | L'attribut, la valeur par défaut de route, le service, les formulaires, la page de refus, la bannière et la page d'état. |
| [Levée automatique](doc/fr/automatic-lifting.md) | Le message différé, le balayage récurrent, une tâche cron sans Messenger. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les événements, les mots et les gabarits. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/incident-mode-bundle
```

```yaml
# config/packages/phil_incident_mode.yaml
phil_incident_mode:
    spare_established: [new-posts, new-comments]
    established_members: App\Trust\EstablishedMembers
    presets:
        suspend-listings: { label: 'Suspend new listings', restrictions: [new-listings], proposed: 4h, longest: 2d }
```

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

#[SuspendedDuring('signups', methods: ['POST'])]
public function register(Request $request): Response
```

```twig
{{ include('@PhilIncidentMode/_banner.html.twig') }}
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5, Twig et
les composants Twig de Symfony UX, et `phil/incident-mode` 0.2. La console, les formulaires, Messenger, Scheduler,
l'ORM, `phil/audit-bundle` et `phil/privacy-bundle` sont utilisés lorsqu'ils sont installés.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
