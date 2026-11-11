# Forms

## `AddressType`

To and from an `Address`: the country, then the fields of that country's format, in its order, named as it names
them — *État* for the United States, *Province* for Canada, *Préfecture* for Japan —, required as it requires them.
Where the country has states or provinces, the administrative area is a choice of them, named in the request's
language: *Québec (QC)*. Hidden and disabled, the fields of other countries are there for the Stimulus controller.

On submission, the fields follow the country submitted, its code in capitals; the address is then checked against
its country, and each wrong field gets its error, translated in the `validators` domain.

```php
$builder->add('address', AddressType::class, ['countries' => ['CA', 'US', 'FR'], 'with_recipient' => true]);
```

| Option | Default | Means |
|---|---|---|
| `default_country` | the bundle's `default_country` | The country of a new address |
| `countries` | `null`: every country | The countries offered |
| `with_recipient` | `false` | Whether the recipient's names and organisation are fields |

Labels are in the `phil_address` translation domain.

## The Stimulus controller

With `format_url` configured, the form carries `phil--address-bundle--address`. When the country changes, it reads
`/address-format/<country>` — the fields with their labels in the request's language and whether they are required,
and the states or provinces —, then shows or hides each row, disabling a hidden field so that it is not sent, writes
its label, marks it required, and makes the administrative area a choice or a text field. It dispatches `changed`
with the format. Only the answer for the country still chosen is applied, so a slow answer for a country picked
before never overwrites the fields of the one picked after; a request that fails dispatches `failed` with the error.

The form gives the controller the endpoint's URL with the current language in its `locale` query parameter, and
`recipient=1` with `with_recipient`. An answer for a URL that names its language is public and cached a day, so a
shared cache keeps one answer per language; one without it is in the request's language, which may come from a
session, and is private.

On submission, an address keeps only what the country submitted writes. When a person changes the country of an
address being edited, the hidden fields of the previous country, which the browser does not send, are dropped
instead of being kept at their old values; a recipient's field the form does not show is kept where the country
writes it. A violation on a field the form does not show goes to the form itself.
