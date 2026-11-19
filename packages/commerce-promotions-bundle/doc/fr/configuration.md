<!-- phil-dev source-sha256=f7f718dab00a8ab1d0ec849915e038b2dee62e08f83ae19ecfd45e71a885157b -->
# Configuration

La configuration du module se trouve sous `phil_commerce_promotions`. Sans configuration, chaque promotion est
affichée sous son propre libellé.

```yaml
# config/packages/phil_commerce_promotions.yaml
phil_commerce_promotions:
    translations:
        locales:
            fr_CA: [fr]
            fr: [en]
            en: []
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `translations.locales` | `{}` | Les locales dans lesquelles les libellés sont affichés, chacune avec les locales essayées après elle. Une locale que `phil/locale-dimensions` ne sait pas lire fait échouer la construction du conteneur. |

Quand des locales sont déclarées, `PromotionTexts` lit chaque libellé dans la locale de la requête — celle du
traducteur, lue à chaque appel, si bien qu'un *worker* sert chaque requête dans la sienne —, et les rabais d'une
commande portent le libellé dans cette locale. Une locale non déclarée, ou une traduction dont le libellé n'est pas
encore écrit, retombe sur le libellé propre de la promotion.
