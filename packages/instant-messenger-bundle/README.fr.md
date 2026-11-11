<!-- phil-dev source-sha256=6f88c026e1da6381b93b90fe355315dd0dddaafef76b3895cb478479f42ea8c1 -->
# phil/instant-messenger-bundle

**Un champ d'identifiant de messagerie avec son service, une contrainte de validation et des filtres Twig pour
Symfony.**

`phil/instant-messenger-bundle` intègre `phil/instant-messenger` à une application Symfony. `HandleType` est formé de
deux champs qui donnent un `Handle` : le service, choisi parmi ceux qui sont acceptés, et l'identifiant tel que la
personne le tape. La contrainte `HandleOfType` vérifie un `Handle` selon son service, et un texte en détectant son
service parmi ceux qui sont acceptés. Twig reçoit `messenger_display`, `messenger_link` et `messenger_label`. Les
messages sont fournis en anglais et en français.

Il s'adresse aux applications Symfony dont les formulaires recueillent un moyen de joindre quelqu'un : le Signal d'un
membre, le WhatsApp d'une boutique, l'identifiant Matrix d'un organisateur. Le champ demande le service parce que les
identifiants se chevauchent : un nom d'utilisateur ou un numéro de téléphone convient à plusieurs services. Les services
acceptés se nomment comme `phil/url-bundle` nomme les genres : `null` pour chaque service international, une classe
d'enum, un cas, un `EnumSet` ou une liste de ceux-ci.

Utilisez directement `phil/instant-messenger` hors de Symfony ou à l'intérieur d'un modèle de domaine. Le bundle n'a pas
de type de colonne : un identifiant est stocké dans deux colonnes que l'application mappe, le service avec sa propre
enum. Il branche la bibliothèque sur les composants Form, Validator et Twig de Symfony, et n'ajoute aucune règle qui lui
soit propre.

## État

| Partie | État |
|---|---|
| `HandleType` : le service choisi parmi ceux qui sont acceptés, et l'identifiant | Implémenté et testé |
| `HandleOfType` : un identifiant, ou un texte détecté, sur un service accepté | Implémenté et testé |
| Twig : `messenger_display`, `messenger_link`, `messenger_label` | Implémenté et testé |
| Les messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'enregistrement. |
| [Formulaires et validation](doc/fr/forms-and-validation.md) | `HandleType`, `HandleOfType`, les services acceptés et le stockage d'un identifiant. |
| [Twig](doc/fr/twig.md) | Les trois filtres. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/instant-messenger-bundle
```

```php
$builder->add('messenger', HandleType::class, ['types' => [KnownMessenger::Signal, KnownMessenger::WhatsApp]]);
```

```twig
{% set link = shop.messenger|messenger_link %}
{% if link %}<a href="{{ link }}">{{ shop.messenger|messenger_label }} {{ shop.messenger|messenger_display }}</a>{% else %}{{ shop.messenger|messenger_label }} {{ shop.messenger|messenger_display }}{% endif %}
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/instant-messenger` 0.1 et `phil/enums` 0.3 ; `symfony/config`,
`symfony/dependency-injection`, `symfony/form`, `symfony/options-resolver` et `symfony/validator` 8.2 ou plus récent.
Twig est facultatif.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
