# Data

The formats and subdivisions are those of [commerceguys/addressing](https://github.com/commerceguys/addressing),
under the MIT License; that library first generated them from Google's Address Data Service and maintains them.
`Data\DataImporter` writes them as PHP files in `data/`: `formats/<country>.php`, `formats/ZZ.php` for the countries
without a format of their own, and `subdivisions/<list>.php`, named as commerceguys/addressing names its files. The
release imported is in `data/VERSION`; the library reads them only, at run time, and never depends on
commerceguys/addressing.

Country names are not imported: symfony/intl gives them, in every language it has.

## Updating

1. Check out commerceguys/addressing at a release tag.
2. `php bin/import-data.php path/to/addressing`: its formats are read through its own repository class, loaded from
   that checkout only. `DataImporter` writes every file in a staging folder first and only then puts it in place of
   `data/formats/` and `data/subdivisions/`: data it refuses halfway leave the data already there untouched.
3. Write the tag in `data/VERSION`, and in the CHANGELOG.
4. Run the tests: a format that changed may change a test, which then follows the data.
