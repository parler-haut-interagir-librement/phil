<!-- phil-dev source-sha256=1a1e4bcc53f43f40139f9915963e73ec2402868a1125d8c2aaa246affed78ce6 -->
# Markdown et HTML

Le Markdown et le HTML sont lus par les lecteurs de `phil/markup`, qui écrivent les mêmes blocs UJO que le reste de
PHIL.

## Choisir les lecteurs

Par défaut, le Markdown est lu par `MarkdownReader` (CommonMark, les extensions de GitHub, les notes de bas de page,
les attributs, l'en-tête de métadonnées et les autres extensions que liste sa documentation), avec le HTML brut
échappé, et le HTML par `HtmlReader`. Tout `Phil\Markup\Reader\MarkupReader` peut être donné à la place :

```php
use Phil\DocumentImport\DocumentImporter;
use Phil\Markup\Reader\Markdown\MarkdownReader;

$importer = new DocumentImporter(markdown: new MarkdownReader(rawHtml: true));
$importer = new DocumentImporter(markdown: new MarkdownReader(dialect: $dialect));   // a dialect of an add-on
```

Le document est celui du lecteur, normalisé par lui ; les métadonnées sont son `Reading::$metadata`. Les images du
Markdown gardent leurs adresses : l'importateur ne les récupère pas.

## Les images d'un collage HTML

Un traitement de texte qui copie dans le presse-papiers écrit ses images dans des adresses `data:`. Avant la lecture du
HTML, chaque image d'une adresse `data:`, en Base64 ou encodée en pourcentage, devient un `Media` nommé `image-1.png`,
`image-2.jpg`…, remis au magasin de médias, et l'image pointe vers l'adresse que répond le magasin. Une image qu'un
navigateur n'affiche pas est signalée et laissée au lecteur, qui abandonne les adresses `data:`. Les images aux
adresses `http` ou `https` les gardent.

## Le rapport

Les éléments que le lecteur HTML laisse de côté avec leur contenu sont signalés par leur nom, comme
`Omitted::Element` : `script`, `style`, `iframe`, `frame`, `frameset`, `object`, `embed`, `applet`, `template`,
`noscript`, `form`, `input`, `button`, `select`, `textarea`, `svg`, `math`, `audio`, `video`, `canvas`. Une
importation Markdown a un rapport vide : le lecteur garde chaque construction, comme bloc ou comme texte.
