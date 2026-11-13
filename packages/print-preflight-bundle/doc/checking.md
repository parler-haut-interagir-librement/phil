# Checking

## `Preflighter`

```php
$report = $preflighter->check($document);             // the default profile
$report = $preflighter->check($document, 'poster');   // a profile by name
```

`check(Document $document, ?string $profile = null)` runs the library's checks, then the application's, and returns the
library's `Report`: `passes()` when no finding is an error, `isClean()` when there is no warning either, `forPage()`,
`toArray()`. A name the configuration does not declare throws `UnknownProfile`. `profiles()` gives the `PrintProfiles`,
to list the names in a form.

## Checks of the application

A class implementing `Phil\PrintPreflight\Check\Check`, marked `#[AsPreflightCheck]` and autoconfigured, runs in every
preflight after the built-in checks:

```php
#[AsPreflightCheck]
final readonly class TotalInkCoverage implements Check
{
    public function check(Document $document, PrintProfile $profile): array
    {
        // return Finding values with a code of the application, such as "house.ink-coverage"
    }
}
```

Its findings take the severities and the ignored codes of the profile like the built-in ones; the services are tagged
`phil_print_preflight.check`.

## The command

```sh
bin/console phil:print-preflight:check measured.json [--profile=booklet] [--format=json]
```

| Option | Effect |
|---|---|
| `--profile`, `-p` | The profile; the default one otherwise |
| `--format`, `-f` | `text` (default): one line per finding — severity, page or `document`, code, subject, message — then the counts; `json`: the report's `toArray()` |

| Exit code | When |
|---|---|
| 0 | No finding is an error |
| 1 | A finding is an error |
| 2 | The file cannot be read, is not a measured document, or names an unknown profile; the reason is printed |

The findings are printed as they are, so an identifier with `<` in it is shown, not taken for console markup.
