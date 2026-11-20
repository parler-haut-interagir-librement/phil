<!-- phil-dev source-sha256=c593e1927ba61cc9d590d507196d263c176bfe762531c646adec5cf2548b0ec9 -->
# Stockage

Avec DoctrineBundle, le module déclare par attributs la correspondance de quatre entités, sous
`Phil\CommerceProductTemplateBundle\Domain\Model`. L'application crée leurs tables avec ses migrations.

| Table | Entité | Colonnes |
|---|---|---|
| `phil_commerce_product_template` | `ProductTemplate` | `code` (clé), `definition` (JSON), `kind`, `tax_category`, `options` (JSON) |
| `phil_commerce_product_design` | `ProductDesign` | `code` (clé), `definition` (JSON), `options` (JSON), `print_layout` (JSON, `[]` par défaut) |
| `phil_commerce_product_group` | `ProductGroup` | `id` (clé), `design`, `template` (uniques ensemble), `active` |
| `phil_commerce_product_group_member` | `GroupMember` | `sku` (clé), `group_id` (indexée), `axis_values` (JSON), `language`, `active`, `mockups` (JSON) |

`definition` contient les tableaux de `TemplateArray` et de `DesignArray`, avec leur `version` ; la lecture d'une
définition vérifie chaque valeur : une rangée modifiée à la main en quelque chose qu'aucun code n'a écrit est donc
refusée avec `InvalidDefinition`. `options` contient les tableaux de `TemplateOption::toArray()` :

```json
[{"code": "ink", "label": "Ink", "kind": "ink", "required": false, "supplement": null, "text": null,
  "choices": [{"code": "glow", "label": "Glow in the dark", "image": null, "supplement": {"amount": 300, "currency": "CAD"}}],
  "drawer": null, "when": {"colour": ["BK", "NV"]}}]
```

`print_layout` contient les tableaux de `PrintElement::toArray()`, dans l'ordre où ils sont dessinés ; la lecture d'un
élément vérifie chaque valeur par les constructeurs de l'élément, et refuse avec `TemplateRefused::unreadable()` ce
qu'aucun code n'a écrit :

```json
[{"kind": "dice", "face": "front", "zone": "chest", "option": "loot", "box": null, "path": null, "images": [],
  "slots": [{"box": {"x": 0.0, "y": 270.0, "width": 40.0, "height": 40.0}, "sides": 6, "images": ["loot/table-1/1.png", "..."]}],
  "font": null, "colour": "#000000", "max_size": 72.0, "min_size": 6.0, "ink": "colour", "when": {"version": ["L"]}, "chosen": {}}]
```

Les fichiers d'impression et les ordres de production sont des fichiers du magasin de production, non des rangées
([Fichiers d'impression et ordres de production](production.md)).

Les produits eux-mêmes sont des rangées de `phil_commerce_catalog_product` du catalogue, par UGS, comme n'importe quel
autre produit. Un membre se trouve par son UGS — la clé —, et les membres d'un groupe par l'index sur `group_id`.
