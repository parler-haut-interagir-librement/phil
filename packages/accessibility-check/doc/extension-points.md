# Extension points

What an application implements, replaces or relies on.

## Writing a rule

A rule implements `Contract\Rule` — `id()`, `criterion()`, `severity()` — and `Contract\HtmlRule`, `Contract\UjoRule`
or both. It yields a `Violation` for each problem: a `Location`, a `Message`, and, when this case differs from the
rule, its own `Severity` or `SuccessCriterion`. It holds no state between two contents.

```php
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Violation;

final class NoMarquee implements HtmlRule
{
    public function id(): string
    {
        return 'no-marquee';
    }

    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::PauseStopHide;
    }

    public function severity(): Severity
    {
        return Severity::Error;
    }

    public function checkHtml(HtmlTarget $target): iterable
    {
        foreach ($target->elements('marquee', 'blink') as $element) {
            yield new Violation($target->locate($element), new Message('no-marquee.found', ['element' => $element->localName]));
        }
    }
}
```

`HtmlTarget` gives what a rule asks of the content: `elements(...$names)`, `identifiers()`, `locate()`,
`languageOf()`, `isHidden()`, `style()`, `hasRole()`, `accessibleName()`, `labelOf()`, `textOf()`, `ownText()`,
`svgTitle()`, and `policy`, `language` and `isFullDocument`. `UjoTarget` gives `blocks(...$types)` — each a
`BlockVisit` with its block, its pointer, its language and `locate()` —, `fragments(?BlockVisit)` — each HTML field as
an `HtmlTarget` located in its block —, and `field()` and `text()` to read the data.

`Rule\FragmentRule` is the base of a rule that reads both: it implements `checkUjo()` by calling `checkBlock()` for the
data of each block, then `checkHtml()` for each of its HTML fields.

The rules are given to the checker in the order they run — `new AccessibilityChecker($policy, [...DefaultRules::all(),
new NoMarquee()()])`, or a tagged iterator ([Installation](installation.md#in-a-symfony-application)). Two rules of one
identifier throw `InvalidPolicy`. Give the rule's messages to the translator ([Messages](messages.md)).

## The policy

`Policy` holds the limits, the headings, the contrast, the colours, the languages, the generic link texts and the
rules turned off or given another severity: see [Policy](policy.md).

## The translator

The checker takes a `TranslatorInterface` of `symfony/translation-contracts`: `Translation\Messages::translator()` by
default, to which an application adds the messages of a locale or of a rule, or the translator of the application with
`Messages::resources()` loaded in the domain `phil_accessibility_check`: see [Messages](messages.md).

## The plain fields of UJO

`Ujo\PlainFields` names, by block type, the fields of the data that never hold HTML — `code` of a `code` block, `latex`
and `mathml` of `math`, every field of `chart` (`*`)… — which the checker does not parse even when they hold a `<`. A
site that registers block types of its own gives the checker its plain fields, replacing the defaults of each type it
names:

```php
use Phil\AccessibilityCheck\Ujo\PlainFields;

$checker = new AccessibilityChecker(plainFields: new PlainFields(['recipe' => ['ingredients'], 'chart' => ['*']]));
```

## The content of a subject

`Contract\SubjectContent` tells the `PublicationGuard` the HTML or the UJO document of the subject of a workflow: see
[Publication guard](publication-guard.md).

## The validator and the command

`Validator\AccessibleContentValidator` and `Console\CheckCommand` take the `AccessibilityChecker` they check with:
register them as services with the application's checker.

## The report

`Report`, `RuleResult`, `Finding`, `Location`, `Message`, `Severity`, `SuccessCriterion` and `ConformanceLevel` are
values an application reads. The identifiers of the rules, the keys of the messages, the values of `Severity` and the
numbers of `SuccessCriterion` are stable, so that a report can be stored and read again by them.
