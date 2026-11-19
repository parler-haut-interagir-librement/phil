<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Application;

use Override;
use Phil\LicensingBundle\Application\Event\ExportChecking;
use Phil\LicensingBundle\Application\Exception\ExportBlocked;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\LicensingBundle\Tests\Unit\Support\InMemoryDeclarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(ExportGuard::class)]
#[CoversClass(ExportChecking::class)]
#[CoversClass(ExportBlocked::class)]
final class ExportGuardTest extends TestCase
{
    private InMemoryDeclarations $declarations;

    #[Override]
    protected function setUp(): void
    {
        $this->declarations = new InMemoryDeclarations();
    }

    public function testAWorkWithoutADeclarationIsNotExported(): void
    {
        $guard = $this->guard();
        $subject = SubjectReference::of('book', '42');

        self::assertEquals(new ComplianceReport([new Violation(ViolationCode::NoDeclaration)]), $guard->check($subject));

        try {
            $guard->assertExportable($this->licensable($subject));
            self::fail('The export was not blocked.');
        } catch (ExportBlocked $exportBlocked) {
            self::assertSame('The export of book "42" is blocked by its licensing: no_declaration.', $exportBlocked->getMessage());
            self::assertSame($subject, $exportBlocked->subject);
            self::assertTrue($exportBlocked->report->has(ViolationCode::NoDeclaration));
        }
    }

    public function testACompliantWorkIsExported(): void
    {
        $declaration = Declarations::complete();
        $this->declarations->add($declaration);
        $guard = $this->guard();

        self::assertTrue($guard->check($declaration->subject())->isCompliant());
        self::assertTrue($guard->check($this->licensable($declaration->subject()))->isCompliant());
        $guard->assertExportable($declaration->subject());
        $guard->assertExportable($this->licensable($declaration->subject()));
    }

    public function testEachCodeIsNamedOnce(): void
    {
        $declaration = Declarations::of(LicenseCatalog::OGL);
        $declaration->describe(null, null, 'Logos', 'Rules', Declarations::at());

        $this->declarations->add($declaration);

        $this->expectExceptionObject(new ExportBlocked($declaration->subject(), new ComplianceReport()));
        $this->expectExceptionMessageIsOrContains('The export of book "42" is blocked by its licensing: missing_license_text, missing_notice.');

        $this->guard()->assertExportable($declaration->subject());
    }

    public function testARepeatedCodeIsNamedOnce(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CC0);
        $declaration->attribute([
            Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, changes: 'None'),
            Attribution::of(ResourceKind::Image, 'Back', LicenseCatalog::CC_BY, changes: 'None'),
        ], Declarations::at());
        $this->declarations->add($declaration);

        $this->expectExceptionObject(new ExportBlocked($declaration->subject(), new ComplianceReport()));
        $this->expectExceptionMessageIsOrContains('The export of book "42" is blocked by its licensing: missing_credit, missing_resource_notice.');

        $this->guard()->assertExportable($declaration->subject());
    }

    public function testListenersAddTheirOwnViolations(): void
    {
        $declaration = Declarations::complete();
        $this->declarations->add($declaration);
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<ExportChecking> */
            public array $events = [];

            #[Override]
            public function dispatch(object $event): object
            {
                if ($event instanceof ExportChecking) {
                    $this->events[] = $event;
                    $event->addViolation(new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page'));
                }

                return $event;
            }
        };

        $report = $this->guard($dispatcher)->check($declaration->subject());

        self::assertEquals([new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page')], $report->violations);
        self::assertCount(1, $dispatcher->events);
        self::assertSame($declaration, $dispatcher->events[0]->declaration);
        self::assertTrue($dispatcher->events[0]->subject->equals($declaration->subject()));
        self::assertSame($report, $dispatcher->events[0]->report());

        $this->expectException(ExportBlocked::class);

        $this->guard($dispatcher)->assertExportable($declaration->subject());
    }

    private function guard(?EventDispatcherInterface $dispatcher = null): ExportGuard
    {
        return new ExportGuard($this->declarations, new LicenseCompliance(new LicenseCatalog()), $dispatcher);
    }

    private function licensable(SubjectReference $subject): Licensable
    {
        return new readonly class($subject) implements Licensable {
            public function __construct(private SubjectReference $subject)
            {
            }

            #[Override]
            public function licenseSubject(): SubjectReference
            {
                return $this->subject;
            }
        };
    }
}
