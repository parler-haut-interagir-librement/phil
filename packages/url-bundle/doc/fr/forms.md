<!-- phil-dev source-sha256=a554e496522b952a342fc1e61d2f07f954e66c09c91dad86681b0bc202eeca77 -->
# Formulaires

`TypedUrlType` est un seul champ texte, qui va vers un `TypedUrl` et en revient : le genre de l'adresse est détecté
parmi les `types` qu'il accepte.

```php
$builder->add('social', TypedUrlType::class, ['types' => [KnownUrlType::Facebook, KnownUrlType::Instagram]]);
```

- Il étend le `UrlType` de Symfony, avec `https` comme protocole par défaut : `instagram.com/shop` est lu comme
  `https://instagram.com/shop`.
- Un champ vide vaut `null` ; une adresse d'aucun genre accepté rend le champ invalide avec *This address is not one
  of the accepted kinds.*
