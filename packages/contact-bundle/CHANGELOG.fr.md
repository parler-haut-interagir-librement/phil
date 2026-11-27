<!-- phil-dev source-sha256=ce598f0c2ce48cd85cc68544f3e249e751b42f301cc291115dd53d75032d1465 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/contact-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `ContactCardType`, `EmailAddressType`, `PhoneLineType` et leurs transformateurs ; le thème de formulaire
  `@PhilContact/form/theme.html.twig`, qui donne au contrôleur de collection l'index après la plus haute clé, si bien
  qu'une entrée ajoutée après une soumission échouée ne prend jamais la clé d'une entrée gardée ; le contrôleur Stimulus
  `collection`.
- `PersonNameType` et `PersonNameTransformer`, un nom de personne en ses parties avec les jetons `autocomplete` de ses
  champs, et les options `structured_name` et `culture` de `ContactCardType`, le nom structuré donnant à la fiche le nom
  qui lui manque.
- `ContactCardColumnType`, qui garde le nom structuré dans la même colonne ; `VCardResponse` et `JSContactResponse`, qui
  changent une barre oblique ou une barre oblique inverse du nom de fichier en `_` ; `ContactExtension`, dont
  `contact_json_ld` ajoute la fiche au graphe de la page de phil/structured-data-bundle quand ce bundle est installé, et
  affiche sinon son propre élément script, et dont `person_name` écrit un nom affiché, formel ou trié.
- Le service `JSContactReader` de phil/contact, ses bornes et sa région sous `phil_contact.jscontact`.
- Les libellés et les messages en anglais et en français, dans `translations/`.
- `PhilContactBundle`, alias d'extension `phil_contact`, et sa configuration.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
