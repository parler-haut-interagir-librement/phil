# Extension points

What an application configures, overrides or relies on. The bundle declares no PHP port: its contract is its
configuration key, the logical path of its stylesheet, its cascade layer and the variables it sets.

| Point | How |
|---|---|
| Linking the theme | On by default on every page of every dashboard; `phil_ux_easy_admin.theme: false` leaves it to `configureAssets()` ([Installation](installation.md#configuration)) |
| The logical path | `phil-ux-easyadmin/phil-ux-easyadmin.css`, `PhilUxEasyAdminBundle::STYLESHEET` |
| A role of PHIL UX | `--phil-color-*`, `--phil-radius-*`, `--phil-font-*` redefined on `:root, .ea-dark-scheme` outside any layer: the site and its back-office follow ([The theme](theme.md#changing-the-theme)) |
| A variable of EasyAdmin | Redefined on `:root, .ea-dark-scheme` outside any layer: it wins over the theme |
| A layer of the application | Declared after `phil-easyadmin` |
| EasyAdmin's `Theme` options | Written by EasyAdmin outside any layer: they win over the theme |
| The listener | The service `phil_ux_easy_admin.theme_stylesheet`, a `kernel.request` listener at priority -8, after EasyAdmin's router listener |
