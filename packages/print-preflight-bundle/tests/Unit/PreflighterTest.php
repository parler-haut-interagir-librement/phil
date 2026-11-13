<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Rule;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\AsPreflightCheck;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preflighter::class)]
#[CoversClass(AsPreflightCheck::class)]
final class PreflighterTest extends TestCase
{
    public function testChecksAgainstTheProfileNamedOrTheDefault(): void
    {
        $profiles = new PrintProfiles(['any' => new PrintProfile(bleed: Length::pt(0)), 'booklet' => new PrintProfile(bleed: Length::pt(0), pageMultiple: 4)], 'any');
        $preflighter = new Preflighter($profiles);
        $document = new Document([new Page(Size::a5())]);

        self::assertSame([], $preflighter->check($document)->findings);
        self::assertSame([Rule::PageCountMultiple->value], array_map(static fn (Finding $finding): string => $finding->code, $preflighter->check($document, 'booklet')->findings));
        self::assertSame($profiles, $preflighter->profiles());
    }

    public function testRunsTheChecksOfTheApplicationAfterTheOthers(): void
    {
        $check = static fn (string $code): Check => new readonly class($code) implements Check {
            public function __construct(private string $code)
            {
            }

            public function check(Document $document, PrintProfile $profile): array
            {
                return [new Finding(Severity::Notice, $this->code, 'Checked.')];
            }
        };
        $preflighter = new Preflighter(new PrintProfiles(['booklet' => new PrintProfile(bleed: Length::pt(0), pageMultiple: 4)], 'booklet'), [$check('house.one'), $check('house.two')]);

        self::assertSame([Rule::PageCountMultiple->value, 'house.one', 'house.two'], array_map(static fn (Finding $finding): string => $finding->code, $preflighter->check(new Document([new Page(Size::a5())]))->findings));
        self::assertNotNull(new AsPreflightCheck());
    }
}
