<!-- phil-dev source-sha256=c9f0060c73d8805a10cbd2d050d71c6bb7f6a4a99561579b1304cc7e2cda6f0e -->
# Points d'extension

Ce qu'une application ajoute ou étend. Les adresses et les genres sont ceux de `phil/url`.

## Les genres propres à l'application

Une enum de l'application qui implémente `Phil\Url\UrlType` est acceptée partout où des genres sont nommés : dans les
`types` de `TypedUrlType` et de `UrlOfType`, comme classe d'enum, cas, `EnumSet` ou liste de ceux-ci. Énumérés avant
`KnownUrlType`, ses genres l'emportent sur les genres internationaux à la détection ; voir
[Genres acceptés](kinds.md).

## Formulaires et validation

| Point | Comment |
|---|---|
| Les genres qu'un champ détecte | L'option `types` de `TypedUrlType` ; voir [Formulaires](forms.md) |
| Les genres acceptés | Les `types` de `UrlOfType` ; ses `message` et `typeMessage` ; voir [Validation](validation.md) |
| Plus que ce que fait le champ | Une extension de type de formulaire de l'application sur `TypedUrlType` |

## Stockage et Twig

| Point | Comment |
|---|---|
| Où une adresse est gardée | Le type de colonne `phil_url`, et le genre dans une colonne de l'application ; voir [Doctrine](doctrine.md) |
| Si le type de colonne est enregistré | La clé `column_type` : `null` quand DBAL est installé, `true` l'exige, `false` jamais ; voir [Installation](installation.md#column_type) |
| Comment une adresse est affichée | `url_display`, `url_type()` et `url_type_label` ; voir [Twig](twig.md) |
