<!-- phil-dev source-sha256=82444fff3d7e1fd836cd444cce1f4fee91989ae8fa299be0e90115511224f19e -->
# phil/onboarding-bundle

**L'accueil d'un nouveau membre, pour Symfony : des étapes en ordre, une liste de contrôle et une visite guidée.**

phil/onboarding-bundle guide un nouveau membre dans ses premières actions sur un site. L'application et ses paquets
déclarent des parcours d'accueil, par configuration ou par attribut. Ce sont des étapes ordonnées, chacune avec un
titre, la route où elle mène, l'élément de la page que la visite guidée désigne et sa façon d'être accomplie : cochée
par la personne, accomplie quand un événement Symfony est réparti, accomplie quand un vérificateur de l'application le
dit, ou répondue : une question dont l'application transforme les choix en rôles et en abonnements, par un port, le
bundle ne les écrivant jamais lui-même. Le bundle garde où en est chaque utilisateur, étape par étape, et le montre de
deux façons : une liste de contrôle qui fonctionne sans JavaScript, et une visite guidée qui met en évidence la cible
de chaque étape tour à tour. Une personne peut passer une étape, ranger un parcours et le recommencer. Les libellés
existent en anglais et en français.

Il s'adresse à un site qui accueille de nouveaux membres, auteurs ou clients et veut les aider à s'y retrouver :
remplir un profil, écrire une première page, joindre un groupe. La progression est gardée sous l'identifiant par lequel
l'application nomme ses utilisateurs, quelle que soit sa classe d'utilisateur. Le bundle fonctionne donc avec Symfony
Security ou toute autre connexion.

Le bundle repose sur Doctrine ORM, l'injection de dépendances de Symfony, HTTP Foundation et la protection CSRF sans
état. Twig, Symfony UX Twig Component, le bundle Stimulus, AssetMapper, Symfony Security et `phil/settings-bundle` sont
facultatifs. La liste de contrôle et la visite sont du HTML sémantique dont les boutons sont la recette `Button` du kit,
installée dans l'application par le Symfony UX Toolkit. Avec le bundle des réglages, chaque utilisateur peut désactiver
les visites. Le service `Onboarding` repose sur le port `ProgressRepository`, avec des
stockages Doctrine et en mémoire. Il ne garde aucun état entre les requêtes et convient à un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| Parcours et étapes par configuration et par attribut, ordonnés par `before` et `after`, vérifiés à la construction du conteneur | Implémenté et testé |
| Accomplissement à la main, par un événement Symfony, par un vérificateur de l'application, par une réponse | Implémenté et testé |
| Des questions dont les choix accordent des rôles et des abonnements par `AnswerGrants` | Implémenté et testé |
| `Progress`, gardé avec Doctrine ou en mémoire, une ligne par utilisateur et par parcours | Implémenté et testé |
| `Onboarding` : progression, accomplir, cocher, répondre, passer, ranger, recommencer, oublier ; événements faits d'identifiants | Implémenté et testé |
| Points de terminaison qui cochent, répondent, passent, rangent et recommencent, avec un jeton CSRF sans état | Implémenté et testé |
| La liste de contrôle et la visite : fonctions Twig, composants `PhilOnboarding:Checklist` et `PhilOnboarding:Tour`, contrôleur Stimulus et sa feuille de style, la recette `Button` du kit, en anglais et en français | Implémenté et testé |
| Le réglage `onboarding.tours` de `phil/settings-bundle` | Implémenté et testé |
| L'exportation et l'effacement de la progression d'un utilisateur par `phil/privacy-bundle` | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle, la recette Flex, la table, les routes, la recette du kit, les ressources. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Parcours et étapes](doc/fr/flows.md) | Déclarer les parcours et les étapes, leur ordre, les quatre accomplissements et les questions, lire et modifier la progression. |
| [La liste de contrôle et la visite](doc/fr/display.md) | Les fonctions et composants Twig, le contrôleur, le clavier, les lecteurs d'écran et le mouvement réduit, l'apparence. |
| [Événements](doc/fr/events.md) | Les événements répartis et ce qu'ils transportent. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les déclarations, les services et les événements. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester du code qui utilise des parcours. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/onboarding-bundle
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/packages/phil_onboarding.yaml
phil_onboarding:
    flows:
        author:
            title: onboarding.author.title
            steps:
                profile: { title: onboarding.author.profile, route: app_profile, target: '#profile-form' }
                first-page: { title: onboarding.author.first_page, completion: event, event: App\Event\PageWritten }
```

```twig
<twig:PhilOnboarding:Checklist flow="author" />
<twig:PhilOnboarding:Tour flow="author" />
```

```php
$onboarding->complete($user->getUserIdentifier(), 'author', 'profile');
$onboarding->progress($user->getUserIdentifier(), 'author')->percent();   // 50
```

## Prérequis

PHP 8.5 ou plus récent avec `mbstring` ; Doctrine ORM 3.7 et DBAL 4.5 ; la configuration, l'injection de dépendances,
HTTP Foundation et la protection CSRF de Symfony 8.2 ; les contrats de services de Symfony 3.6 ; l'horloge de PSR-20 et
le répartiteur d'événements de PSR-14. Twig, Symfony Routing, Symfony UX Twig Component, le bundle Stimulus,
AssetMapper, Symfony Security, `phil/ux-bundle` et `phil/settings-bundle` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
