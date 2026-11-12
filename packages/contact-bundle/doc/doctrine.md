# Doctrine

With DoctrineBundle, the bundle registers the column type `phil_contact`: a `ContactCard` in one JSON column, as
`phil/contact`'s `ContactCardArray` writes it. No table per owner: a shop, a person, an organisation each keep their
card in a column of their own.

```php
#[ORM\Column(type: 'phil_contact', nullable: true)]
private ?ContactCard $contact = null;
```

A value that is not a `ContactCard` is refused on writing; a stored text that is no JSON, nested more than 8 levels,
or no card, on reading.

## Structured names

A card's structured name is kept in the same column, under the `structuredName` key `ContactCardArray` writes. The
column type and the SQL schema do not change: no Doctrine migration is needed. A card stored without a structured
name reads back without one, its `name` staying its only name, and gains one the next time it is saved with one. An
application that wants the parts of the names it already keeps asks for them in the card form, with
`structured_name`, or fills them itself and saves the cards again.
