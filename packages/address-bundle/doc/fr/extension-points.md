<!-- phil-dev source-sha256=4d9ae47435b1af8569e0b90448225d9e39f6decc04f96a21bd1ce32323951734 -->
# Points d'extension

Ce qu'une application configure, étend ou écoute. Les types d'adresses sont ceux de `phil/address`, dont le bundle lit
les données telles quelles.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `default_country` | Le pays d'une nouvelle adresse, ou `null` pour le demander |
| `format_url` | Le point de terminaison que lit le contrôleur Stimulus, `__COUNTRY__` tenant lieu du pays ; `null` laisse le contrôleur de côté — voir [Installation](installation.md) |

## Formulaires

| Point | Comment |
|---|---|
| Les pays offerts | L'option `countries` d'`AddressType` |
| Le pays d'une nouvelle adresse | Son option `default_country` |
| Les champs du destinataire | Son option `with_recipient` |
| Les libellés | Le domaine de traduction `phil_address` : les traductions de l'application remplacent celles qui sont fournies |
| Plus que ce que fait le formulaire | Une extension de type de formulaire de l'application sur `AddressType`, comme pour tout type de formulaire Symfony |

Voir [Formulaires](forms.md).

## Le contrôleur Stimulus

`phil--address-bundle--address` émet `changed`, avec le format du pays, une fois que les champs suivent un nouveau pays,
et `failed`, avec l'erreur, lorsque le format ne peut pas être lu. Un contrôleur de l'application les écoute pour
remplir ou afficher davantage.

## Validation et stockage

| Point | Comment |
|---|---|
| Des règles au-delà de celles du pays | Une contrainte de l'application à côté de `ValidAddress`, qui vérifie ce que dit le format du pays |
| L'endroit où une adresse est conservée | Le type de colonne `phil_address`, du JSON par `AddressArray` — voir [Validation et stockage](validation-and-storage.md) |

## Twig

`address`, `address_lines`, `address_label`, `country_name` et `address_schema` écrivent ce que donne `phil/address`.
Une mise en page propre à l'application lit `address_lines` et place elle-même chaque ligne — voir [Twig](twig.md).
