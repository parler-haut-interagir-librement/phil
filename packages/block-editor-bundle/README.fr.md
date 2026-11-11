<!-- phil-dev source-sha256=93fa8f928e95c75baca59addca7d53d01a8d904fec2ab03d09d2b69e81a246ff -->
# phil/block-editor-bundle

**Un éditeur par blocs pour les formulaires Symfony, en contrôleurs Stimulus, qui écrit des documents UJO (Universal
JSON Output) vérifiés sur le serveur.**

phil/block-editor-bundle donne à un formulaire Symfony un éditeur par blocs à la manière de Notion. Les rédacteurs
ajoutent des blocs depuis un menu `/` ou un bouton +, transforment un bloc en un autre, les déplacent par une poignée ou
au clavier, et les imbriquent dans des dépliants et des colonnes. Ils mettent le texte en forme depuis une barre
d'outils ou par des raccourcis, mentionnent des personnes avec `@`, lient des pages avec `[[`, téléversent des images et
collent depuis Word, Google Docs ou n'importe quelle page sans en traîner les styles. L'éditeur lit et écrit UJO
(Universal JSON Output), le format de documents par blocs de `phil/ujo`, dans un champ caché. À la soumission,
`phil/ujo` vérifie ce JSON et le restreint aux outils du champ. Un mode réduit n'offre que les paragraphes, les titres,
les listes, les citations et les liens, pour les sites simples. Le bundle ne stocke pas les documents et n'en produit
pas le rendu pour les lecteurs. Avec `phil/edit-lock-bundle`, les rédacteurs modifient un document ensemble, un
rédacteur par bloc.

Il s'adresse à toute application Symfony dont les rédacteurs composent par blocs des pages, des articles, des entrées de
wiki ou des messages de forum. Le champ est un type de formulaire comme un autre : un document arrive donc dans une
propriété d'entité sous forme de JSON canonique ou de `Document`. Pour les lecteurs, `phil/ujo-bundle` en produit le
rendu avec `ujo_html`. Les recherches derrière `@` et `[[`, ainsi que le stockage des images téléversées, appartiennent
à l'application : le bundle définit les ports et les routes, et l'application écrit une classe pour chacun.

C'est un bundle Symfony construit sur `phil/ujo` et `phil/ujo-bundle`. L'éditeur est un contrôleur Stimulus servi par
AssetMapper, sans étape de construction, sans paquet npm à installer et sans autre cadriciel que Stimulus. Notion sert
de modèle pour l'interaction. Son assainisseur de texte en ligne, dans le navigateur, reproduit celui de `phil/ujo` : ce
qui est collé est donc nettoyé selon les règles mêmes du serveur.

## État

| Partie | État |
|---|---|
| `Toolset` : outils de bloc et outils en ligne, préréglages `full` et `reduced` | Implémenté et testé |
| `BlockEditorType`, `BlockEditorTransformer`, le thème de formulaire : JSON vérifié et restreint par `phil/ujo` | Implémenté et testé |
| `ToolsetNormalizers` : un normaliseur `phil/ujo` par jeu d'outils | Implémenté et testé |
| Ports `ImageUploader` et `ReferenceSearch` ; `ImageUploadController`, `ReferenceSearchController`, routes | Implémenté et testé |
| `DocumentReferences` : les mentions, les liens de page et les fichiers d'un document | Implémenté et testé |
| Le contrôleur de l'éditeur : blocs, menu `/`, poignées, menu de bloc, barre d'outils, raccourcis, collage, imbrication, mentions, images, mode lecture seule, anglais et français | Implémenté ; modules purs testés sous Node, contrôleur vérifié dans un navigateur ([tests](doc/fr/testing.md)) |
| Écrire à plusieurs : l'événement `focus`, et les événements `apply`, `remove` et `lock` qu'un autre contrôleur émet | Implémenté ; `place()` testé sous Node, vérifié dans un navigateur avec `phil/edit-lock-bundle` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, les routes, le contrôleur et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les services et les jeux d'outils. |
| [Formulaires](doc/fr/forms.md) | `BlockEditorType`, ses options, le thème de formulaire et la vérification d'un document soumis. |
| [L'éditeur](doc/fr/editor.md) | Les blocs, les menus, les poignées, la barre d'outils, les raccourcis, le collage, l'imbrication, le mode lecture seule. |
| [Intégrations](doc/fr/integrations.md) | Les téléversements d'images, les recherches `@` et `[[`, une médiathèque, le suivi des utilisations. |
| [JavaScript](doc/fr/javascript.md) | La configuration du contrôleur, ses événements, ses modules, ses libellés et sa feuille de style. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle et les réponses de ses points de terminaison. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, le seuil de mutation et les vérifications faites dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/block-editor-bundle
```

```php
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;

$builder->add('body', BlockEditorType::class);                         // the configured toolset
$builder->add('summary', BlockEditorType::class, ['preset' => 'reduced']);
```

```twig
{{ form_row(form.body) }}                 {# the editor, through the bundle's form theme #}
{{ article.body|ujo_html }}               {# for readers, with phil/ujo-bundle #}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `phil/ujo` et `phil/ujo-bundle`. Dans le navigateur, Stimulus 3, servi par
`symfony/stimulus-bundle` et AssetMapper.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
