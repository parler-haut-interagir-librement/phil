<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Plan;

use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Exception\PlanChanged;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\Reason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reason::class)]
#[CoversClass(CellError::class)]
#[CoversClass(MappingIncomplete::class)]
#[CoversClass(PlanChanged::class)]
#[CoversClass(SourceRefused::class)]
#[CoversClass(InvalidProfile::class)]
final class ReasonTest extends TestCase
{
    public function testEachReasonHasACodeAndASentence(): void
    {
        $messages = [];

        foreach (Reason::cases() as $reason) {
            self::assertMatchesRegularExpression('/^[a-z]+(-[a-z]+)*$/', $reason->value);
            $messages[$reason->value] = $reason->message();
        }

        self::assertSame([
            'required' => 'is required',
            'not-an-integer' => 'is not a whole number',
            'not-a-decimal' => 'is not a decimal number',
            'not-a-boolean' => 'is not yes or no',
            'not-a-date' => 'is not a date written YYYY-MM-DD',
            'not-an-email' => 'is not an email address',
            'too-long' => 'is too long',
            'not-allowed' => 'is not one of the allowed values',
            'too-many-cells' => 'the row has more cells than the header',
            'malformed-row' => 'the row has text after a closing quote',
            'duplicate-key' => 'repeats the key of another row',
            'self-reference' => 'refers to its own row',
            'unknown-reference' => 'refers to nothing known',
            'reference-cycle' => 'is part of a cycle of references',
            'reference-rejected' => 'refers to a row that is refused',
            'date-order' => 'is before its start date',
            'refused-by-target' => 'is refused',
        ], $messages);
    }

    public function testAnErrorKeepsItsParts(): void
    {
        $error = new CellError('code', Reason::TooLong, 'at most 3 characters', 7);

        self::assertSame(['code', Reason::TooLong, 'at most 3 characters', 7], [$error->column, $error->reason, $error->detail, $error->relatedLine]);
        self::assertSame('Code: is too long (line 7) — at most 3 characters', $error->message('Code'));
        self::assertSame('Row: is too long', new CellError(null, Reason::TooLong)->message(null));
        self::assertEquals(new CellError('a', Reason::RefusedByTarget, 'b'), CellError::refusedByTarget('a', 'b'));
    }

    public function testTheExceptionsSayWhatIsRefused(): void
    {
        $missing = MappingIncomplete::missing(['email']);

        self::assertSame(['email'], $missing->columns);
        self::assertSame('No header is mapped to the required columns: email.', $missing->getMessage());
        self::assertSame('x', SourceRefused::because('x')->getMessage());
        self::assertSame('y', InvalidProfile::because('y')->getMessage());
        self::assertSame('The plan imports "a", the target writes "b".', PlanChanged::forTarget('a', 'b')->getMessage());
        self::assertSame('The plan differs from the one previewed: preview the file again before writing it.', PlanChanged::sincePreview()->getMessage());
    }
}
