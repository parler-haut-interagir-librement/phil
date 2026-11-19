<!-- phil-dev source-sha256=dc0d9ce050ab7a1fae922b43810c74907a0cafdcedf66ddad843c640d85b713d -->
# PDF

Un PDF décrit des pages, pas un document : il place des glyphes à des positions et dessine des images, et ne dit rien
des paragraphes, des titres ou des listes, à moins d'être balisé. La bibliothèque lit elle-même le texte et la structure
simple que montrent les pages, sans aucun programme externe, et signale ce qu'elle laisse de côté.

```php
$imported = new DocumentImporter()->importFile('rules.pdf');
```

## Ce qui est lu

| Partie du fichier | Comment |
|---|---|
| Objets | Par les tables et les flux de références croisées, la section la plus récente d'abord, `Prev` et les fichiers hybrides suivis ; les objets des flux d'objets ; un fichier dont les références croisées manquent ou sont endommagées est parcouru pour trouver ses objets |
| Flux | Flate avec ses prédicteurs PNG et TIFF, LZW, hexadécimal ASCII, base 85 ASCII et codage par plages ; un flux Flate endommagé donne ce qu'il contient avant son dommage |
| Pages | L'arbre des pages dans l'ordre, chaque page avec les ressources et la boîte de média qu'elle a ou dont elle hérite ; ses flux de contenu joints |
| Texte | L'état du texte suivi à travers `BT`, `Tf`, `Td`, `TD`, `Tm`, `T*`, `Tc`, `Tw`, `Tz`, `TL`, `Ts`, `Tj`, `TJ`, `'` et `"`, avec la matrice graphique de `q`, `Q` et `cm` ; les XObjects de formulaire parcourus avec leurs propres ressources et leur propre matrice |
| Caractères | D'abord la CMap `ToUnicode` d'une police ; puis, pour une police simple, ses `Differences` par nom de glyphe et son encodage (WinAnsi, MacRoman, Standard) ; le code d'une police composite sans CMap lu comme Unicode |
| Largeurs | `Widths` et `FirstChar` d'une police simple, `W` et `DW` d'une police composite : où chaque chaîne se termine, pour que la chaîne suivante de la ligne la suive ou qu'une espace vienne entre elles |
| Gras et italique | Le nom de la police (`Bold`, `Black`, `Heavy`, `Semibold`, `Demi`, `Italic`, `Oblique`) et les indicateurs et la graisse de son descripteur |
| Liens | Les annotations de lien vers une adresse `http`, `https` ou `mailto`, posées sur le texte dont elles couvrent le milieu |
| Métadonnées | Les informations du document : `title`, `creator` (son auteur), `subject`, `keywords`, `created` et `modified` en ISO 8601 ; la `language` du catalogue. Les chaînes de texte en UTF-16 ou en UTF-8 après leur marque d'ordre des octets, les autres lues comme du Windows-1252 |

## Des lignes aux blocs

Les chaînes d'une page sont rassemblées en lignes d'après leur ligne de base. Une chaîne relevée et plus petite sur une
ligne est un exposant. Puis les lignes deviennent des blocs d'après leurs tailles et leurs positions seulement :

- la taille qu'ont la plupart des caractères est le corps du texte ;
- une taille plus grande qu'ont peu de caractères (au plus le quart du corps du texte) fait des titres, la plus grande
  d'abord, jusqu'à six niveaux ; une ligne courte seule, tout en gras, sans ponctuation finale, est un titre sous eux ;
- une ligne qui commence par une puce (`•`, `◦`, `▪`, `-`, `–`, `*`…) ou par un nombre ou une lettre suivis de `.` ou de
  `)` est un élément de liste ; l'indentation d'un élément l'imbrique ; le passage des puces aux nombres commence une
  autre liste ;
- des lignes d'une même taille rapprochées forment un paragraphe ; un paragraphe se poursuit d'une page à l'autre quand
  sa dernière ligne ne termine pas une phrase et que la page suivante commence en minuscule ; un mot coupé par un trait
  d'union en fin de ligne est rejoint ;
- le gras, l'italique, les exposants et les liens sont gardés dans le texte.

Une ligne qui revient près du haut ou du bas de la plupart des pages (un en-tête courant, un pied de page, un numéro de
page, ses chiffres mis à part) est laissée de côté et signalée une seule fois. Le texte des artefacts de contenu marqué,
que les PDF balisés emploient dans le même but, est lui aussi laissé de côté.

## Images

Une image JPEG est tirée telle quelle, et les échantillons gris ou RVB de 8 bits sont écrits en fichier PNG ; une image
dessinée de nouveau (un logo sur chaque page) est tirée une seule fois, et une image de moins de 8 pixels (un filet, un
point) est laissée telle quelle. Les autres images (JPEG 2000, télécopies CCITT et JBIG2, couleurs CMJN et indexées,
images en ligne) sont signalées.

## Laissé de côté et refusé

Les champs de formulaire, les tampons, les pièces jointes et les autres annotations, les commentaires et les annotations
de balisage, les liens vers des endroits du document, les en-têtes courants et les images ci-dessus sont signalés. Un
PDF chiffré, même s'il s'ouvre sans mot de passe, est refusé par `UnsupportedFormat` : enregistrez-le de nouveau sans
restrictions. Un fichier qui n'est pas un PDF est refusé par `InvalidDocument`.

Une page numérisée contient une image de son texte, pas du texte : elle ne donne aucun paragraphe. La bibliothèque ne
fait aucune reconnaissance optique de caractères. Les tableaux, les colonnes côte à côte et les notes de bas de page
n'ont aucune marque dans un PDF non balisé et ressortent comme des paragraphes ; pour une importation fidèle, le
document à partir duquel le PDF a été fait est la meilleure source.

## Limites

Le fichier n'est pas plus grand que `$totalBytes` ; chaque flux une fois décodé n'est pas plus grand que `$entryBytes`,
et tous ensemble pas plus grands que `$totalBytes` ; les tableaux, les dictionnaires et l'arbre des pages ne
s'imbriquent pas plus profondément que `$depth`, les XObjects de formulaire pas plus profondément que 32 ; le fichier ne
déclare pas plus de `$objects` objets ; la lecture de ses pages ne prend pas plus de `$operations` opérations (chaque
opérateur de contenu, ceux d'un formulaire chaque fois qu'il est dessiné, et chaque suite de texte contre laquelle une
annotation de lien est vérifiée). Une boucle dans l'arbre des pages ou dans les références prend fin ; rien du
fichier n'est jamais exécuté.
