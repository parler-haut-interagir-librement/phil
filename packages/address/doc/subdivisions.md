# Subdivisions

```php
Subdivisions::of('CA');                     // the provinces and territories
Subdivisions::of('BR', 'AC');               // the cities of Acre
Subdivisions::find('Québec', 'CA');         // a Subdivision, or null
```

A `Subdivision` has an identifier among its siblings, the `code` an address carries — `QC`, or the name where the
country has no codes —, a `localCode`, a `name` and a `localName` in the language of its `locale` — `fr` for Canada's
—, an ISO code where the data have one, the pattern its postal codes start with, and whether it has children.

`find()` matches a value by identifier, code or name, local ones included, whatever the case: `qc`, `Quebec` and
`Québec` are the same province. `keys()` gives what a subdivision is found by, in lowercase. Each list is indexed by
those keys the first time a value is looked up in it, so a lookup among Minas Gerais's 853 cities costs what it costs
among Canada's 13 provinces; where two subdivisions of a list share a key, the first one is found. `nameIn($locale)`
gives the local name in its language, the name otherwise.

Lists below the first level are named as commerceguys/addressing names them — `BR-AC`, or a hash of the parents
further down — and found by the parents' identifiers.
