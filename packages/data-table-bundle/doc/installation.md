# Installation

## Requirements

PHP 8.5, Symfony 8.2, Twig 3.29 and Symfony UX Live Component 3.7. FrameworkBundle's CSRF protection stays on: the
container stops with a message saying so otherwise. The bundle adds its token to the stateless ones, so it needs no session.

```php
// config/bundles.php
Phil\DataTableBundle\PhilDataTableBundle::class => ['all' => true],
```

## The recipe

The Flex recipe of the bundle registers it in `config/bundles.php`, writes `config/packages/phil_data_table.yaml` with no
cell theme, and `config/routes/phil_data_table.yaml`, which imports the routes below. The recipe lives in the recipes
repository of PHIL, read once the application lists that repository in `extra.symfony.endpoint`; without it, register
the bundle and import the routes by hand.

## Routes

The changes of a browser without JavaScript go through two POST routes. A table with actions or editable columns needs
them, and a read-only table does not.

```yaml
# config/routes/phil_data_table.yaml
phil_data_table:
    resource: '@PhilDataTableBundle/config/routes.php'
    prefix: /_tables
```

The routes sit behind the application's firewall, and each change also asks its voter.

## The recipes of the kit

The templates draw their buttons with the Button recipe of the PHIL UX kit, installed in the application by the Symfony
UX Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

The recipe has the name, props and blocks of the Shadcn kit's, so a site on Tailwind installs it from the Shadcn kit
instead. The templates call it through `phil_data_table_kit('Button')`, under the components subdirectory of the
`ux_toolkit` configuration; in an environment where the Toolkit is not enabled, as production often is, repeat it in
`kit_subdirectory`.

## Assets

With AssetMapper, the bundle maps its controller under `@phil/data-table-bundle` and its stylesheet under
`phil-data-table`.

```css
@import url("phil-data-table/data-table.css");
```

The Stimulus controller `phil--data-table-bundle--table` gives the focus back after a re-render, handles Escape in a
cell, and makes the question of an action a modal dialog. The table works without it.

## Configuration

```yaml
phil_data_table:
    access: ~        # a TableAccess service; Symfony Security when installed, refusing every attribute otherwise
    preferences: ~   # a TablePreferences service; phil/settings-bundle when installed, nothing kept otherwise
    cell_themes: []  # templates whose blocks draw the cells of Column::block() before the column's own template
    kit_subdirectory: ~  # where the recipes of the kit are installed; the subdirectory of ux_toolkit when null
```

Without Symfony Security and without `access`, every voter attribute is refused: actions and editing are not offered.
