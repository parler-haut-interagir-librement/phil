<!-- phil-dev source-sha256=aa5ec7e2db48e3f58518c75f8d2a8461c43bd9478712693cb157490953031641 -->
# phil/phone-bundle

**Un champ de numéro de téléphone, une contrainte de validation, un type de colonne et des filtres Twig pour
Symfony.**

`phil/phone-bundle` intègre `phil/phone` à une application Symfony. `PhoneType` est un champ `tel` qui lit un numéro
saisi sans indicatif de pays dans un pays par défaut : le `default_region` du bundle, sauf si le champ nomme le sien.
Il refuse un numéro qui ne figure dans aucun plan de numérotation, et affiche un numéro de son pays à la forme
nationale et tout autre à la forme internationale. La contrainte `ValidPhoneNumber` accepte un numéro valide des types
et des pays qu'elle nomme, tous par défaut. Avec DoctrineBundle, le type de colonne `phil_phone` stocke un numéro en
E.164 avec son poste, et Twig reçoit les filtres `phone` et `phone_link`. Les messages existent en anglais et en
français.

Il s'adresse aux applications Symfony dont les formulaires recueillent des numéros de téléphone : le mobile d'un
membre, la ligne d'une boutique, une personne-ressource d'un organisme. Le pays par défaut laisse les gens saisir les
numéros comme ils les écrivent chez eux. La contrainte permet à un formulaire de demander seulement un mobile, ou
seulement un numéro du Canada ou des États-Unis.

Utilisez `phil/phone` directement hors de Symfony ou dans un modèle de domaine. Le bundle ne contient aucune règle de
numérotation : l'analyse, la validation et le formatage sont ceux de la bibliothèque, et le bundle les branche sur
Symfony Form, Validator, Doctrine DBAL et Twig.

## État

| Partie | État |
|---|---|
| `PhoneType` : un numéro saisi avec ou sans indicatif de pays, refusé quand il ne figure dans aucun plan | Implémenté et testé |
| `ValidPhoneNumber` : un numéro valide, de types et de pays acceptés | Implémenté et testé |
| Le type de colonne `phil_phone` | Implémenté et testé |
| Twig : `phone`, `phone_link` | Implémenté et testé |
| Messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, enregistrement, `default_region`. |
| [Formulaires et validation](doc/fr/forms-and-validation.md) | `PhoneType` et `ValidPhoneNumber`. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_phone`. |
| [Twig](doc/fr/twig.md) | Les deux filtres. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/phone-bundle
```

```yaml
# config/packages/phil_phone.yaml
phil_phone:
    default_region: CA
```

```php
$builder->add('phone', PhoneType::class);
```

```twig
{% set link = shop.phone|phone_link %}
{% if link %}<a href="{{ link }}">{{ shop.phone|phone('national') }}</a>{% endif %}
```

## Prérequis

PHP 8.5 ou plus récent ; `phil/phone` 0.1 ; `symfony/config`, `symfony/dependency-injection`, `symfony/form`,
`symfony/options-resolver` et `symfony/validator` 8.2 ou plus récent. Doctrine DBAL et Twig sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
