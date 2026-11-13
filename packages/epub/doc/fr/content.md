<!-- phil-dev source-sha256=ee07ec61f35c14bee97df28e6a80155beb45f1945e4b0e093d642298c6380d46 -->
# Documents de contenu

Le HTML de chaque chapitre devient un document de contenu XHTML : un document XML dans l'espace de noms XHTML, avec
l'espace de noms EPUB déclaré pour `epub:type`, un `<title>`, les feuilles de style du livre et le corps converti.

## Du HTML au XHTML

Le fragment est analysé par l'analyseur HTML5 de PHP (`Dom\HTMLDocument`), exactement comme le lit un navigateur
(éléments non fermés fermés, tableaux complétés, balises mal imbriquées réparées), puis réécrit en XML :

- les éléments dans leur espace de noms : le HTML en XHTML, `<svg>` avec les espaces de noms SVG et XLink déclarés,
  `<math>` avec l'espace de noms MathML ; les éléments vides fermés (`<br/>`) ; les éléments SVG et MathML vides
  fermés ;
- le texte et les valeurs d'attributs échappés, les caractères qu'interdit XML retirés ;
- `epub:type`, `xml:lang` et `xml:space` gardés ; les autres attributs préfixés, les attributs dont XML refuse le nom
  et les déclarations d'espaces de noms de la source retirés ;
- les éléments dont XML refuse le nom remplacés par leur contenu.

Ce qu'un document de contenu ne devrait pas contenir est laissé de côté : les éléments `script`, `noscript`,
`template`, `style`, `link`, `meta`, `title`, `base`, `object` et `embed`, les attributs de gestionnaires d'événements
`on…`, `srcset` et `sizes`. Un identifiant en double est retiré après sa première utilisation, et un identifiant vide
est retiré.

L'entrée doit être du HTML purifié, comme la sortie d'un assainisseur HTML ou de `phil/ujo`. La conversion retire ce
qui rendrait le document invalide ou scripté ; ce n'est pas un assainisseur de HTML non fiable.

Le manifeste déclare `svg`, `mathml` et `remote-resources` sur chaque document qui en contient.

## Liens

Chaque `href` de `<a>` et d'`<area>`, et de `<a>` en SVG, est vérifié :

| Adresse | Écrite |
|---|---|
| `#id` du même chapitre | telle quelle |
| `#id` d'un autre chapitre | le chemin vers ce chapitre et le fragment ; le premier chapitre qui contient l'identifiant l'emporte |
| un chemin relatif vers un chapitre, avec ou sans fragment | le chemin relatif au chapitre courant ; un fragment que le chapitre ne contient pas est retiré, le lien est gardé |
| un chemin relatif vers une ressource (une image, un fichier audio ou vidéo, une piste de texte) | le chemin relatif au chapitre courant |
| `http:`, `https:`, `mailto:`, `tel:` | telle quelle |
| toute autre chose : `/site/path`, `//host`, `javascript:`, `data:`, un chemin inconnu, un fragment inconnu | retirée ; le texte reste |

Les chaînes de requête des chemins relatifs sont retirées. Les chemins et les fragments encodés en pourcentage sont
décodés avant d'être comparés.

## Ressources

Chaque `src` de `<img>`, `<audio>`, `<video>`, `<source>`, `<track>` et `<iframe>`, le `poster` de `<video>` et le
`href` d'une `<image>` SVG sont vérifiés contre les ressources du livre :

| Adresse | Écrite |
|---|---|
| un chemin relatif vers une ressource (une image, un fichier audio ou vidéo, une piste de texte) | le chemin relatif au chapitre courant |
| une adresse `http:` ou `https:` sur de l'audio ou de la vidéo | telle quelle ; le document est marqué `remote-resources` |
| toute autre chose | l'élément est laissé de côté (une image laisse son texte de remplacement), une affiche (`poster`) est retirée |

EPUB ne permet ni les images distantes ni les fichiers absents du paquet : les retirer garde le livre valide.

## Associer des adresses

Le HTML d'une application désigne ses propres adresses : `/media/42/map.png`, `/rules/combat`. `Book::withUrls()`
associe chaque adresse avant les vérifications ci-dessus ; la fonction retourne l'adresse à utiliser (un chemin
relatif au chapitre, ou une URL absolue), ou null pour la retirer :

```php
$book = $book
    ->withAsset(Asset::image('images/map.png', $storage->read('42/map.png')))
    ->withUrls(static fn (string $url): ?string => match (true) {
        str_starts_with($url, '/media/42/') => 'images/'.basename($url),
        str_starts_with($url, '/') => 'https://example.com'.$url,
        default => $url,
    });
```

## Découper un fragment en chapitres

`HtmlSplitter::split($html, $level)` coupe un fragment à ses titres de premier niveau de niveau `$level` ou plus élevé
(`<h1>` seul par défaut). Ce qui précède le premier titre forme une partie à lui seul, sauf s'il n'est fait que de
blancs ; les titres imbriqués dans d'autres éléments ne coupent pas.

```php
$chapters = array_map(
    static fn (string $part): Chapter => new Chapter($part),
    HtmlSplitter::split($articleHtml, 2),
);
```
