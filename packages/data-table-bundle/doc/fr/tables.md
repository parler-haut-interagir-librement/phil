<!-- phil-dev source-sha256=b3552384135803f10d13cfeec070a9afb101862717f29b0b4cb096b45665f376 -->
# Tableaux

Un tableau est un service qui implémente `DataTable`. Il est configuré automatiquement et trouvé par son `name()`.

```php
public function configure(TableBuilder $table): void
{
    $table
        ->caption('books.caption')           // required: it names the table to a screen reader
        ->translationDomain('catalogue')     // of every label; `messages` by default
        ->identifier('id')                   // the row's property, also a field of the data source
        ->version('version')                 // for the conflicts of inline editing
        ->requires('BOOKS_VIEW')             // a voter attribute to see the table
        ->column(Column::text('title', 'books.title')->sortable())
        ->sortBy('title', SortDirection::Asc)
        ->pageSizes(25, 50, 100)             // the first by default, 500 at most
        ->maxOffset(10_000);
}
```

`configure()` s'exécute à chaque ouverture du tableau et ne garde rien d'une requête. Une erreur dans une déclaration
lance `InvalidDefinition` au premier rendu.

## Colonnes

| Fabrique | Écrite comme |
|---|---|
| `Column::text($name, $label)` | La valeur sous forme de chaîne |
| `Column::number($name, $label, $decimals = null)` | Selon la locale, avec ce nombre de chiffres après la virgule |
| `Column::date($name, $label, $dateStyle, $timeStyle, $timezone)` | Selon les styles d'`IntlDateFormatter` |
| `Column::enum($name, $label, $choices)` | Le libellé de la valeur ; `$choices` est une liste ou une énumération adossée |
| `Column::boolean($name, $label)` | Oui ou Non |
| `Column::link($name, $label, $href)` | Le texte, lié à ce que `$href` retourne pour la ligne |
| `Column::block($name, $label, $template, $block)` | Un bloc d'un gabarit, avec `row` et `value` |

Un lien se limite à `http`, `https`, `mailto`, `tel` et aux adresses de ce site. Toute autre adresse est écrite comme du
texte.

## Thèmes de cellules

Une colonne en bloc est dessinée par un `Twig\BlockChain` : les gabarits de `cell_themes` d'abord, dans leur ordre, puis le
gabarit propre à la colonne. Un thème redéfinit un bloc en définissant un bloc du même nom ; chaque bloc qu'il omet vient
du gabarit de la colonne. Le bloc reçoit `row` et `value` dans son contexte, avec les globales de Twig.

```yaml
# config/packages/phil_data_table.yaml
phil_data_table:
    cell_themes: ['tables/cells.html.twig']
```

```twig
{# templates/tables/cells.html.twig #}
{% block cover %}<img class="cover" alt="{{ row.title }}" src="{{ asset('covers/' ~ row.id ~ '.jpg') }}">{% endblock %}
```

Un bloc introuvable dans tout gabarit interrompt le rendu par une `RuntimeError` de Twig.

Chaque colonne est immuable, et ses méthodes en retournent une nouvelle :

| Méthode | Effet |
|---|---|
| `of('author?.name')` | Lit ce chemin de propriété de la ligne au lieu du nom de la colonne |
| `reading($function)` | Lit la valeur avec une fonction |
| `sortable($field = null)` | Offre le tri, sur ce champ de la source de données ou sur le chemin de la colonne |
| `editable(Editing $editing)` | Voir [Actions et modification](actions-editing.md) |

Une ligne est un tableau, lu par clé, ou un objet, lu par Symfony PropertyAccess.

## Filtres

| Fabrique | Contrôle | Dans l'adresse |
|---|---|---|
| `Filter::text($name, $label, $fields = [])` | Champ de recherche, 200 caractères au plus | `filters[name]` |
| `Filter::enum($name, $label, $choices, $field = null)` | Liste | `filters[name]` |
| `Filter::dateRange($name, $label, $field = null, $timezone = null)` | Deux jours, tous deux inclus | `filters[name_from]`, `filters[name_until]` |
| `Filter::boolean($name, $label, $field = null)` | Peu importe, oui ou non | `filters[name]` |

Un filtre de texte cherche dans n'importe lequel de ses champs. Tous les filtres d'un tableau s'appliquent ensemble. Une
valeur hors de ce qu'accepte son filtre est abandonnée, et deux jours dans le mauvais ordre sont intervertis.

## Le composant

```twig
<twig:PhilDataTable:Table name="books" />
<twig:PhilDataTable:Table name="items" urlPrefix="items_" :context="{owner: owner.id}" />
```

`context` parvient à `DataTable::source()`, qui s'en sert pour restreindre les lignes. Il est signé : un navigateur ne
peut donc pas le changer. Des scalaires seulement.
