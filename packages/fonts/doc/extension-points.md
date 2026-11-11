# Extension points

The library is plain PHP: it declares no service and no port. What an application chooses goes through arguments.

| Point | How |
|---|---|
| The Brotli decoder of WOFF2 | The last argument of `FontFile::fromBytes()`, `FontFile::fromFile()` and `LicenceRegister::scan()`: `Brotli::decompressor()` for `ext-brotli`, `Brotli::decompressor('my_decode')` for another function, or any closure that takes the compressed bytes and the expected length and returns the bytes or `false` |
| The family a rule declares | `FontFaceRule::withFamily()`, for a face whose family is renamed on the site |
| The sources of a rule | `withUrl()` once per file, smallest format first; `withoutLocal()` to always download |
| Where a register lives | `LicenceRegister::toJson()` and `fromJson()`: the application stores the JSON where it wants |

A decoder that returns more or fewer bytes than the length it is given is refused with `InvalidFont`: the library
never trusts the decoder with the bounds of what it reads.
