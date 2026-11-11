<!-- phil-dev source-sha256=9463afcb69b8da30ecbed875c893914a1c42c8c4079f8c8a2368fca137518f07 -->
# phil/markup-bundle

**Le Markdown, le BBCode, les wikis, UJO et le HTML des utilisateurs rendus de façon sûre dans Twig, convertis en UJO,
avec phil/markup sous forme de services Symfony.**

phil/markup-bundle intègre phil/markup à une application Symfony. Sa configuration nomme les formats dans lesquels les
textes peuvent être écrits, celui qui est pris quand aucun n'est nommé, le niveau auquel les filtres purifient et le
niveau le plus élevé de chaque format, de sorte que le BBCode écrit par les membres n'atteint jamais `all`, quoi que
demande un gabarit. Il enregistre comme services le moteur de rendu UJO, `Markup`, son compilateur et son purificateur,
et associe le compilateur et le purificateur à leurs interfaces, `MarkupCompiler` et `HtmlPurifier`, par des alias, pour
qu'une application puisse pointer l'un ou l'autre vers sa propre classe. `UjoConverter` donne l'analyseur de chaque
format activé et convertit une source en document UJO ou en son JSON, l'étape que franchit un texte quand il est publié
ou ouvert dans un éditeur par blocs. Lorsque Symfony Form est installé, `MarkupFormatType` permet à un rédacteur de
choisir un format parmi ceux qui sont activés. Avec Twig, le filtre `markup_html` compile et purifie une source,
`markup_purify` purifie de nouveau du HTML stocké, et `markup_ujo` donne le JSON UJO d'une source. Lorsque
`phil/typography` est installé, chaque format reçoit une ponctuation soignée (guillemets typographiques, tirets, points
de suspension, espaces de la locale) dans la locale de la requête. Un bundle complémentaire apporte son propre format,
un dialecte de Markdown ou son propre analyseur, en le déclarant dans `added_formats`, et chaque partie du bundle le
prend par son nom.

Utilisez-le dans une application Symfony dont les gabarits affichent du texte que rédigent des visiteurs ou des
éditeurs, ou qui convertit ce qui a été écrit dans un langage de balisage en blocs d'un éditeur par blocs. Hors de
Symfony, ou pour compiler, purifier et lire à partir de simple code PHP, phil/markup suffit. Un compilateur ou un
purificateur que l'application remplace atteint aussi bien `Markup` que les filtres et phil/content-bundle.

Le bundle ne contient aucune logique de balisage propre. Il branche dans le conteneur les analyseurs, le compilateur et
le purificateur de la bibliothèque, ainsi que le moteur de rendu de phil/ujo, tient chaque texte aux formats et aux
niveaux de la configuration, et les expose aux formulaires et à Twig.

## État

| Partie | État |
|---|---|
| Les formats activés, le format par défaut, le niveau par défaut et le plafond de chaque format : `MarkupFormats` | Implémenté et testé |
| Les formats des compléments, `added_formats` : leur libellé et leur analyseur | Implémenté et testé |
| Le moteur de rendu UJO, `Markup`, `MarkupCompiler` et `HtmlPurifier` comme services | Implémenté et testé |
| `UjoConverter` : les analyseurs des formats activés, chacun vers UJO et son JSON | Implémenté et testé |
| `MarkupFormatType`, un choix parmi les formats activés, ses libellés en anglais et en français | Implémenté et testé |
| Les filtres `markup_html`, `markup_purify` et `markup_ujo` | Implémenté et testé |
| La ponctuation soignée dans chaque format par `phil/typography`, dans la locale de la requête, désactivable | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'enregistrement. |
| [Configuration](doc/fr/configuration.md) | Les formats, les niveaux, la ponctuation soignée, les hôtes d'images, le remplacement du compilateur ou du purificateur. |
| [Conversion en UJO](doc/fr/conversion.md) | `UjoConverter` : les analyseurs, le document, le JSON. |
| [Formulaires](doc/fr/forms.md) | `MarkupFormatType`. |
| [Twig](doc/fr/twig.md) | Les trois filtres, et ce que coûte un rendu. |
| [Exceptions](doc/fr/exceptions.md) | `UnavailableFormat`, et ce que la bibliothèque lance à travers le bundle. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
phil_markup:
    formats: [markdown, bbcode, text]
    levels: { bbcode: forum }
```

```twig
{{ message.source|markup_html('bbcode', 'forum') }}
{{ article.body|markup_purify }}
<div data-blocks="{{ draft.source|markup_ujo('markdown') }}"></div>
```

```php
$json = $converter->toJson($draft->source, $draft->format);   // what the published version keeps
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, `phil/markup` et `phil/ujo` ; Symfony Form pour le type de
formulaire, Twig pour les filtres.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
