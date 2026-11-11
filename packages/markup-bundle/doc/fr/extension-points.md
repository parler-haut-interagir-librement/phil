<!-- phil-dev source-sha256=1749e4e67a01bb205f80a4fc5d040232dc720c6d8c6808a4a44eb21bea9583a3 -->
# Points d'extension

Ce qu'une application configure, remplace ou décore. Les compilateurs, les purificateurs et les analyseurs sont ceux de
`phil/markup`.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `formats`, `default_format` | Les formats dans lesquels les textes peuvent être écrits, dans l'ordre où le formulaire les propose, et celui qui est pris quand aucun n'est nommé |
| `added_formats` | Les formats qu'apportent les compléments : leur libellé, et le service de leur `ReaderProvider` ; un bundle complémentaire ajoute le sien en préfixe |
| `levels`, `default_level` | Le niveau le plus élevé auquel chaque format est purifié, quoi que demande un gabarit, et le niveau pris quand aucun n'est nommé |
| `smart_punctuation` | Si le HTML passe par un typographe avant d'être purifié, et dans quelle locale |
| `media_hosts` | Les hôtes d'où peuvent venir les images, donnés à `SanitizerPurifier` quel que soit le bundle qui l'enregistre |

Voir [Configuration](configuration.md).

## Services

| Service | Comment une application l'utilise |
|---|---|
| `Phil\Markup\MarkupCompiler` | Un alias : pointé vers le compilateur propre de l'application (`#[AsAlias(MarkupCompiler::class)]`), et `Markup`, les filtres et les bundles qui compilent suivent ; la ponctuation soignée décore ce vers quoi il pointe |
| `Phil\Markup\HtmlPurifier` | Un alias : pointé de la même façon vers le purificateur propre de l'application ; un purificateur que l'application construit elle-même garde ses propres hôtes d'images |
| `Phil\Ujo\Html\HtmlRenderer` | Le moteur de rendu avec lequel `DefaultCompiler` produit le rendu UJO : les moteurs de rendu de blocs de l'application, par la fabrique que documente `phil/ujo-bundle`, produisent le rendu de ses propres blocs dans chaque format |
| `Phil\Markup\Markup` | Injecté partout où des textes sont compilés et purifiés ; public |
| `Phil\MarkupBundle\Infrastructure\Format\MarkupFormats` | Injecté pour lire les formats et les niveaux activés ; public |
| `Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter` | Injecté pour convertir un texte en UJO ; lit avec `DefaultCompiler` quelle que soit la cible de `MarkupCompiler` ; public |

Le compilateur propre d'une application change le HTML, pas les analyseurs : ce que lit le convertisseur reste les
analyseurs de la bibliothèque et ceux des formats déclarés dans `added_formats`.

## Dialectes et formats des compléments

Un dialecte de Markdown (ses règles en ligne et de bloc, la syntaxe qu'il lit autrement) est un `MarkdownDialect` de
`phil/markup`, donné à un `MarkdownReader` ; un format entier est un `ReaderProvider`, qui donne cet analyseur ou un
analyseur à lui. Un bundle complémentaire enregistre son fournisseur comme service, le déclare dans `added_formats`
depuis `prependExtension()` et fournit les traductions de son libellé dans le domaine `phil_markup` :

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_markup', ['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => ShoutReaders::class]]]);
}

public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->register(ShoutReaders::class, ShoutReaders::class);
}
```

L'application active ensuite le format, ou laisse `formats` vide pour les prendre tous.

## Formulaires et Twig

| Point | Comment |
|---|---|
| Les formats qu'offre un champ | `MarkupFormatType`, parmi les formats activés ; voir [Formulaires](forms.md) |
| Compiler dans les gabarits | `phil_markup`, `phil_purify` et `phil_ujo`, avec un format et un niveau sous le plafond du format ; voir [Twig](twig.md) |
