# The license finder

The finder helps an author choose the license of a work from what they allow, and writes custom terms when no license
of the catalog fits.

## The decision tree

`LicenseFinder` is pure PHP: no request, no form, no translation. It reads `LicenseChoices`, the answers given so far,
and says which `Question` comes next, or recommends a license once nothing is left to ask.

| Question | Answers |
|---|---|
| `adapted_material` | The share-alike license of the material the work adapts, or none. |
| `attribution` | Whether whoever reuses the work must credit its creators. |
| `commercial_use` | Whether the work may be used for commercial purposes. |
| `modifications` | `allowed`, `share_alike` or `forbidden`: what others may do with adaptations. |

Material under a share-alike license — the ORC License, CC BY-SA 4.0, CC BY-NC-SA 4.0, the Open Game License 1.0a,
and each share-alike license of the application — imposes its license: the recommendation is that license, marked
`imposed`, and the other questions are skipped. Otherwise:

| Credit | Commercial use | Adaptations | Recommendation |
|---|---|---|---|
| required | allowed | allowed | `cc-by-4.0` |
| required | allowed | share-alike | `cc-by-sa-4.0` |
| required | allowed | forbidden | `cc-by-nd-4.0` |
| required | forbidden | allowed | `cc-by-nc-4.0` |
| required | forbidden | share-alike | `cc-by-nc-sa-4.0` |
| required | forbidden | forbidden | `cc-by-nc-nd-4.0` |
| not required | allowed | allowed | `cc0-1.0` |
| not required | any other answer | | `custom`, with `CustomTerms` |

```php
$finder = new LicenseFinder($catalog);
$choices = LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(false)->withCommercialUse(false)->withModifications(Modifications::Allowed);

$finder->nextQuestion($choices);              // null: every question is answered
$recommendation = $finder->recommend($choices);
$recommendation->license;                     // 'custom'
$recommendation->terms?->clauses;             // Share, NoCommercialUse, Adaptations, NoAttribution, NoWarranty
```

`recommend()` throws `InvalidLicensing` while a question is unanswered, or for an adapted license that is not a
share-alike license of the catalog.

## The custom license

`CustomTerms` lists its clauses in reading order: sharing, commercial use, adaptations, credit, warranty.
`CustomTermsWriter` writes them in the reader's language — a heading naming the holder, one paragraph per clause,
`terms.<clause>` of the domain `phil_licensing` —, and the declaration keeps the text:

```php
$declaration = LicenseDeclaration::declare($subject, $catalog->get($recommendation->license), $now);

if ($recommendation->terms instanceof CustomTerms) {
    $declaration->includeLicenseText($writer->write($recommendation->terms, 'Example Press', 'en'), $now);
}
```

The terms are a plain-language starting point the author reviews; they are not legal advice.

## The form in steps

With `symfony/form` and `symfony/validator`, `LicenseFinderType` asks one question a page on the form flow of
Symfony. Each step is posted, the answers are kept between steps in the session, and the buttons "Previous", "Next"
and "Finish" move through them: no JavaScript is needed. Share-alike material skips the other steps.

```php
public function __invoke(Request $request, LicenseFinder $finder): Response
{
    $flow = $this->createForm(LicenseFinderType::class, new LicenseFinderData())->handleRequest($request);

    if ($flow->isSubmitted() && $flow->isValid() && $flow->isFinished()) {
        return $this->render('licensing/recommendation.html.twig', ['recommendation' => $finder->recommend($flow->getData()->choices())]);
    }

    return $this->render('licensing/finder.html.twig', ['form' => $flow->getStepForm()->createView()]);
}
```

The option `data_storage` replaces the session, with any storage of the form flow. `LicenseFinderData` holds the
current step and one property per question; `choices()` gives what the finder reads.

## The templates

`@PhilLicensing/finder/form.html.twig` shows the current step, with `form`: the steps as an ordered list, the current
one marked `aria-current="step"`, then the question. `phil_licensing_recommendation(recommendation, holder, locale)`
renders `@PhilLicensing/finder/recommendation.html.twig`: the license, the link to its text, and the custom terms.
`phil_licensing_terms(terms, holder, locale)` gives the text of custom terms. The markup is semantic HTML, with the
classes `phil-licensing-finder`, `phil-licensing-recommendation` and `phil-licensing-terms`.
