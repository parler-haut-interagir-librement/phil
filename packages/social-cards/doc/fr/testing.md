<!-- phil-dev source-sha256=5bc8fbeb386f94a673c14a7e3573290419205d7e4f01aec45c122bd2a284fe13 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `ShareMetadata`, `ShareImage`, `Text` | Chaque champ et ses bornes, les textes mis sur une ligne et coupés, les adresses, les locales et les comptes refusés, `fromArray()` et ce qu'il refuse, les adresses résolues dans une page et lues par phil/url, les caractères de formatage invisibles retirés |
| `MetaTags`, `MetaTag` | Les balises exactes d'un article et d'une simple page, leur ordre, la carte de X, l'échappement |
| `Color`, `CardTemplate`, `CardFont`, `CardImage` | Les graphies d'une couleur, les valeurs par défaut et les bornes d'un gabarit, les polices lues et refusées, les images dans et hors de leurs bornes, l'empreinte |
| `LineBreaker`, `TextFitter`, `FittedText` | Les coupures de ligne en anglais, en français et en japonais, la dichotomie vérifiée par une recherche linéaire, les mots coupés, les points de suspension, les mesures comptées ; la coupe d'un titre français mesurée par GD dans la police de la boîte |
| `GdCardRenderer`, `GdTextMeasure` | Les marges, l'accent, l'image de fond, le logo, la place du sous-titre, la compression, et trois cartes entières comparées pixel par pixel avec les cartes qu'a dessinées GD 2.3.3 |
| `ImagickCardRenderer`, `ImagickTextMeasure` | Là où `ext-imagick` est installée, ignorés ailleurs : la taille et l'opacité de la carte, les mêmes octets deux fois, aucune date écrite, l'accent et le sous-titre, un titre en hébreu sur la marge de droite, l'image de fond et le logo, une image illisible, la mesure dans la police de la boîte |
| `TextDirection` | Latin, hébreu, arabe et devanagari, la première lettre forte après des chiffres et des marques, aucune lettre forte, la borne du balayage |
| `CardGenerator`, les stockages, `Fingerprint` | Chaque carte dessinée une fois, chaque partie de l'empreinte, les fichiers atomiques, les empreintes refusées |
| `TagReader`, `PageTags`, `LinkPreview`, `TagCheck` | Les balises d'une page, les bornes du lecteur, les pages qui ne sont pas du HTML, les jeux de caractères, les replis d'un aperçu, chaque constat |
| `LinkFinder` | Les liens dans l'ordre et une seule fois, la ponctuation qui les suit, les parenthèses et les crochets ouverts ou non, les autres schémas, les bornes du texte, d'un lien et du compte, l'UTF-8 invalide |

Les tests fournissent leur propre police, quelques kilo-octets dessinés pour eux, pour qu'ils dessinent les mêmes pixels
partout où GD est le même. Elle couvre l'ASCII imprimable et `é`. La coupe d'un titre par les mesures de GD a besoin des
points de suspension, et les titres français ont besoin des guillemets : `Tests\Support\BoxFont` écrit, pour les tests
qui en ont besoin, une police TrueType de boîtes — chaque caractère étant une boîte pleine de sa propre chasse — qui
couvre l'ASCII imprimable, `é`, `«`, `»` et `…`, les points de suspension étant plus larges que deux lettres, de sorte
que la dernière ligne d'un titre coupé perde une lettre pour leur faire place, et les lettres de l'hébreu, de sorte qu'un titre de droite à gauche soit dessiné par
ImageMagick, qui omet un caractère que sa police n'a pas.

À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/social-cards/tests
```

Les tests de `ImagickCardRenderer` ont besoin de `ext-imagick`, et ceux de la mise en forme d'ImageMagick compilé avec
libraqm. Là où aucune des deux n'est installée, un conteneur fournit les deux : les paquets `php-cli`, `php-pecl-
imagick` et `ImageMagick` de Fedora sont compilés avec libraqm. À la racine du monodépôt, avec l'image de ces paquets
et les extensions du paquet :

```sh
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/src/var/tmp -v "$PWD:/src" -w /src <image> \
    php vendor/bin/phpunit packages/social-cards/tests packages/social-cards-bundle/tests
```

## Bancs d'essai

`CardRendererBench` dessine un titre court, un long ajusté par dichotomie et un coupé à sa plus petite taille, simples
ou sur une image de fond avec un logo, par GD et, là où `ext-imagick` est installée, par ImageMagick. `TagReaderBench`
lit un petit en-tête, un en-tête de centaines de balises et une page de 512 Kio sans en-tête. À la racine du monodépôt :

```sh
composer bench -- packages/social-cards/tests/Benchmark
```

## Fuzzing

La cible `social-cards-tags` lit des pages que personne n'a écrites, puis en fait l'aperçu et la vérification, à partir
des germes de `tests/Fuzz/corpus/social-cards-tags` :

```sh
composer fuzz -- social-cards-tags
```

La cible `social-cards-links` trouve les liens de textes que personne n'a écrits, à partir des germes de
`tests/Fuzz/corpus/social-cards-links` : au plus les liens demandés, chacun une fois, chacun un morceau `http` ou
`https` du texte.

```sh
composer fuzz -- social-cards-links
```
