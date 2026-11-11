# Checking and writing

## `AddressValidator`

```php
AddressValidator::violations($address);    // list<AddressViolation>, none when valid
AddressValidator::isValid($address);
```

| `Violation` | When |
|---|---|
| `Missing` | A field the country requires is not given |
| `Unused` | A field the country does not write is given: a state in Germany |
| `InvalidPostalCode` | The postal code does not match the country's pattern, or does not start as its state's or province's postal codes do |
| `UnknownSubdivision` | A state, a city or a district is not one of the country's, below its parent |

Subdivisions are checked level by level, as deep as the country goes and the data know: a level without a list is
taken as given.

## `AddressFormatter`

```php
AddressFormatter::format($address, 'fr');           // "1234 rue Saint-Denis\nMontréal QC H2X 3K8"
AddressFormatter::lines($address, 'fr');            // the same, a line each
AddressFormatter::postalLabel($address, 'US', 'fr'); // capitals where the country wants them, and "CANADA" from abroad
```

The format's lines get the fields in their places; empty lines, separators left over and lines of punctuation only go
away. A subdivision with a code of its own is written by it — `QC`, `TX` —, one without by its name, the local one
in its language. In the country's own language, the local format is used: Japan's starts with `〒`.

## schema.org

```php
SchemaOrg::of($address); // ['@type' => 'PostalAddress', 'streetAddress' => '1234 rue Saint-Denis', …]
```

`SchemaOrg::of()` gives a `PostalAddress`: the address lines joined, the localities, the region, the postal code and
the country code. Who receives the mail is left out. The array has a `@type` and no `@context`, so that it is embedded
in a larger JSON-LD document, a place's or an organisation's.

## Storage

`AddressArray::from()` writes an address as `{"country": "CA", "addressLine1": "…", …}`, `to()` reads it back and
throws `UnreadableAddress` for anything else — what `phil/address-bundle`'s column type stores as JSON.
