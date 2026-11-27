<!-- phil-dev source-sha256=1e76817d33c7a1c0637a932cea236469411822093211687f8163d08385708cc5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/phone-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

### Sécurité

- `PhoneNumberTransformer` refusait un numéro d'aucun plan de numérotation avec un message qui contenait le numéro, et
  transmettait les messages de `phil/phone` qui contenaient le texte tapé : un message qui atteignait un journal y
  gardait des données personnelles. Ils ne nomment que la raison.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PhilPhoneBundle`, alias d'extension `phil_phone`, avec la clé `default_region`.
- `PhoneNumberColumnType` : `phil_phone` est un `VARCHAR(33)`, `PhoneNumberColumnType::LENGTH` : le plus long numéro
  qu'accepte `PhoneNumber::parse()`, avec un indicatif de pays de trois chiffres, dix-sept chiffres nationaux et un
  poste de sept chiffres, y tient.
- `PhoneType` et `PhoneNumberTransformer` ; le `maxlength` du champ est `PhoneNumber::MAX_INPUT`.
- `ValidPhoneNumber` et son validateur, qui demande une seule fois son type à un numéro, ce qui règle à la fois sa
  validité et les types acceptés.
- `PhoneExtension`.
- Les messages en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
