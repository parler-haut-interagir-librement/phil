# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the bundle declares |
| `phil/contact` | ^0.1@dev | The cards |
| `phil/phone-bundle`, `phil/url-bundle`, `phil/instant-messenger-bundle` | ^0.1@dev | The fields of the card form |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | The card form |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | The bundle class and its configuration |
| `doctrine/dbal` | optional | The column type `phil_contact` |
| `symfony/http-foundation` | optional | `VCardResponse`, `JSContactResponse` |
| `twig/twig` | optional | The functions and the form theme |

```sh
composer require phil/contact-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\PhoneBundle\PhilPhoneBundle::class => ['all' => true],
    Phil\UrlBundle\PhilUrlBundle::class => ['all' => true],
    Phil\InstantMessengerBundle\PhilInstantMessengerBundle::class => ['all' => true],
    Phil\ContactBundle\PhilContactBundle::class => ['all' => true],
];
```

Phone numbers entered in the form are read in `phil_phone.default_region`. With Symfony UX, the Stimulus controller
is in `assets/`, declared in its `package.json`.

## Configuration

```yaml
# config/packages/phil_contact.yaml
phil_contact:
    jscontact:
        max_bytes: 65536      # the longest card the JSContact reader reads
        max_depth: 16         # the deepest nesting of its JSON, 2 or more
        max_entries: 100      # the most entries of one map or list
        region: ~             # the country of phone numbers without their country code: "CA"
```

Every key is optional; the values above are the defaults.
