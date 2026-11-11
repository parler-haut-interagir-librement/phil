<!-- phil-dev source-sha256=70f703ad4c74559f9fe646cd17c124bad22fa789a44fe18ef6757fa248698419 -->
# Configuration

```yaml
phil_markup:
    formats: [markdown, bbcode, text] # every format by default
    default_format: ~                 # the first enabled format by default
    default_level: all                # basic, forum or all
    levels:                           # the highest level of a format; all for a format not named
        bbcode: forum
    added_formats:                    # the formats add-ons bring, by name; an add-on bundle prepends its own
        shout: { label: Shout, reader: app.shout_reader }
    smart_punctuation:
        enabled: true                 # when phil/typography is installed
        locale: ~                     # the locale of the request by default
    media_hosts: [cdn.example.com]    # null by default: any host
```

## Formats

`formats` liste par leur nom les formats dans lesquels les textes peuvent être écrits (`markdown`, `html`, `bbcode`,
`mediawiki`, `dokuwiki`, `creole`, `text`, `ujo` et ceux de `added_formats`), dans l'ordre où `MarkupFormatType` les
propose. Vide ou absente, la liste comprend tous les formats, les formats ajoutés en dernier. Un nom qu'aucun format ne
porte est refusé à la construction du conteneur. Un format nommé deux fois compte une seule fois, et la liste d'un
fichier de configuration ultérieur remplace celle d'un fichier antérieur au lieu de s'y ajouter. Un gabarit, le
convertisseur ou un formulaire qui demande un format absent de la liste reçoit un [`UnavailableFormat`](exceptions.md).

`default_format` est le format pris quand un filtre ou le convertisseur n'en nomme aucun : le premier format activé
quand il n'est pas donné. Un format par défaut qui n'est pas activé est refusé à la construction du conteneur.

## Formats des compléments

`added_formats` déclare les formats qu'apportent les compléments, chacun par son nom : `label`, ce qu'affiche un
formulaire, traduit dans le domaine `phil_markup`, et `reader`, l'identifiant du service qui implémente le
`ReaderProvider` de `phil/markup`. `DefaultCompiler` lit le format avec l'analyseur que donne le service, et chaque
partie du bundle (les filtres, le convertisseur, le formulaire, les niveaux) prend son nom comme celui d'un format
intégré. Un bundle complémentaire déclare son propre format depuis son `prependExtension()`, de sorte qu'il suffit
d'installer le complément :

```php
$builder->prependExtensionConfig('phil_markup', ['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => ShoutReaders::class]]]);
```

Un nom compte de 1 à 16 lettres minuscules, chiffres et traits d'union, commence par une lettre et n'est pas un format
intégré, pour tenir dans la colonne d'un `MarkedUpText` ; tout autre nom est refusé à la construction du conteneur. Les
entrées de plusieurs fichiers s'additionnent.

## Niveaux

`default_level` est le niveau auquel les filtres Twig purifient, et auquel le convertisseur lit, quand aucun n'est
nommé.

`levels` donne, format par format, le niveau le plus élevé auquel un texte dans ce format est purifié, quoi que demande
un gabarit : avec `bbcode: forum`, `{{ post|markup_html('bbcode', 'all') }}` purifie à `forum`, et le BBCode d'un membre
ne garde donc aucun titre, aucun tableau ni aucun HTML brut. Un format non nommé monte jusqu'à `all`. Le plafond
s'applique aussi à `markup_purify` lorsqu'il reçoit un `MarkedUpText`, selon le format dans lequel le texte a été écrit,
et au convertisseur, qui ne lit le HTML brut en bloc `raw` qu'à `all`. Un niveau qui n'est pas `basic`, `forum` ou
`all`, ou un format qui n'en est pas un, est refusé.

## Ponctuation soignée

Lorsque `phil/typography` est installé, le HTML de chaque format passe par un typographe avant d'être purifié :
guillemets typographiques, tirets, points de suspension et espaces que la locale place autour de la ponctuation, tels
que les définit le préréglage de la locale.

```twig
{{ '"Oui" ! Voilà...'|markup_html('markdown') }}
{# <p>« Oui » ! Voilà…</p>, in a French request #}
```

Le typographe ne réécrit que du texte : le contenu de `pre`, `code`, `kbd`, `script`, `style` et `textarea` garde ses
caractères, et le purificateur s'exécute toujours ensuite, de sorte que le HTML est aussi sûr que sans lui. Il agit sur
le compilateur derrière l'alias `Phil\Markup\MarkupCompiler`, que décore `phil_markup.smart_punctuation` : `Markup`, les
filtres, un `MarkedUpText` construit par `Markup::text()` et `phil/content-bundle` en profitent, tout comme un
compilateur que l'application place derrière l'alias. `UjoConverter` lit la source lui-même et garde ses caractères.

| Option | Valeur par défaut | Effet |
|---|---|---|
| `smart_punctuation.enabled` | `true` | `false`, ou `smart_punctuation: false`, laisse le HTML du compilateur tel quel |
| `smart_punctuation.locale` | `null` | La locale des corrections ; si elle est nulle, la locale courante du `LocaleSwitcher` de Symfony (celle de la requête, ou celle que fixe `runWithLocale()`), et la locale par défaut des typographes sans le traducteur |

Les typographes sont ceux de `phil/typography-bundle` lorsqu'il est installé, avec les locales, les correcteurs et la
césure qui y sont configurés. Sinon, le bundle construit les siens, `phil_markup.typographers` : le préréglage de chaque
locale, par défaut la locale par défaut du noyau, sans trait d'union conditionnel. Lorsque `phil/typography-bundle`
corrige aussi le HTML des documents UJO, ce qu'il fait par le même `Phil\Ujo\Html\HtmlRenderer`, les formats rendus par
UJO lui sont laissés, dans la locale de son option `ujo`, et la ponctuation soignée ne corrige que le HTML et le texte
brut : aucun texte n'est corrigé deux fois.

## Hôtes d'images

`media_hosts` est la liste des hôtes d'où peuvent venir les images, sous-domaines compris, en plus du site lui-même.
`[]` ne garde que les images du site, et `null`, la valeur par défaut, accepte tout hôte. Une image d'un autre hôte fait
appeler cet hôte par le navigateur de chaque lecteur, ce qui lui apprend son adresse : un site dont les membres écrivent
des textes à `forum` la définit. Une image d'un hôte non listé perd son `src`. Les hôtes parviennent à
`SanitizerPurifier`, quel que soit le bundle qui l'enregistre, `phil/content-bundle` compris, par une passe de
compilation ; un purificateur que l'application construit elle-même n'est pas touché.

## Remplacer le compilateur ou le purificateur

`Phil\Markup\MarkupCompiler` et `Phil\Markup\HtmlPurifier` sont des alias. Une application pointe l'un ou l'autre vers
son propre service, et `Markup`, les filtres et `phil/content-bundle` suivent :

```php
#[AsAlias(HtmlPurifier::class)]
final readonly class StrictPurifier implements HtmlPurifier
{
    public function purify(string $html, Level $level): string { /* … */ }
}
```

Le moteur de rendu `Phil\Ujo\Html\HtmlRenderer`, avec lequel `DefaultCompiler` produit le rendu UJO, est aussi un
service, construit par `HtmlRenderer::default()` ; `phil/ujo-bundle` enregistre le même. Le convertisseur lit avec
`DefaultCompiler` lui-même, quelle que soit la cible de `MarkupCompiler` : le compilateur propre d'une application
change le HTML, pas les analyseurs.
