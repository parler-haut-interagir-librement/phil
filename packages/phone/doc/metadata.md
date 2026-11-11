# Metadata

The numbering plans are those of [libphonenumber](https://github.com/google/libphonenumber),
`resources/PhoneNumberMetadata.xml`, under the Apache License 2.0. `Metadata\MetadataImporter` turns the XML into PHP
files in `data/`: one per country, `001-<code>.php` for the numbers of a country code that belong to no country, and
`country-codes.php`, the countries of each code with the main one first. The release imported is in `data/VERSION`.

On the way, patterns lose the whitespace they are laid out with, each format gets its national prefix rule with
`$NP` and `$FG` replaced, and possible lengths are expanded; the general description's lengths are the union of the
types'. The library reads only what it uses: no carrier codes, no short numbers, no example numbers.

## Updating

1. Download `resources/PhoneNumberMetadata.xml` at a release tag of libphonenumber.
2. `php bin/import-metadata.php path/to/PhoneNumberMetadata.xml`: `data/` is replaced. Every territory is read
   before anything is written, each file is written beside its name and renamed over it, and the plans the new
   metadata no longer has are removed last: metadata that is not libphonenumber's, or a file that cannot be written,
   leaves the data whole, and the import fails with an `InvalidMetadata`.
3. Write the tag in `data/VERSION`, and in the CHANGELOG.
4. Run the tests: a plan that changed may change a number of the tests, which then follow the plan.
