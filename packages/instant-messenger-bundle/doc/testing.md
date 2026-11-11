# Testing

| Area | What is covered |
|---|---|
| `AcceptedMessengers` | Every form of accepted services, the choices in order, refusals |
| `HandleType`, `HandleTransformer` | Submitting, the choices and their values, showing, an empty or a bad handle |
| `HandleOfType` | Handles and texts on accepted services or not, messages and codes |
| `MessengerExtension`, `PhilInstantMessengerBundle` | The filters, the services |

A functional suite boots a kernel with FrameworkBundle, TwigBundle and the bundle: `InstantMessengerBundleTest`
submits the field, validates in `fr_CA` and renders the filters. Installed on its own, where FrameworkBundle is a
development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/instant-messenger-bundle/tests
```
