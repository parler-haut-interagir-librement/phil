<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Application\Exception\ExportBlocked;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Infrastructure\PagedMedia\GuardPrintedWork;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\LicensingBundle\Tests\Unit\Support\InMemoryDeclarations;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\PrintSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GuardPrintedWork::class)]
final class GuardPrintedWorkTest extends TestCase
{
    public function testACompliantWorkIsPrinted(): void
    {
        $declarations = new InMemoryDeclarations();
        $declarations->add(Declarations::complete());

        new GuardPrintedWork($this->guard($declarations))(new PrintStarting(new PrintSubject('book', '42')));

        $this->expectNotToPerformAssertions();
    }

    public function testAWorkWithoutADeclarationIsNotPrinted(): void
    {
        try {
            new GuardPrintedWork($this->guard(new InMemoryDeclarations()))(new PrintStarting(new PrintSubject('book', '7')));
            self::fail('The print was not stopped.');
        } catch (ExportBlocked $exportBlocked) {
            self::assertTrue($exportBlocked->subject->equals(SubjectReference::of('book', '7')));
            self::assertTrue($exportBlocked->report->has(ViolationCode::NoDeclaration));
        }
    }

    public function testAnUncreditedCreativeCommonsResourceStopsThePrint(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CC0);
        $declaration->attribute([Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY)], Declarations::at());

        $declarations = new InMemoryDeclarations();
        $declarations->add($declaration);

        try {
            new GuardPrintedWork($this->guard($declarations))(new PrintStarting(new PrintSubject('book', '42')));
            self::fail('The print was not stopped.');
        } catch (ExportBlocked $exportBlocked) {
            self::assertTrue($exportBlocked->report->has(ViolationCode::MissingCredit));
            self::assertTrue($exportBlocked->report->has(ViolationCode::MissingResourceNotice));
            self::assertTrue($exportBlocked->report->has(ViolationCode::MissingChanges));
        }
    }

    public function testAnUnlicensedSubjectIsPrintedUnchecked(): void
    {
        $guard = new GuardPrintedWork($this->guard(new InMemoryDeclarations()), ['invoice', 'ticket']);

        $guard(new PrintStarting(new PrintSubject('ticket', '7')));

        try {
            $guard(new PrintStarting(new PrintSubject('book', '7')));
            self::fail('The print was not stopped.');
        } catch (ExportBlocked $exportBlocked) {
            self::assertTrue($exportBlocked->report->has(ViolationCode::NoDeclaration));
        }
    }

    private function guard(InMemoryDeclarations $declarations): ExportGuard
    {
        return new ExportGuard($declarations, new LicenseCompliance(new LicenseCatalog()));
    }
}
