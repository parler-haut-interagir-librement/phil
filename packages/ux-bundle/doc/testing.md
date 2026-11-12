# Testing

| Area | What is covered |
|---|---|
| `PhilUxBundle` | The key and its refusal, no service; the theme prepended before the application's themes, the last value of the key winning; the stylesheet's directory mapped; nothing without Twig or FrameworkBundle |
| The form theme | Forms rendered by Symfony's Twig renderer: a row, the classes of the application kept, one error and several, expanded choices as a set of fields, check boxes, the errors of the form, a theme of the application winning, the theme turned off |
| The stylesheet | Found by AssetMapper as `phil-ux/phil-ux.css`, from the `dist/` directory of phil/ux-css |

A functional suite boots a kernel with FrameworkBundle, its forms and AssetMapper, TwigBundle and the bundle, once per
configuration. Installed on its own, where FrameworkBundle is a development requirement, it is skipped.

```sh
vendor/bin/phpunit packages/ux-bundle/tests
```
