<!-- phil-dev source-sha256=093ccf59d1f60ad67c43350602422643cc7441cad2f52372d43157c3b6c12649 -->
# Twig

Le filtre est enregistré quand Twig est installé. Il garde le préfixe `phil_` pour ne pas entrer en conflit avec le
filtre `slug` de `twig/string-extra`.

| Filtre | Arguments | Donne |
|---|---|---|
| `phil_slug` | `slugger` (facultatif) : le nom d'un générateur | Le slug du texte, par ce générateur ou par celui par défaut |

```twig
{{ 'Jeux de rôle'|phil_slug }}                       {# jeux-de-role #}
{{ "L'Ordre du Corbeau"|phil_slug('directory') }}    {# ordre-du-corbeau, with leading_words [l, …] #}
```

- Un texte dont on ne peut faire aucun slug (`'?!'`) et `null` donnent une **chaîne vide** : un nom de classe ou une
  clé de tri vaut mieux vide qu'une page d'erreur.
- Un nom de générateur inconnu est une erreur (`UnknownSlugger`) : c'est une faute dans le gabarit, pas dans les
  données.
