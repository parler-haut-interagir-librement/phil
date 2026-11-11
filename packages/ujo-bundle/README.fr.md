<!-- phil-dev source-sha256=fbde2049524a6092313e80b5ad1045263512a4ae23bddb428cc64ef4058a8b5b -->
# phil/ujo-bundle

**Des documents UJO (Universal JSON Output) dans une application Symfony : un champ qui les vérifie, une contrainte,
et du HTML sûr dans Twig.**

phil/ujo-bundle intègre `phil/ujo` à Symfony. Il déclare les parties de la bibliothèque comme des services construits
à partir d'une courte configuration : si les blocs de HTML brut sont acceptés, quelle taille un document peut avoir, à
quelle profondeur les blocs peuvent s'imbriquer. Il ajoute à Twig les filtres et les fonctions `ujo_html` et
`ujo_text`, un champ de formulaire `UjoType` qui reçoit le JSON qu'écrit un éditeur par blocs et le garde sous forme
canonique, et une contrainte `ValidUjo` qui vérifie un document et, sur demande, les types de ses blocs. Il ne fournit
pas l'éditeur par blocs lui-même et ne stocke pas les documents.

Il s'adresse à toute application Symfony dont les rédacteurs composent dans un éditeur par blocs qui écrit du UJO : le
champ et la contrainte arrêtent un document invalide ou indésirable au formulaire, et les filtres produisent le rendu
purifié des documents stockés, selon les règles actuelles.

C'est un bundle Symfony basé sur `phil/ujo`, `symfony/form` et `symfony/validator`. Twig est facultatif : les filtres
et les fonctions sont enregistrés quand il est installé. Chaque service est la classe propre de la bibliothèque,
déclarée sous son nom de classe : une application en remplace donc n'importe lequel en le redéfinissant.

## État

| Partie | État |
|---|---|
| Configuration : `allow_raw`, `max_bytes`, `max_depth`, `max_blocks`, `max_html_length`, `max_raw_length` | Implémenté et testé |
| Services : `BlockTypeRegistry`, `Normalizer`, `HtmlRenderer`, `PlainText`, `JsonDecoder`, `Ujo` | Implémenté et testé |
| `ujo_html` et `ujo_text`, filtres et fonctions | Implémenté et testé |
| `UjoType` et `UjoTransformer` | Implémenté et testé |
| `ValidUjo`, avec les types acceptés et un niveau de validation, forme ou publication ; messages en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et activation du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les services, et le remplacement de l'un d'eux. |
| [Twig](doc/fr/twig.md) | `ujo_html` et `ujo_text`. |
| [Formulaires et validation](doc/fr/forms-and-validation.md) | `UjoType`, ses options, et la contrainte `ValidUjo`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester un formulaire qui contient un document. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/ujo-bundle
```

```php
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;

$builder->add('body', UjoType::class);            // canonical JSON in, canonical JSON out

#[ValidUjo(types: ['paragraph', 'header', 'list', 'quote', 'image'])]
public ?string $body = null;
```

```twig
{{ article.body|ujo_html }}
<meta name="description" content="{{ article.body|ujo_text|u.truncate(160) }}">
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 et `phil/ujo`. Twig 3.21, pour les filtres et les fonctions.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
