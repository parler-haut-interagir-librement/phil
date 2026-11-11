<!-- phil-dev source-sha256=f39b0b057ebfb1ef9744edaf9026cff5269cf911f2dca05f66419cfad45c988e -->
# Points d'extension

Le bundle n'a aucun port à implémenter. Ce qu'une application fournit est déclaratif :

- **Sur une enum** : les métadonnées de `phil/enums` (`#[Label]`, `#[Description]`, `#[Group]`, ses propres
  méta-propriétés), `AsTranslatableEnumTrait` avec `implements TranslatableInterface` pour la rendre traduisible, et
  `#[Translatable(domain:, prefix:)]` pour choisir son domaine de traduction et son préfixe de clé.
- **Dans les fichiers de traduction** : les traductions des libellés et des descriptions, dont les clés sont les textes
  sources, dans le domaine de l'enum ([Traduction](translation.md)).
- **Sur un champ de formulaire** : les options `group`, `only`, `except` et `where_meta` ([Formulaires](forms.md)).
- **Sur une propriété** : la contrainte `EnumCase` ([Validation](validation.md)).
- **Sur un cas, pour les badges** : les méta-propriétés `Color` et `Icon`, sous forme de noms que la feuille de style et
  le jeu d'icônes de l'application connaissent ([Twig](twig.md)).
- **Dans les gabarits** : `templates/bundles/PhilEnumsBundle/badge.html.twig`, qui remplace le badge du bundle.
- **Dans la configuration** : les chemins que lisent les commandes, et les types de colonne `EnumSet`
  ([Configuration](configuration.md)).

Les services que le bundle enregistre, avec leurs étiquettes, sont listés dans [Configuration](configuration.md). Une
application qui veut une autre règle de libellé peut toujours passer son propre `choice_label` à un champ : l'extension
ne fixe que la valeur par défaut.
