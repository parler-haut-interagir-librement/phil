<!-- phil-dev source-sha256=d1c33bf6bda0b4b5d6882e7483b1518e7ade4f86c512d1e4bf7922ef79f5a47d -->
# Stockage

`TemplateArray` et `DesignArray` écrivent un gabarit et un visuel sous forme de tableaux de scalaires, pour une colonne
JSON ou un fichier, et les relisent :

```php
$json = json_encode(TemplateArray::toArray($template), JSON_THROW_ON_ERROR);
$template = TemplateArray::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
```

## Les tableaux

Chaque tableau a une `version`, 1 (`TemplateArray::VERSION`, `DesignArray::VERSION`). Un gabarit contient son `code`,
son `name` par locale, `sku_pattern`, `supplier`, `pricing` — sa `currency`, sa `base` et ses `prices` et `supplements`
en unités mineures par axe puis par valeur —, ses `axes` avec leurs valeurs, ses `faces` avec leurs zones en millimètres
et leur `photo_box`, et ses `exclusions`. Un visuel contient son `code`, son `name`, ses `languages`, ses `artworks`,
ses `templates` et ses valeurs `allowed`.

```json
{"version": 1, "code": "unisex", "name": {"en": "Unisex shirt"}, "sku_pattern": "{design}{size}{colour}",
 "supplier": null, "pricing": {"currency": "CAD", "base": 2500, "prices": {"size": {"2X": 2800}}, "supplements": {}},
 "axes": [{"code": "size", "label": {"en": "Size"}, "values": [{"code": "M", "label": {"en": "M"}, "swatch": null,
   "supplier_name": null, "photos": {}, "channels": []}]}], "faces": [], "exclusions": []}
```

## Relecture

`fromArray()` lit chaque valeur avec le type qu'il attend et construit le gabarit ou le visuel par leurs constructeurs :
un tableau relu est donc vérifié comme un gabarit construit dans le code. Un tableau d'une autre version, une valeur du
mauvais type — nommée par son chemin, `template.axes[0].values[2].code` — ou une définition que refusent les
constructeurs lève `InvalidDefinition`.

Les codes de valeur faits seulement de chiffres, comme une taille `0`, deviennent des clés entières une fois que PHP les
décode ; les lecteurs les acceptent comme les codes qu'ils sont.
