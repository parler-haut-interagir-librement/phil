# Doctrine

With DoctrineBundle, the bundle registers the column type `phil_phone`: a `PhoneNumber` in a `VARCHAR(33)`, in E.164
with its extension as RFC 3966 writes it, `+15145550123;ext=12`. Thirty-three characters hold the longest number
`PhoneNumber::parse()` accepts: `+`, a country code of three digits, a national number of seventeen, `;ext=` and an
extension of seven. A column created as `VARCHAR(32)` is widened by the next migration Doctrine generates.

```php
#[ORM\Column(type: 'phil_phone', nullable: true)]
private ?PhoneNumber $phone = null;
```

A value that is not a `PhoneNumber` is refused on writing; a stored text that is no number, on reading.
