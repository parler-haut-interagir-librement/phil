<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Traits;

use Phil\Enums\Tests\Fixture\MarkupValueEnum;
use Phil\Enums\Tests\Fixture\SelectablePureEnum;
use Phil\Enums\Tests\Fixture\StringBackedEnum;
use Phil\Enums\Traits\AsStringSelectableEnumTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversTrait(AsStringSelectableEnumTrait::class)]
final class AsStringSelectableEnumTraitTest extends TestCase
{
    #[Test]
    public function stringOptionsWithCustomCallback(): void
    {
        $result = StringBackedEnum::stringOptions(
            static fn (string $name, string $value): string => "{$name}:{$value}",
            ', ',
        );

        self::assertSame('DRAFT:draft, PUBLISHED:published, ARCHIVED:archived', $result);
    }

    /**
     * One option per line: the default glue is a new line, not a backslash and an `n`.
     */
    #[Test]
    public function stringOptionsDefaultCallbackGeneratesOneHtmlOptionPerLine(): void
    {
        self::assertSame(
            "<option value=\"draft\">Draft</option>\n<option value=\"published\">Published</option>\n<option value=\"archived\">Archived</option>",
            StringBackedEnum::stringOptions(),
        );
    }

    /**
     * A pure enum has no value: its name stands in, for the value and for the callback.
     */
    #[Test]
    public function aPureEnumIsWrittenOutByName(): void
    {
        self::assertSame(
            "<option value=\"IN_TRANSIT\">In transit</option>\n<option value=\"OnHold\">On hold</option>\n<option value=\"ACTIVE\">Active</option>",
            SelectablePureEnum::stringOptions(),
        );
        self::assertSame(
            'IN_TRANSIT=IN_TRANSIT|OnHold=OnHold|ACTIVE=ACTIVE',
            SelectablePureEnum::stringOptions(static fn (string $name, string $value): string => $name.'='.$value, '|'),
        );
    }

    #[Test]
    public function theDefaultOptionsEscapeTheValueForHtml(): void
    {
        self::assertSame(
            "<option value=\"x&quot;&gt;&lt;script&gt;\">Quoted</option>\n<option value=\"Tom &amp; Jerry&apos;s\">Ampersand</option>",
            MarkupValueEnum::stringOptions(),
        );
    }

    #[Test]
    public function aCallbackOfYourOwnIsPrintedAsItReturns(): void
    {
        self::assertSame(
            "x\"><script>|Tom & Jerry's",
            MarkupValueEnum::stringOptions(static fn (string $name, string $value): string => $value, '|'),
        );
    }

    /**
     * PHP takes any byte above 0x7F in a name, so a name may not be UTF-8: it cannot be split into words, and is
     * written out whole, the bytes that are not characters replaced.
     */
    #[Test]
    public function aNameThatIsNotUtf8IsWrittenOutWhole(): void
    {
        $enum = __NAMESPACE__.'\\NotUtf8Enum';

        if (!enum_exists($enum)) {
            // A source file of its own, since no editor keeps such a byte in one.
            $file = tempnam(sys_get_temp_dir(), 'enum');
            self::assertIsString($file);
            file_put_contents($file, '<?php namespace Phil\\Enums\\Tests\\Unit\\Traits; enum NotUtf8Enum { use \\Phil\\Enums\\Traits\\AsStringSelectableEnumTrait; case Caf'."\xFF".'; }');

            try {
                require $file;
            } finally {
                unlink($file);
            }
        }

        self::assertTrue(enum_exists($enum));
        self::assertSame('<option value="Caf'."\u{FFFD}".'">Caf?</option>', $enum::stringOptions());
    }
}
