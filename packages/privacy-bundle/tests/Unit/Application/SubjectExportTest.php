<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubjectExport::class)]
#[CoversClass(MisconfiguredPrivacy::class)]
final class SubjectExportTest extends TestCase
{
    public function testItHasOneSectionPerModuleSortedByName(): void
    {
        $export = new SubjectExport([new FixedExporter('newsletter', ['lists' => ['weekly']]), new FixedExporter('identity', ['email' => 'a@example.com'])]);

        self::assertSame(
            [
                'identity' => ['subject' => 's-1', 'email' => 'a@example.com'],
                'newsletter' => ['subject' => 's-1', 'lists' => ['weekly']],
            ],
            $export->export(DataSubject::fromString('s-1')),
        );
    }

    public function testNoModuleMeansAnEmptyExport(): void
    {
        self::assertSame([], new SubjectExport([])->export(DataSubject::fromString('s-1')));
    }

    public function testTwoModulesUnderOneNameAreRefused(): void
    {
        $this->expectException(MisconfiguredPrivacy::class);
        $this->expectExceptionMessageIsOrContains('"identity"');

        new SubjectExport([new FixedExporter('identity', []), new FixedExporter('identity', [])]);
    }
}
