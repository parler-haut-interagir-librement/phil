<!-- phil-dev source-sha256=760d676b99fa5f523693b1e996b5f850bcdf6df3ce85ae067e3dbcf41839dacf -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/contact` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `ContactCard`, `Email`, `PhoneLine`, `PhoneRole` ; `VCard`, `SchemaOrg`, `ContactCardArray` ; `ContactException`,
  `InvalidContact` et `UnreadableContact`. `Email::of()` garde toute la partie locale quand mettre le domaine en
  minuscules change sa longueur en octets (`ada@İnfo.com`). `VCard` échappe un retour de chariot seul dans une valeur
  texte, si bien qu'une valeur ne peut pas commencer une nouvelle propriété vCard.
- `PersonName`, le nom d'une personne en ses parties — prénom, autres prénoms, particule, nom de famille, second nom de
  famille, titre de civilité, génération, titre professionnel, culture et ordre —, avec `display()`, `formal()`,
  `sortKey()` et `sortFamily()` ; `NameOrder` ; `NameConvention` et `NameConventions`, l'ordre et la clé de tri des noms
  de chaque culture, testés sur de vrais noms. Le `structuredName` de `ContactCard`, qui donne son nom à la fiche quand
  elle n'en a pas ; `N` dans `VCard`, avec `LANGUAGE`, `SORT-AS` et les composants de la RFC 9554 ; les parties du nom
  dans `SchemaOrg` ; la clé `structuredName` dans `ContactCardArray`, absente des fiches stockées sans elle.
- `JSContactCard`, `JSContactWriter`, `JSContactReader`, `JSContactNotice` et `UnreadableJSContact` : JSContact 1.0 (RFC
  9553) écrit et lu, les adresses postales comme valeurs de `phil/address`, les propriétés que la bibliothèque n'associe
  pas gardées et récrites, un avis pour ce qui est lu mais non gardé ; le lecteur strict et borné en octets, en
  profondeur et en entrées, avec une cible de fuzzing.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `doc/storage.md` dit comment chiffrer une
  fiche stockée avec `#[Encrypted]` et y chercher avec `#[BlindIndexed]` du composant KeyManagement de Symfony.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
