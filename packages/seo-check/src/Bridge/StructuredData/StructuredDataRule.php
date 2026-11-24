<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Bridge\StructuredData;

use Override;
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Validation\Severity as IssueSeverity;
use Phil\StructuredData\Validation\Validator;

/**
 * The JSON-LD of the page is readable and valid, by the `Validator` of phil/structured-data: an error of the validator
 * is an error, a warning a warning, an element that cannot be read an error. A page without structured data is an
 * information: rich results need it.
 *
 * The message of each issue is the validator's, in English, given as the `{message}` placeholder; its code, the
 * `{code}` placeholder, lets a translation write its own.
 */
final readonly class StructuredDataRule implements SeoRule
{
    public const string NAME = 'structured-data';

    public function __construct(
        private Validator $validator = new Validator(),
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedPage $page): iterable
    {
        try {
            $extraction = JsonLdReader::fromHtml($page->primary()->page->html);
        } catch (InvalidStructuredData $invalidStructuredData) {
            yield new Finding(self::NAME, Severity::Warning, new Message('structured-data.unreadable', ['reason' => $invalidStructuredData->getMessage()]));

            return;
        }

        foreach ($extraction->errors as $error) {
            yield new Finding(self::NAME, Severity::Error, new Message('structured-data.unreadable', ['reason' => $error]));
        }

        if (0 === $extraction->blocks) {
            // An empty graph has nothing to validate: the loop below yields nothing.
            yield new Finding(self::NAME, Severity::Info, new Message('structured-data.none'));
        }

        foreach ($this->validator->validate($extraction->graph)->issues as $issue) {
            yield new Finding(
                self::NAME,
                IssueSeverity::Error === $issue->severity ? Severity::Error : Severity::Warning,
                new Message('structured-data.issue', ['code' => $issue->code, 'node' => $issue->node, 'message' => $issue->message]),
                $issue->node,
            );
        }
    }
}
