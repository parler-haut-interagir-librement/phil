<!-- phil-dev source-sha256=f9128daf210d31225eb149b073c1f0a6625792c955d73f5e311fc2060afc4014 -->
# phil/typography

**De vrais guillemets, des tirets, les espaces du français et des traits d'union conditionnels dans votre HTML, dans
toutes les langues, sans toucher au balisage.**

phil/typography corrige la microtypographie du texte. Les guillemets droits deviennent les guillemets de la langue,
`--` et les traits d'union entourés d'espaces deviennent des tirets, trois points des points de suspension,
`1920 x 1080` une multiplication, `(tm)` un signe de marque de commerce. Un nombre et son unité sont liés par une
espace insécable. Le français reçoit ses espaces avant `: ; ! ?` et à l'intérieur des `« »`, selon les règles de la
France ou du Canada. Les mots longs reçoivent des traits d'union conditionnels là où les motifs de césure de la langue
permettent une coupure. Il travaille sur du HTML analysé comme du HTML5 et ne réécrit que le texte : les éléments, les
attributs et les commentaires reviennent tels qu'ils étaient, et le texte de `pre`, `code`, `kbd`, `script`, `style`
et `textarea` n'est jamais changé.

Il s'adresse à toute application PHP qui affiche du texte écrit par des gens qui tapent au clavier : un CMS, un blogue,
un forum, une boutique, un outil qui met en page des livres ou imprime des PDF. Il s'exécute sur le HTML qu'a produit un
gabarit ou un convertisseur Markdown, ou sur du texte brut. Un préréglage donne à chaque locale les correcteurs dont
elle a besoin, dans le bon ordre : `Typographer::for('fr_CA')` est donc tout ce qu'écrivent la plupart des
applications. Dans Symfony, `phil/typography-bundle` ajoute les filtres Twig et corrige le HTML des documents UJO.

C'est une bibliothèque PHP simple, sur le modèle de JoliTypo, sans autre dépendance que les extensions `dom`, `intl`,
`libxml` et `mbstring`. La césure suit l'algorithme de Liang, celui qu'utilise TeX, implémenté en PHP. Les motifs sont
importés de hyph-utf8 pour les 69 langues dont la licence les permet dans un paquet MIT, chacune consignée dans
`data/LICENSES`. Ils sont générés pour le tchèque, le hongrois, l'arménien, l'indonésien, le letton, le macédonien et le
roumain, à partir des règles orthographiques de chaque langue ; ceux de l'arménien et du macédonien sont expérimentaux
et servis seulement sur demande. Le thaï, qui coupe les lignes sans trait d'union, reçoit des espaces de largeur nulle
entre ses mots, tirées du dictionnaire ICU de `ext-intl`.

La conception est petite : un `Fixer` réécrit le texte d'un `Run`, les nœuds texte d'un bloc ; `Typographer` parcourt
le DOM et exécute ses correcteurs dans l'ordre ; `Preset` les choisit pour une `Locale` ; `Hyphenator` coupe les mots
avec les `Patterns` que charge un `Dictionary`.

## État

| Partie | État |
|---|---|
| `Typographer` : fragments HTML, documents entiers et texte brut ; éléments protégés, configurables | Implémenté et testé |
| Correcteurs : normalisation Unicode, points de suspension, dimension, tiret, guillemets typographiques, apostrophe, virgule, espacement du français, signes juridiques, unités, césure, coupures de mots du thaï | Implémenté et testé |
| Guillemets de 66 langues et régions, d'après le CLDR d'Unicode | Implémenté et testé |
| Espacement du français selon les règles de la France et du Canada | Implémenté et testé |
| Motifs de césure de 69 langues tirés de hyph-utf8, avec leurs exceptions et leurs longueurs minimales | Implémenté et testé |
| Motifs de césure du tchèque, du hongrois, de l'arménien, de l'indonésien, du letton, du macédonien et du roumain, écrits à partir des règles de chaque langue : des règles, pas un dictionnaire, moins complètes que les motifs TeX ; arménien et macédonien expérimentaux, désactivés par défaut | Implémenté et testé |
| Thaï : espaces de largeur nulle entre les mots, tirées du dictionnaire ICU | Implémenté et testé |
| Préréglages : les correcteurs de chaque locale | Implémenté et testé |
| Émojis : chaque séquence laissée octet pour octet | Implémenté et testé |
| Césure du zoulou | Écartée : aucun motif dans hyph-utf8 |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [HTML et texte](doc/fr/html.md) | Comment le HTML est lu et réécrit, les suites de texte, les éléments protégés, les émojis. |
| [Correcteurs](doc/fr/fixers.md) | Chaque correcteur, ce qu'il change et ce qu'il laisse tel quel. |
| [Locales](doc/fr/locales.md) | Les préréglages, les guillemets, les styles de tiret et l'espacement du français par locale. |
| [Césure](doc/fr/hyphenation.md) | L'algorithme de Liang, les langues et leurs licences, et vos propres motifs. |
| [Exceptions](doc/fr/exceptions.md) | `TypographyException` et ses trois exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui corrige du texte. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/typography
```

```php
use Phil\Typography\Typographer;

echo Typographer::for('fr_FR')->fix('<p>"Bonjour" , dit-il : l\'été arrive !</p>');
// <p>«&nbsp;Bon­jour&nbsp;», dit-il&nbsp;: l’été ar­rive !</p>  (narrow no-break space before "!", soft hyphens)

echo Typographer::for('en_US', hyphenation: false)->fixText('"Wait..." - she said, 1914-1918.');
// “Wait…”—she said, 1914–1918.
```

## Prérequis

PHP 8.5 ou plus récent, avec `ext-dom`, `ext-intl`, `ext-libxml` et `ext-mbstring`. Rien d'autre.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE). Les motifs de césure importés sous `data/hyphenation/` gardent leurs propres licences, données
pour chaque langue dans [data/LICENSES](data/LICENSES) ; ceux qui ont été écrits pour cette bibliothèque sont sous sa
licence MIT.
