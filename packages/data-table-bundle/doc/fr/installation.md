<!-- phil-dev source-sha256=4d2a7368136800f80fc4b5a903299c76616b87c177231a849931fbd02246bee2 -->
# Installation

## Prérequis

PHP 8.5, Symfony 8.2, Twig 3.29 et Live Component de Symfony UX 3.7. La protection CSRF de FrameworkBundle reste activée :
sinon, le conteneur s'arrête avec un message qui le dit. Le bundle ajoute son jeton à ceux qui sont sans état : il n'a donc pas
besoin de session.

```php
// config/bundles.php
Phil\DataTableBundle\PhilDataTableBundle::class => ['all' => true],
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_data_table.yaml` sans thème de
cellules, et `config/routes/phil_data_table.yaml`, qui importe les routes ci-dessous. La recette se trouve dans le dépôt de
recettes de PHIL, lu une fois que l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans cela, enregistrez
le bundle et importez les routes à la main.

## Routes

Les modifications d'un navigateur sans JavaScript passent par deux routes POST. Un tableau avec des actions ou des
colonnes modifiables en a besoin ; un tableau en lecture seule, non.

```yaml
# config/routes/phil_data_table.yaml
phil_data_table:
    resource: '@PhilDataTableBundle/config/routes.php'
    prefix: /_tables
```

Les routes se trouvent derrière le pare-feu de l'application, et chaque modification demande aussi son *voter*.

## Les recettes du kit

Les gabarits dessinent leurs boutons avec la recette Button du kit PHIL UX, installée dans l'application par le Toolkit
de Symfony UX ; rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

La recette a le nom, les props et les blocs de celle du kit Shadcn : un site sous Tailwind l'installe donc depuis le kit
Shadcn. Les gabarits l'appellent par `phil_data_table_kit('Button')`, sous le sous-répertoire des composants de la
configuration `ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production,
répétez-le dans `kit_subdirectory`.

## Ressources

Avec AssetMapper, le bundle associe son contrôleur sous `@phil/data-table-bundle` et sa feuille de style sous
`phil-data-table`.

```css
@import url("phil-data-table/data-table.css");
```

Le contrôleur Stimulus `phil--data-table-bundle--table` redonne le focus après un nouveau rendu, gère Échap dans une
cellule et fait de la question d'une action un dialogue modal. Le tableau fonctionne sans lui.

## Configuration

```yaml
phil_data_table:
    access: ~        # a TableAccess service; Symfony Security when installed, refusing every attribute otherwise
    preferences: ~   # a TablePreferences service; phil/settings-bundle when installed, nothing kept otherwise
    cell_themes: []  # templates whose blocks draw the cells of Column::block() before the column's own template
    kit_subdirectory: ~  # where the recipes of the kit are installed; the subdirectory of ux_toolkit when null
```

Sans Symfony Security et sans `access`, chaque attribut de *voter* est refusé : les actions et la modification ne sont
pas offertes.
