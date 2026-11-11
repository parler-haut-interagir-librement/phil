# Extension points

What an application configures or extends. The numbers are `phil/phone`'s, read with its imported numbering plans.

## Configuration

| Option | What it extends |
|---|---|
| `default_region` | The country a number typed without its country code is read in, by the field, the constraint and the filters — see [Installation](installation.md) |

## Forms and validation

| Point | How |
|---|---|
| The country of one field | The `region` option of `PhoneType` |
| Which numbers are accepted | The `types` and `regions` of `ValidPhoneNumber`, and its `region` for a text |
| The messages | The constraint's `message`, `typeMessage` and `regionMessage`, and its codes `INVALID_ERROR`, `TYPE_ERROR`, `REGION_ERROR` |
| More than the field does | A form type extension of the application's on `PhoneType` |

See [Forms and validation](forms-and-validation.md).

## Storage and Twig

| Point | How |
|---|---|
| Where a number is kept | The `phil_phone` column type, E.164 with its extension — see [Doctrine](doctrine.md) |
| How a number is shown | The `phone` filter with a format, and `phone_link` for a `tel:` address — see [Twig](twig.md) |
