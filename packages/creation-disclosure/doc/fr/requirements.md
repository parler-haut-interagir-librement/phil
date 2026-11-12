<!-- phil-dev source-sha256=b6c75d951218964acd0dd058a62870ba6aed25ca83fb3d6ddfb87c11b7447f15 -->
# Exigences

Un concours, une boutique ou un catalogue décide de ce qu'il demande aux œuvres qu'il reçoit : quelles parties doivent
être déclarées et, quand il en fixe un, le plus d'IA générative que chacune peut contenir. `DisclosureRequirement`
tient cette décision.

```php
use Phil\CreationDisclosure\DisclosureRequirement;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;

// An illustration contest without generative AI.
$contest = DisclosureRequirement::of([Part::Image], GenerativeAi::None);

// A shop that only asks its sellers to declare their text and images.
$shop = DisclosureRequirement::of([Part::Text, Part::Image]);
```

`of()` prend les parties, au moins une, gardées dans l'ordre de `Part` et chacune une fois ; il lance
`Exception\InvalidDisclosure` sans aucune. Le plafond est facultatif : sans lui, toute déclaration passe, pourvu qu'il
y en ait une.

## Vérifier une œuvre

`unmet()` liste les parties exigées qu'une déclaration laisse non déclarées, ou déclare au-dessus du plafond, dans
l'ordre de `Part` ; `isMetBy()` vaut `true` quand il n'y en a aucune.

```php
$contest->unmet($entry);   // [Part::Image] when the image is undeclared or holds generated content
$shop->isMetBy($product);  // true once the text and the images are declared
```

Les vues sont ordonnées `None`, `Undeclared`, `Possible`, `Partial`, `Full` ; `GenerativeAi::isAtMost()` les compare.
Une partie non déclarée ne satisfait jamais une exigence, quel que soit son plafond : un concours à `Undeclared` demande
quand même une déclaration. Les parties que l'exigence ne nomme pas ne sont pas regardées.

Un formulaire montre chaque partie manquante à côté de son champ ; l'application décide si une œuvre qui échoue est
refusée ou mise en attente de révision.
