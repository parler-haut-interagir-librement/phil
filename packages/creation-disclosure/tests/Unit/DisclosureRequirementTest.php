<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure\Tests\Unit;

use Phil\CreationDisclosure\CreationDisclosure;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\CreationDisclosure\DisclosureRequirement;
use Phil\CreationDisclosure\Exception\InvalidDisclosure;
use Phil\CreationDisclosure\GenerativeAi;
use Phil\CreationDisclosure\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DisclosureRequirement::class)]
#[CoversClass(InvalidDisclosure::class)]
final class DisclosureRequirementTest extends TestCase
{
    public function testARequirementNamesItsPartsInOrder(): void
    {
        $requirement = DisclosureRequirement::of([Part::Image, Part::Text, Part::Image]);

        self::assertSame([Part::Text, Part::Image], $requirement->parts);
        self::assertNull($requirement->atMost);
    }

    public function testTheNamedPartsFormAList(): void
    {
        self::assertSame([Part::Audio, Part::Code], DisclosureRequirement::of([Part::Code, Part::Audio])->parts);
    }

    public function testARequirementNamesAPart(): void
    {
        $this->expectException(InvalidDisclosure::class);
        $this->expectExceptionMessageIsOrContains('A requirement names at least one part of the work.');

        DisclosureRequirement::of([]);
    }

    public function testEveryRequiredPartIsDeclared(): void
    {
        $requirement = DisclosureRequirement::of([Part::Text, Part::Image]);
        $entry = CreationDisclosure::of(Part::Text, Part::Image, Part::Code)->declare(Part::Image, DigitalSourceType::TrainedAlgorithmicMedia);

        self::assertSame([Part::Text], $requirement->unmet($entry));
        self::assertFalse($requirement->isMetBy($entry));
        self::assertTrue($requirement->isMetBy($entry->declare(Part::Text, DigitalSourceType::HumanEdits)));
    }

    public function testAnAiFreeContestRefusesWhatMayHoldGeneratedContent(): void
    {
        $contest = DisclosureRequirement::of([Part::Text, Part::Image, Part::Audio], GenerativeAi::None);
        $entry = CreationDisclosure::of()
            ->declare(Part::Text, DigitalSourceType::HumanEdits)
            ->declare(Part::Image, DigitalSourceType::Composite)
            ->declare(Part::Audio, DigitalSourceType::DigitalCapture);

        self::assertSame([Part::Image], $contest->unmet($entry));
    }

    public function testALimitAcceptsWhatIsBelowIt(): void
    {
        $shop = DisclosureRequirement::of([Part::Image, Part::Text], GenerativeAi::Partial);
        $entry = CreationDisclosure::of()
            ->declare(Part::Text, DigitalSourceType::TrainedAlgorithmicMedia)
            ->declare(Part::Image, DigitalSourceType::CompositeWithTrainedAlgorithmicMedia);

        self::assertSame([Part::Text], $shop->unmet($entry));
    }
}
