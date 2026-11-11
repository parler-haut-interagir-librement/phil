<!-- phil-dev source-sha256=d7d4d4ce436f7b1f421323256a28a4fbc66faa96b042c4e8ee32bedaa7f1158c -->
# Gabarits

Un gabarit est un article vierge sur lequel on imprime quelque chose, décrit sous forme de données. Un gabarit de
chandail a un axe de taille, un axe de couleur, peut-être un axe de version ; un gabarit d'affiche, un format et un
papier. Rien dans la bibliothèque ne connaît la différence.

## Libellés et pastilles

Un `Label` contient un texte par locale — `fr`, `en`, `fr_CA` —, au moins un, chacun de 1 à 255 caractères.
`in('fr_CA')` lit le texte de cette locale, sinon celui de sa langue, `fr`, sinon le premier texte donné ; `in()` lit le
premier.

Une `Swatch` est ce qu'un sélecteur montre pour une valeur : `Swatch::colour('#1A1A1A')`, six ou huit chiffres
hexadécimaux conservés en majuscules, ou `Swatch::photo('swatches/rainbow.jpg')` pour une valeur qu'aucune couleur
unique ne montre — un arc-en-ciel, un fini métallique.

## Axes et valeurs

```php
$colour = Axis::of('colour', Label::of(['en' => 'Colour', 'fr' => 'Couleur']),
    AxisValue::of('BK', Label::of(['en' => 'Black', 'fr' => 'Noir']), Swatch::colour('#1A1A1A'), 'black',
        ['front' => 'blanks/unisex/front-bk.jpg', 'back' => 'blanks/unisex/back-bk.jpg']),
    AxisValue::of('GL', Label::of(['en' => 'Glow', 'fr' => 'Phosphorescent']), Swatch::photo('swatches/glow.jpg')),
);
```

| Partie | Règle |
|---|---|
| Code d'axe | Jusqu'à 32 lettres minuscules, chiffres ou `_`, commençant par une lettre ; jamais `design`, `lang` ni `template` |
| Valeurs | De 1 à 500, dans l'ordre où un sélecteur les liste, chaque code une seule fois |
| Code de valeur | De 1 à 16 lettres, chiffres, `.`, `_` ou `-`, commençant par une lettre ou un chiffre : ce que l'UGS écrit |
| Nom du fournisseur | Le nom que donne à la valeur le fournisseur de l'article vierge de ce gabarit — `sport grey` —, de 1 à 255 caractères |
| Photos | La photo de l'article vierge dans cette valeur, par code de face, un chemin relatif au stockage d'images |
| Canaux | Les canaux qui vendent les produits de cette valeur ; tous les canaux quand la liste est vide |

Le nom du fournisseur appartient à la valeur d'un gabarit, et non à une couleur partagée : deux articles vierges de deux
fournisseurs nomment différemment la même couleur. Un gabarit fait pour un kiosque de congrès donne à sa version de
congrès le canal du kiosque ; le plan vend alors les produits de cette version par ce canal seulement.

Les chemins sont relatifs à l'endroit où l'application garde ses images : des segments de lettres, de chiffres, de `.`,
de `_` et de `-`, jamais `.` ni `..`, jamais absolus, d'au plus 512 caractères.

## Faces et zones d'impression

```php
$front = Face::of('front', Label::of(['en' => 'Front']),
    PrintZone::of('chest', 50, 60, 300, 400, 300, PhotoBox::of(312, 260, 400, 533)),
    PrintZone::of('pocket', 200, 60, 80, 80, 600),
);
```

Une `PrintZone` est un rectangle en millimètres à partir du coin supérieur gauche de sa face — position et taille,
chacune d'au plus 10 000 mm —, la résolution de son fichier d'impression, de 72 à 2400 ppp, 300 par défaut, et le
`PhotoBox` qu'elle couvre sur les photos de sa face, en pixels, pour les maquettes. Les photos d'une même face partagent
leur cadrage quelle que soit la valeur qu'elles montrent : le cadre est donc donné une seule fois. Une face a au moins
une zone ; sa première zone reçoit une illustration qui ne nomme aucune zone.

## Prix

```php
$pricing = Pricing::of($cad(2_500))
    ->withPrice('size', '2X', $cad(2_800))
    ->withPrice('size', '3X', $cad(3_000))
    ->withSupplement('colour', 'GL', $cad(300))
    ->withSupplement('version', 'L', $cad(1_000));
```

Le prix d'une combinaison est le prix de sa valeur sur l'axe de prix, sinon la base, plus le supplément de chacune de
ses valeurs. Les prix ne portent que sur un axe ; les suppléments, sur n'importe lequel. Chaque montant est dans la
devise de la base et n'est jamais négatif. Un rabais est une promotion du catalogue, pas un supplément négatif.

## Exclusions

```php
Exclusion::of(['colour' => ['PK', 'LB'], 'size' => ['4X', '5X']]);
```

Une exclusion retire chaque combinaison qui a, sur chaque axe qu'elle nomme, l'une des valeurs qu'elle liste : le rose
et le bleu pâle dans les deux plus grandes tailles, quelle que soit la version. Un gabarit en liste autant qu'en exige
la gamme du fournisseur ; le plan ne fabrique jamais les combinaisons exclues, et une vitrine ne les offre jamais.

## Le gabarit

```php
$shirt = Template::of('unisex', Label::of(['en' => 'Unisex shirt']), SkuPattern::parse('{design}{lang}{version}{size}{colour}'),
    $pricing, [$version, $size, $colour], [$front, $back], $exclusions, supplier: 'Brand 64000');
```

| Vérification | Refusé avec `InvalidDefinition` |
|---|---|
| Code | Pas formé de 1 à 32 lettres, chiffres, `.`, `_` ou `-`, commençant par une lettre ou un chiffre |
| Axes | Aucun, plus de 8, un axe deux fois, ou plus de 10 000 combinaisons avant exclusions |
| Faces | Une face deux fois, ou une valeur avec la photo d'une face que le gabarit n'a pas |
| Patron d'UGS | Un marqueur qui n'est ni un axe ni `design`, `lang` ou `template` |
| Prix, exclusions | Une valeur ou un axe que le gabarit n'a pas |

`combinations()` produit chaque combinaison qu'il fabrique, le dernier axe variant le plus vite ; `makes()` dit si une
combinaison est exclue.
