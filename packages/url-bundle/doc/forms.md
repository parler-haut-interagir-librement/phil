# Forms

`TypedUrlType` is one text field, to and from a `TypedUrl`: the kind of the address is detected among the `types`
it accepts.

```php
$builder->add('social', TypedUrlType::class, ['types' => [KnownUrlType::Facebook, KnownUrlType::Instagram]]);
```

- It extends Symfony's `UrlType`, with `https` as the default protocol: `instagram.com/shop` is read as
  `https://instagram.com/shop`.
- An empty field is `null`; an address of no accepted kind makes the field invalid with *This address is not one of
  the accepted kinds.*
