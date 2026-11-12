# phil/accessibility-check

**Catch what keeps readers out before a content is published: missing text alternatives, broken outlines, empty links, faint text.**

phil/accessibility-check checks a content at the moment it is published. It reads HTML — a whole page or the fragment
an editor produced — through the HTML parser of PHP, and UJO (Universal JSON Output) documents block by block, so that
an author is told which block and which field to fix. Each rule is a class with an identifier, the WCAG 2.2 success
criterion it tests, a severity and a message in English, French, Spanish, German, Italian and Portuguese: text
alternatives of images, the order of headings and the first-level heading, names of links and buttons, link texts that
say nothing, the contrast of the colours the content gives, header cells of tables, languages of pages and passages,
titles of frames, captions of videos, identifiers given twice, titles of drawings. The report says, rule by rule,
whether it passed, lists what was found with its location, and serializes to JSON.

It is for any PHP application that publishes what people write: a CMS, a blog, a wiki, a tool that lays out books or
game material. A check does not replace a test with people and assistive technologies; it stops the mistakes a machine
can see, every time, before readers meet them.

It is a plain PHP library built on the `dom`, `libxml` and `mbstring` extensions and
`symfony/translation`. With the packages it adapts to, it also gives Symfony applications a validator constraint, a
guard of `symfony/workflow` that holds back a publication, and a console command.

The design is small: `AccessibilityChecker` runs the rules a `Policy` keeps over an `HtmlTarget` or a `UjoTarget` and
returns a `Report` of `RuleResult`s and `Finding`s; a rule implements `HtmlRule`, `UjoRule` or both, and yields
`Violation`s; `symfony/translation` writes their messages, through `Translation\Messages`.

## Status

| Part | State |
|---|---|
| HTML read through `Dom\HTMLDocument`, limits of size, depth and elements, UTF-8 only | Implemented and tested |
| UJO documents read block by block, nested blocks included, fields that hold HTML parsed apart | Implemented and tested |
| 15 rules, with their WCAG 2.2 success criterion and severity | Implemented and tested |
| Contrast of WCAG computed from the colours of the content and of the tokens of the look | Implemented and tested |
| Messages in English, French, Spanish, German, Italian and Portuguese | Implemented and tested |
| Report with the outcome of each rule, locations and JSON | Implemented and tested |
| Symfony validator constraint `AccessibleContent` | Implemented and tested |
| Workflow guard `PublicationGuard` | Implemented and tested |
| Console command `phil:accessibility:check` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and the services of a Symfony application. |
| [Checking](doc/checking.md) | Checking HTML and UJO, the report, the findings and their locations, JSON. |
| [Rules](doc/rules.md) | The catalogue of the rules: what each checks, its criterion, its severity and its messages. |
| [Policy](doc/policy.md) | Limits, headings, contrast and colours, languages, generic link texts, rules turned off. |
| [Messages](doc/messages.md) | The translator, its six languages, the keys and their placeholders. |
| [Validator](doc/validator.md) | The `AccessibleContent` constraint. |
| [Publication guard](doc/publication-guard.md) | Holding back a publication of `symfony/workflow`. |
| [Console](doc/console.md) | The `phil:accessibility:check` command. |
| [Extension points](doc/extension-points.md) | Writing a rule, adding messages, the plain fields of UJO, the content of a subject. |
| [Exceptions](doc/exceptions.md) | `AccessibilityCheckException` and its three exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar, fuzzing and benchmark, and how to test with it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/accessibility-check
```

```php
use Phil\AccessibilityCheck\AccessibilityChecker;

$report = new AccessibilityChecker()->checkHtml(<<<'HTML'
    <h2>Opening hours</h2>
    <h4>Summer</h4>
    <img src="/uploads/IMG_2041.jpg">
    <p>Book a table <a href="/booking">here</a>.</p>
    <p style="color: #aaa">Closed on holidays.</p>
    HTML);

foreach ($report->findings as $finding) {
    printf("[%s] %s (WCAG %s) %s: %s\n", $finding->severity->value, $finding->rule, $finding->criterion->value,
        $finding->location->describe(), $report->message($finding));
}
// [error] image-alt (WCAG 1.1.1) line 3, img: The image /uploads/IMG_2041.jpg has no text alternative: …
// [warning] heading-order (WCAG 1.3.1) line 2, h4: This h4 heading follows an h2: a level is skipped.
// [warning] link-text (WCAG 2.4.4) line 4, p:nth-of-type(1) > a: The text of the link to /booking, "here", …
// [error] color-contrast (WCAG 1.4.3) line 5, p:nth-of-type(2): The text "Closed on holidays." has a contrast of 2.32:1 …

$report->passes();                 // false: an error was found
echo json_encode($report);         // the outcome of each rule and every finding
```

## Requirements

PHP 8.5 or later, with the `dom`, `libxml` and `mbstring` extensions; `phil/ujo` to check UJO documents; `symfony/validator`,
`symfony/workflow` and `symfony/console` for the constraint, the guard and the command.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
