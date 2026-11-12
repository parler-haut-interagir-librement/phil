# EasyAdmin

With `easycorp/easyadmin-bundle`, the bundle registers `MediaFieldConfigurator`, and `MediaField` shows a property
that holds a medium — a `phil_media` column — in a CRUD controller:

```php
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaField;

public function configureFields(string $pageName): iterable
{
    yield TextField::new('title');
    yield MediaField::new('cover')->setCollection('covers');
}
```

| Page | What it shows |
|---|---|
| Index and detail | The medium's thumbnail — the format `admin_thumbnail` —, or its file's extension, and its name; `None` when the property is empty or the medium no longer exists |
| New and edit | `MediaPickerType`: the chosen file, a button that opens the explorer's picker in a dialog, and one that empties the field |

`setCollection()` opens the picker on a collection, unless the field's form options already name one. The picker is
the explorer's listing: import its routes, behind the same firewall as the dashboard, and load the bundle's Stimulus
controllers on the dashboard's pages — with AssetMapper, add the importmap entry of the application's Stimulus
bootstrap to the dashboard through `configureAssets()`. Pair the property with the `MediaFile` constraint to limit what
may be chosen.

## The explorer in the back office

The file explorer is the media library's administration page. `MediaAdmin::menuItem()` links the dashboard's menu to
it:

```php
use Phil\MediaBundle\Infrastructure\EasyAdmin\MediaAdmin;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Content');
    yield MediaAdmin::menuItem();                                    // "Media", fa fa-photo-film
    yield MediaAdmin::menuItem('Covers', 'fa fa-image', 'covers');   // opened on a collection
}
```

The label may be a `TranslatableInterface`. The explorer keeps its own routes, access, stateless CSRF tokens and
layout, and goes through `MediaLibrary` for every upload, rename, move and deletion — so a deletion still asks the
records that show the medium first. Point `phil_media.admin.layout` to a template of the back office that defines the
`title`, `stylesheets`, `javascripts` and `content` blocks, and import the explorer's routes behind the dashboard's
firewall: see [The file explorer](admin.md).

The integration is optional: without EasyAdmin, nothing of it is wired and nothing of it is loaded.
