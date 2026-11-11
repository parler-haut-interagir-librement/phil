<!-- phil-dev source-sha256=85a99d7ac4366cd7a0e9926cf48c25ddfec377544e0d46ef2e29e00482874cd6 -->
# Points d'extension

Ce qu'une application configure ou étend. Les numéros sont ceux de `phil/phone`, lus avec ses plans de numérotation
importés.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `default_region` | Le pays dans lequel un numéro saisi sans indicatif de pays est lu, par le champ, la contrainte et les filtres — voyez [Installation](installation.md) |

## Formulaires et validation

| Point | Comment |
|---|---|
| Le pays d'un champ | L'option `region` de `PhoneType` |
| Les numéros acceptés | Les `types` et les `regions` de `ValidPhoneNumber`, et son `region` pour un texte |
| Les messages | Les `message`, `typeMessage` et `regionMessage` de la contrainte, et ses codes `INVALID_ERROR`, `TYPE_ERROR`, `REGION_ERROR` |
| Plus que ce que fait le champ | Une extension de type de formulaire de l'application sur `PhoneType` |

Voyez [Formulaires et validation](forms-and-validation.md).

## Stockage et Twig

| Point | Comment |
|---|---|
| Où un numéro est gardé | Le type de colonne `phil_phone`, E.164 avec son poste — voyez [Doctrine](doctrine.md) |
| Comment un numéro est affiché | Le filtre `phone` avec un format, et `phone_link` pour une adresse `tel:` — voyez [Twig](twig.md) |
