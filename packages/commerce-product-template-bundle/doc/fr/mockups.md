<!-- phil-dev source-sha256=2e47e47da61e8200c91bcf7cefa12c20d2f42c96d9fb91cf92e2e58f267901e2 -->
# Maquettes

`Mockups::render('NAQ001', 'unisex')` compose les maquettes d'un groupe avec `phil/print-compositor`, une fois le groupe
généré et enregistré par `flush`.

## Ce qui est composé

Pour chaque membre actif et chaque face du gabarit :

- la **photo** est celle de la première valeur du membre, dans l'ordre des axes, qui a une photo de cette face — celle
  de la couleur, pour un t-shirt ;
- les **illustrations** sont celles que le plan place sur cette face et dont la zone a une `PhotoBox` ;
- une face sans photo, ou sans une telle illustration, n'a pas de maquette.

Les illustrations sont placées dans leurs boîtes sur la photo avec l'ombrage du tissu de `mockups.shading`, puis
encodées en WebP à la qualité `mockups.quality`. Le membre garde le chemin de chaque maquette par code de face.

## Les noms et les nouvelles exécutions

Une maquette est nommée d'après une empreinte de ce qui la compose — les octets de la photo et de chaque illustration,
les boîtes, l'ombrage et la qualité —, sous `mockups.prefix` : `mockups/3f9c….webp`. Ainsi :

- les tailles d'une couleur partagent un seul fichier, composé une seule fois ;
- une nouvelle exécution ne compose que ce qui manque au magasin : une nouvelle couleur, une photo ou une illustration
  remplacée ;
- une maquette n'est jamais écrasée par un autre contenu : une page mise en cache avec un ancien chemin montre donc
  toujours une image cohérente.

`render()` retourne le nombre de maquettes qu'il a composées. Chaque image est lue une seule fois par exécution.

## Le magasin d'images

`ImageStore` lit les photos et les illustrations et écrit les maquettes par chemins relatifs au magasin. Avec
`images_directory` configuré, `DirectoryImageStore` les garde dans ce répertoire : un chemin qui pourrait en sortir est
refusé, et une maquette est écrite dans un fichier temporaire puis renommée, pour qu'un lecteur n'en voie jamais la
moitié. Une application qui garde ses images ailleurs implémente `ImageStore` et y fait pointer un alias
([Points d'extension](extension-points.md)).

## Ce qui est refusé

`TemplateRefused::missingImage()` pour une photo ou une illustration absente du magasin,
`TemplateRefused::mockup()` pour une image que le compositeur refuse — pas une image, une boîte qui dépasse la photo —,
et les refus de la génération pour un groupe, un gabarit ou un motif inconnu ou un plan refusé.

La composition s'exécute en PHP sur les pixels de chaque zone : exécutez-la dans un *worker*, après la génération.
