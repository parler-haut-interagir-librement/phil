# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilInstantMessengerBundle` | `final class`, `AbstractBundle` | Alias `phil_instant_messenger`; the form type, the validator, the Twig extension |
| `Application\AcceptedMessengers` | `final class` | The accepted services as enum sets, and as choices |
| `Infrastructure\Form\HandleType` | form type | The service and the handle |
| `Infrastructure\Form\HandleTransformer` | data transformer | Between a `Handle` and the two fields |
| `Infrastructure\Validator\HandleOfType`, `HandleOfTypeValidator` | constraint and validator | A handle on an accepted service |
| `Infrastructure\Twig\MessengerExtension` | Twig extension | `messenger_display`, `messenger_link`, `messenger_label` |
