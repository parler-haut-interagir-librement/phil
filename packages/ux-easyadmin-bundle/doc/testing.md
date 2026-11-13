# Testing

| Area | What is covered |
|---|---|
| `PhilUxEasyAdminBundle` | The key and its refusal; the listener and its priority, or no service with `theme: false`; the stylesheet's directory mapped, nothing without FrameworkBundle |
| `ThemeStylesheet` | The stylesheet added after the dashboard's own, a stylesheet already listed kept as the dashboard wrote it, a request without an EasyAdmin context left alone |
| `ThemeSheet` | Each import of a token file replaced by its layer, every other line kept, a name with a path, upper-case letters or nothing, a layer with none, a media query or a trailing comment left as written, a missing file and a directory refused; the served stylesheet equal to the one the source and the installed phil/ux-css give; every variable of the theme declared by the stylesheets of the installed EasyAdmin, for the accent, the sidebar, the tables, the forms, the alerts and badges of every state, the corners, the focus and the fonts; every token it reads declared by phil/ux-css; its layer after EasyAdmin's |
| The recipe | The bundle registered in every environment, its configuration copied with the theme on |
| The back-office | A kernel with EasyAdmin, two dashboards, SecurityBundle, TwigBundle, TwigComponentBundle and DoctrineBundle on SQLite: the theme linked after the dashboard's stylesheet, once when the dashboard lists it, absent with `theme: false`; the stylesheet found by AssetMapper |

| `tests/Browser` | A page of the back-office in a real Chrome, served by PHP's built-in server with EasyAdmin's built stylesheet: the background, the text, the accent, the sidebar, an alert, a badge, a table border and the corners computed from the PHIL UX roles in the light scheme, the dark roles in the dark scheme, and a style of the application winning over the theme |

Installed on its own, where FrameworkBundle is a development requirement, the functional suite is skipped. The browser
suite needs the chromedriver of the system and runs apart, at the end of a batch.

```sh
vendor/bin/phpunit packages/ux-easyadmin-bundle/tests
php packages/ux-easyadmin-bundle/bin/build.php   # after a change of the source or of phil/ux-css
composer test:browser
```
