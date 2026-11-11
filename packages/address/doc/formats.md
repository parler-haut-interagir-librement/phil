# Addresses and formats

## `Address`

```php
$address = Address::of('ca', ['addressLine1' => ' 1234 rue Saint-Denis ', 'postalCode' => 'H2X 3K8', 'addressLine2' => '']);
$address->country;                          // "CA"
$address->get(AddressField::PostalCode);    // "H2X 3K8"
$address->with(AddressField::Locality, 'Montréal');
```

A country, by its ISO 3166-1 alpha-2 code, and fields trimmed — an empty one is none, each 255 characters or fewer.
An unknown country or field throws `InvalidAddress`. An address is not checked when it is made: `AddressValidator`
says whether it is right for its country.

## The fields

`AddressField`: `givenName`, `additionalName`, `familyName`, `organization` — the recipient, `AddressField::recipient()`
—, `addressLine1` to `addressLine3`, `dependentLocality` (a neighbourhood, a district), `locality` (a city),
`administrativeArea` (a state, a province), `postalCode`, `sortingCode` (France's CEDEX).

## `AddressFormat`

`AddressFormats::of($country)` gives a country's format; a country without its own takes the generic one.

| Property | Holds |
|---|---|
| `format`, `localFormat`, `locale` | The lines, `%field` in their places; the lines in the country's own script and its language — Japan's, in `ja` |
| `required`, `uppercase` | The fields the country requires, those a postal label writes in capitals |
| `postalCodePattern`, `postalCodePrefix` | The pattern of its postal codes; a prefix some write, `SE-` |
| `administrativeAreaType`, `localityType`, `dependentLocalityType`, `postalCodeType` | What it calls them: `state`, `province`, `prefecture`; `city`, `post_town`; `zip`, `postal`, `eircode` |
| `subdivisionDepth`, `subdivisionFields()` | How many levels of subdivisions its addresses name, from the administrative area down |
| `defaults` | Values a country's addresses always have: `Singapore` for the locality of Singapore |

`fields()` gives the fields the format writes, in order; `uses()` and `requires()` ask about one.
