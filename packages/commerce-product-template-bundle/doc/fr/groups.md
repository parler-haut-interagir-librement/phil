<!-- phil-dev source-sha256=1b50cbd9f9649155359d47c659f0628e7c74367c9086be46762d2f71d8e4e7e0 -->
# Groupes

Les produits d'un motif sur un gabarit forment un **groupe** : la page que voit un acheteur, avec un sélecteur par axe
— taille, couleur, version —, tandis que chaque produit reste un produit du catalogue avec sa propre UGS, son prix et
son stock.

| Type | Contient |
|---|---|
| `ProductGroup` | Son identifiant `<design>:<template>`, les codes de son motif et de son gabarit, s'il est actif |
| `GroupMember` | Une UGS du groupe, le code de sa valeur sur chaque axe, sa version linguistique, s'il est actif, le chemin de sa maquette par face |

Les groupes et les membres ne sont créés et mis à jour que par la génération ; une application les lit.

## La page d'un groupe

```php
$page = $groups->page('NAQ001', 'unisex', 'fr');
```

`ProductGroups::page()` retourne une `GroupPage`, ou `null` pour un groupe retiré :

| Membre | Ce que c'est |
|---|---|
| `id`, `name` | L'identifiant du groupe, et `Design – Template` dans la locale |
| `axes` | Pour chaque axe du gabarit, dans l'ordre : son code, son libellé et les valeurs qu'a au moins un membre actif, chacune avec son code, son libellé et ses pastilles `hex` et `photo` |
| `members` | Chaque membre actif : l'UGS, les valeurs par axe, la version linguistique et les maquettes par face |
| `skuFor($values, $language)` | L'UGS du membre actif de ces valeurs et de cette langue, ou `null` |

Un sélecteur n'offre jamais une combinaison que le gabarit exclut, ni une combinaison que le motif ne permet pas : aucun
membre ne l'a. Un contrôleur Stimulus de l'application lit les membres une seule fois et trouve l'UGS, et la maquette,
de chaque choix sans interroger le serveur.
