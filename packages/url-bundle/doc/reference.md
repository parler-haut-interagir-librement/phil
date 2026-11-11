# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilUrlBundle` | `final class`, `AbstractBundle` | Alias `phil_url`; the form type, the validator, the Twig extension, the column type |
| `Application\AcceptedUrlTypes` | `final class` | The accepted kinds as enum sets |
| `Infrastructure\Form\TypedUrlType` | form type | One field, its kind detected |
| `Infrastructure\Form\TypedUrlTransformer` | data transformer | Between a `TypedUrl` and the field's text |
| `Infrastructure\Validator\UrlOfType`, `UrlOfTypeValidator` | constraint and validator | An address of an accepted kind |
| `Infrastructure\Doctrine\UrlColumnType` | DBAL type `phil_url` | A `Url` in a `VARCHAR(2048)` |
| `Infrastructure\Twig\UrlExtension` | Twig extension | `url_display`, `url_type()`, `url_type_label` |
