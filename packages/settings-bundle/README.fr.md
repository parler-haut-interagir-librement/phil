<!-- phil-dev source-sha256=12232a6927fc2ce6273ee58481f294811951feae6ee6b5632fed493917c1a0fd -->
# phil/settings-bundle

**Des réglages que les gens changent eux-mêmes, pour Symfony.**

phil/settings-bundle conserve les réglages que chaque utilisateur change, ses préférences, et ceux du site, que
changent les utilisateurs d'un rôle. Les paquets et l'application les déclarent, par configuration ou par attribut. Le
bundle les lit typés, avec leur valeur par défaut. Il enregistre ce qui en diffère, une ligne par personne et par clé,
et produit une page de formulaires, section par section. Ses préférences d'affichage (schéma de couleurs, mouvement,
contraste, taille du texte) sont aussi conservées pour les visiteurs, dans un témoin de connexion strictement
nécessaire. Elles sont écrites sur l'élément racine de la page, où la feuille de style les applique comme elle applique
les requêtes média du système.

## État

| Partie | État |
|---|---|
| Déclarations par configuration et par attribut, ordonnées par `before` et `after` | Implémenté et testé |
| Lecture typée avec la valeur par défaut, enregistrement, restauration, oubli, événements de changement | Implémenté et testé |
| Stockage DBAL, une lecture par sujet et par requête, réinitialisation entre les requêtes | Implémenté et testé |
| Les préférences et les réglages du site, formulaires par section, CSRF, anglais et français | Implémenté et testé |
| Préférences d'affichage des utilisateurs et des visiteurs, attributs de la racine, contrôleur Stimulus | Implémenté et testé |
| Les préférences du bundle des notifications, l'exportation et l'effacement du bundle de la vie privée | Implémenté et testé |
| Les migrations des valeurs conservées — renommer, convertir, valeur par défaut changée, retirer —, versionnées, par une commande | Implémenté et testé |
| L'aperçu d'une section avant l'enregistrement | Implémenté et testé |
| Les réglages du site derrière SecurityBundle, dans la suite fonctionnelle | Implémenté et testé |
| La recette Flex : le bundle, sa configuration et ses routes | Écrite dans `recipe/0.1`, pour le dépôt de recettes de PHIL |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, la recette, les tables, les routes et l'élément racine. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Réglages](doc/fr/settings.md) | Déclarer, ordonner, lire, écrire, et les événements de changement. |
| [Les pages de réglages](doc/fr/pages.md) | Les préférences et les réglages du site, leurs formulaires, leur aperçu et leur gabarit. |
| [Préférences d'affichage](doc/fr/display.md) | Thème, mouvement, contraste et taille du texte, sur la page et pour les visiteurs. |
| [Notifications et vie privée](doc/fr/integrations.md) | Les préférences de notification, et l'exportation et l'effacement des réglages d'une personne. |
| [Migrations](doc/fr/migrations.md) | Les renommages, les changements de type et les valeurs par défaut d'une déclaration qui a évolué, appliqués une fois par une commande. |
| [Stockage](doc/fr/storage.md) | La table, les lectures d'une requête et la réinitialisation du *worker*. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les déclarations, les services et les événements. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, ses bancs d'essai et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\SettingsBundle\PhilSettingsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_settings.yaml
phil_settings:
    sections:
        author: { before: [notifications] }
    settings:
        author.signature: { type: text, section: author, max_length: 120 }
        site.registrations_open: { type: boolean, default: true, scope: site }
```

```twig
<html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
```

```php
$settings->string('author.signature');        // the current user's, or the default
$settings->save(Subject::user($id), ['author.signature' => 'Ada']);
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent avec FrameworkBundle et TwigBundle, et Doctrine DBAL.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
