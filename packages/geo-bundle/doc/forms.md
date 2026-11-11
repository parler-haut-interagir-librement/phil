# Forms

`CoordinatesType` is a position as two number fields, `latitude` and `longitude`, to and from `Coordinates`; it reads
decimals in the request's locale, to seven places.

```php
$builder->add('position', CoordinatesType::class, ['required' => false]);
```

Both fields empty is no position, `null`. One field without the other, or a value off the Earth, makes the field
invalid with its `invalid_message`, *This value is not a valid position.*

A `null` needs a property that takes one. The Doctrine embeddable holds a position always: bound to it, keep the
field `'required' => true`. An optional position is stored as two nullable columns instead, as
[the Doctrine document](doctrine.md#an-optional-position) shows.
