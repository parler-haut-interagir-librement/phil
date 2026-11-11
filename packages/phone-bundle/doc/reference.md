# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilPhoneBundle` | `final class`, `AbstractBundle` | Alias `phil_phone`, key `default_region`; the services and the column type |
| `Infrastructure\Form\PhoneType` | form type | A number in a `tel` field |
| `Infrastructure\Form\PhoneNumberTransformer` | data transformer | Between a `PhoneNumber` and its text |
| `Infrastructure\Validator\ValidPhoneNumber`, `ValidPhoneNumberValidator` | constraint and validator | A valid number of accepted types and countries |
| `Infrastructure\Doctrine\PhoneNumberColumnType` | DBAL type | `phil_phone` |
| `Infrastructure\Twig\PhoneExtension` | Twig extension | `phone`, `phone_link` |
