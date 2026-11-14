<!-- phil-dev source-sha256=25eab723741df32b44a8d23fa78b02ad9d011c769296a372f72678366893da33 -->
# Points d'extension

Chaque port se trouve dans `Phil\DataTableBundle\Application\Contract`. Jusqu'à la première version, chacun peut encore
changer ; il est stable à partir de 1.0.

## `DataTable`

Un tableau de l'application. Voir [Tableaux](tables.md). Le service est configuré automatiquement avec l'étiquette
`phil_data_table.table`.

## `DataSource`

L'origine des lignes. Voir [Sources de données](data-sources.md).

## `TableAccess`

Si celui qui regarde détient un attribut de *voter* sur un sujet. Remplacez-le par `phil_data_table.access`.

```php
interface TableAccess
{
    public function isGranted(string $attribute, array|object|null $subject = null): bool;
}
```

Symfony Security répond par défaut lorsqu'il est installé. Sinon, chaque attribut est refusé.

## `TablePreferences`

Les colonnes et la taille de page qu'un utilisateur garde, tableau par tableau. Remplacez-le par
`phil_data_table.preferences`.

```php
interface TablePreferences
{
    public function load(string $table): TablePreference;

    public function save(string $table, TablePreference $preference): void;
}
```

Par défaut, rien n'est gardé. Avec phil/settings-bundle, `SettingsTablePreferences` les garde dans le réglage
d'utilisateur `data_table.preferences`, et un visiteur garde les siens dans l'adresse seulement.

## Gabarits et styles

Redéfinissez n'importe quel gabarit sous `templates/bundles/PhilDataTableBundle/`. Le gabarit et le bloc propres à une
colonne dessinent une cellule avec `Column::block()`, et les gabarits de `cell_themes` en redéfinissent les blocs un à un :
voir [Thèmes de cellules](tables.md#thèmes-de-cellules). La feuille de style est dans la couche `phil.components` : les
styles hors de toute couche l'emportent.

## Mode worker

Les services ne gardent rien entre deux requêtes. L'implémentation d'un tableau fait de même : son contexte arrive en
argument.
