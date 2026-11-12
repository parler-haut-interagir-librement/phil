<!-- phil-dev source-sha256=25b68588b66641c250f98f81be91ff19fb89986084433e020988203213928d00 -->
# Déclarer

## Les parties

Une œuvre est faite de parties dont la fabrication se déclare à part : `Part::Text`, `Part::Image`, `Part::Audio`,
`Part::Video` et `Part::Code`. Un livre a un texte et des images ; un jeu, des images, de l'audio et du code ; une
photo, une image.

`CreationDisclosure::of()` commence une œuvre avec ses parties, aucune déclarée :

```php
$work = CreationDisclosure::of(Part::Text, Part::Image);

$work->parts();      // [Part::Text, Part::Image]
$work->undeclared(); // [Part::Text, Part::Image]
```

Les parties sont toujours listées dans l'ordre de `Part`, quel que soit l'ordre donné, et chacune une fois.

## Les déclarations

`declare()` donne à une partie son type de source numérique et rend une nouvelle déclaration ; la déclaration
elle-même ne change jamais. Déclarer une partie que l'œuvre n'avait pas l'ajoute. `undeclare()` remet une partie à
« non déclarée ».

```php
$work = $work
    ->declare(Part::Text, DigitalSourceType::HumanEdits)
    ->declare(Part::Image, DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);

$work->sourceOf(Part::Image); // DigitalSourceType::CompositeWithTrainedAlgorithmicMedia
$work->sourceOf(Part::Code);  // null: not a part of the work
$work->has(Part::Code);       // false
```

Un type retiré, comme `digitalArt`, est déclaré comme le type qui le remplace, `digitalCreation`. Le
[vocabulaire](vocabulary.md) liste les types.

## Les vues

Chaque type dit ce qu'il doit à l'IA générative, un `GenerativeAi` :

| Vue | Signifie |
|---|---|
| `None` | Rien n'a été généré : capté, numérisé, fait ou retouché par des personnes, ou calculé par de simples algorithmes. |
| `Undeclared` | Rien ne dit comment c'est fait. |
| `Possible` | Peut contenir des éléments générés : modifié par des algorithmes, ou composé d'éléments d'origine non dite. |
| `Partial` | Une part a été générée : retouché par l'IA générative, ou composé avec des éléments générés. |
| `Full` | Tout a été créé par l'IA générative. |

`generativeAiOf()` lit la vue d'une partie ; une partie non déclarée, ou que l'œuvre n'a pas, est `Undeclared`.
`generativeAi()` combine les parties en la vue de l'œuvre entière :

- `Full` quand chaque partie est `Full` ;
- sinon `Partial` dès qu'une partie est `Full` ou `Partial` ;
- sinon `Possible` dès qu'une partie l'est ;
- sinon `Undeclared` dès qu'une partie l'est ;
- sinon `None`.

Une œuvre sans partie est `Undeclared`. `GenerativeAi::combine()` fait de même sur n'importe quelle liste de vues, pour
une collection ou un lot d'œuvres.

### La vue « sans IA »

`isFreeOfGenerativeAi()` vaut `true` quand l'œuvre entière est `None` : chaque partie déclarée, et aucune générée, en
partie ou peut-être. Une partie non déclarée l'en écarte, pour qu'un filtre « sans IA » ne montre jamais une œuvre que
personne n'a décrite.

```php
$work->isFreeOfGenerativeAi(); // false: the images were edited with generative AI
```

## Conserver une déclaration

`toArray()` donne le code de chaque partie, `null` quand elle n'est pas déclarée, prêt pour une colonne de type `json`
ou une API :

```php
$work->toArray(); // ['text' => 'humanEdits', 'image' => 'compositeWithTrainedAlgorithmicMedia']
```

`fromArray()` la relit. Une valeur peut aussi être l'adresse d'un type, comme XMP la porte. Une clé qui n'est pas une
partie, une valeur qui n'est ni un code, ni une adresse, ni `null`, lance `Exception\InvalidDisclosure` ; un code retiré
gardé est relu tel quel, pour qu'une vieille déclaration continue de dire ce qu'elle disait.

## Métadonnées

`DigitalSourceType::uri()` est l'adresse d'un type, `http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture`, la
valeur de la propriété XMP `DigitalSourceType::XMP_PROPERTY`, `Iptc4xmpExt:DigitalSourceType`, et d'une assertion C2PA.
`fromUri()` lit une adresse en `http` ou `https`, avec ou sans barre oblique finale.
