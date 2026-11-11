<!-- phil-dev source-sha256=cb0bdca5135c6210f2cb989f590f797d1f5f1c7fd9d8fedb438de91955a4e644 -->
# Formulaires

`CoordinatesType` est une position en deux champs numériques, `latitude` et `longitude`, vers `Coordinates` et depuis
`Coordinates` ; il lit les décimales dans la locale de la requête, jusqu'à sept chiffres.

```php
$builder->add('position', CoordinatesType::class, ['required' => false]);
```

Les deux champs vides donnent aucune position, `null`. Un champ sans l'autre, ou une valeur hors de la Terre, rend le
champ invalide avec son `invalid_message`, *This value is not a valid position.*

Un `null` demande une propriété qui l'accepte. L'objet intégrable Doctrine contient toujours une position : lié à lui,
gardez le champ `'required' => true`. Une position facultative est plutôt stockée en deux colonnes nullables, comme le
montre [le document sur Doctrine](doctrine.md#une-position-facultative).
