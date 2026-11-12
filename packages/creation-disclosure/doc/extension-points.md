# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library has no interface to implement: the vocabulary is IPTC's, and every type is a value.

| To | Do |
|---|---|
| Label the types in another language | Translate the codes in the application's catalogue, keyed by `DigitalSourceType::value`; `label()` is the English of IPTC |
| Combine the views of several works | Call `GenerativeAi::combine()` with their views |
| Store a disclosure | Keep `toArray()` in a JSON column and read it back with `fromArray()` |
| Ask for declarations | Build a `DisclosureRequirement` per contest, shop or catalogue, from the application's configuration |
