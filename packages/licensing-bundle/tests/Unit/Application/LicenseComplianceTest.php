<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Application;

use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseCompliance::class)]
#[CoversClass(ComplianceReport::class)]
#[CoversClass(Violation::class)]
#[CoversClass(ViolationCode::class)]
final class LicenseComplianceTest extends TestCase
{
    public function testACompleteDeclarationComplies(): void
    {
        $report = $this->check(Declarations::complete());

        self::assertTrue($report->isCompliant());
        self::assertSame([], $report->violations);
    }

    public function testTheOrcLicenseAsksForTheReservedMaterial(): void
    {
        self::assertEquals([new Violation(ViolationCode::MissingReservedMaterial, 'orc')], $this->check(Declarations::of(LicenseCatalog::ORC))->violations);
    }

    public function testTheOpenGameLicenseAsksForItsTextAndItsDeclarations(): void
    {
        $declaration = Declarations::of(LicenseCatalog::OGL);

        self::assertEquals([
            new Violation(ViolationCode::MissingLicenseText, 'ogl-1.0a'),
            new Violation(ViolationCode::MissingProductIdentity, 'ogl-1.0a'),
            new Violation(ViolationCode::MissingDesignatedContent, 'ogl-1.0a'),
            new Violation(ViolationCode::MissingNotice, 'ogl-1.0a'),
        ], $this->check($declaration)->violations);

        $declaration->describe('Copyright 2026, Example Press.', null, 'Names and logos.', 'The rules of chapter 3.', Declarations::at());
        $declaration->includeLicenseText('The license text, as published.', Declarations::at());
        self::assertTrue($this->check($declaration)->isCompliant());
    }

    public function testACustomLicenseAsksForItsTermsUnlessAnAddressGivesThem(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CUSTOM);
        self::assertEquals([new Violation(ViolationCode::MissingLicenseText, 'custom')], $this->check($declaration)->violations);

        $declaration->includeLicenseText('Our terms.', Declarations::at());
        self::assertTrue($this->check($declaration)->isCompliant());

        $catalog = new LicenseCatalog([
            License::of('located', 'Located', [Requirement::LicenseNotice], 'https://example.com/terms'),
            License::of('referenced', 'Referenced', [Requirement::LicenseNotice], reference: 'Registry 12'),
            License::of('silent', 'Silent', []),
        ]);

        foreach (['located', 'referenced', 'silent'] as $code) {
            $declaration->relicense($catalog->get($code), Declarations::at());
            $declaration->includeLicenseText(null, Declarations::at());
            self::assertTrue(new LicenseCompliance($catalog)->check($declaration)->isCompliant(), $code);
        }
    }

    public function testCreativeCommonsAttributionAsksForTheCopyrightNoticeOfTheWork(): void
    {
        foreach ([LicenseCatalog::CC_BY, LicenseCatalog::CC_BY_SA] as $code) {
            self::assertEquals([new Violation(ViolationCode::MissingNotice, $code)], $this->check(Declarations::of($code))->violations);

            $declaration = Declarations::of($code);
            $declaration->describe('© 2026 Example Press', null, null, null, Declarations::at());
            self::assertTrue($this->check($declaration)->isCompliant(), $code);
        }
    }

    public function testCc0AsksNothingOfTheWorkItself(): void
    {
        self::assertTrue($this->check(Declarations::of(LicenseCatalog::CC0))->isCompliant());
    }

    public function testACreativeCommonsResourceSaysWhatChangedAndKeepsItsNotice(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CC0);
        $declaration->attribute([
            Attribution::of(ResourceKind::Image, 'Silent', LicenseCatalog::CC_BY, 'Jane'),
            Attribution::of(ResourceKind::Image, 'Unchanged', LicenseCatalog::CC_BY, 'Jane', notice: '© Jane', changes: 'None'),
            Attribution::of(ResourceKind::Text, 'Open content', LicenseCatalog::ORC, 'Example Games'),
        ], Declarations::at());

        self::assertEquals([
            new Violation(ViolationCode::MissingResourceNotice, 'cc-by-4.0', 0, 'Silent'),
            new Violation(ViolationCode::MissingChanges, 'cc-by-4.0', 0, 'Silent'),
            new Violation(ViolationCode::ShareAlikeConflict, 'orc', 2, 'Open content'),
        ], $this->check($declaration)->violations);
    }

    public function testEachResourceIsCheckedAgainstItsLicense(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CC_BY);
        $declaration->describe('© 2026 Example Press', null, null, null, Declarations::at());
        $declaration->attribute([
            Attribution::of(ResourceKind::Image, 'Uncredited', LicenseCatalog::CC_BY, changes: 'None'),
            Attribution::of(ResourceKind::Text, 'Open content', LicenseCatalog::OGL, 'Example Games'),
            Attribution::of(ResourceKind::Map, 'Shared map', LicenseCatalog::CC_BY_SA, notice: 'Map by Example', changes: 'Cropped'),
            Attribution::of(ResourceKind::Font, 'Mystery font', 'nope'),
            Attribution::of(ResourceKind::Image, 'Public domain', LicenseCatalog::CC0),
            Attribution::of(ResourceKind::Image, 'Credited', LicenseCatalog::CC_BY, 'Jane', notice: '© Jane', changes: 'None'),
        ], Declarations::at());

        self::assertEquals([
            new Violation(ViolationCode::MissingCredit, 'cc-by-4.0', 0, 'Uncredited'),
            new Violation(ViolationCode::MissingResourceNotice, 'cc-by-4.0', 0, 'Uncredited'),
            new Violation(ViolationCode::MissingResourceNotice, 'ogl-1.0a', 1, 'Open content'),
            new Violation(ViolationCode::ShareAlikeConflict, 'ogl-1.0a', 1, 'Open content'),
            new Violation(ViolationCode::ShareAlikeConflict, 'cc-by-sa-4.0', 2, 'Shared map'),
            new Violation(ViolationCode::UnknownLicense, 'nope', 3, 'Mystery font'),
        ], $this->check($declaration)->violations);
    }

    public function testAShareAlikeResourceFitsAWorkUnderItsLicense(): void
    {
        $declaration = Declarations::of(LicenseCatalog::CC_BY_SA);
        $declaration->describe('© 2026 Example Press', null, null, null, Declarations::at());
        $declaration->attribute([Attribution::of(ResourceKind::Map, 'Shared map', LicenseCatalog::CC_BY_SA, 'Example', notice: '© Example', changes: 'None')], Declarations::at());

        self::assertTrue($this->check($declaration)->isCompliant());
    }

    public function testALicenseRemovedFromTheCatalogIsAViolation(): void
    {
        $house = License::of('house', 'House', []);
        $declaration = LicenseDeclaration::declare(Declarations::complete()->subject(), $house, Declarations::at());
        $declaration->attribute([Attribution::of(ResourceKind::Image, 'Uncredited', LicenseCatalog::CC_BY, changes: 'None')], Declarations::at());

        $report = $this->check($declaration);

        self::assertEquals([
            new Violation(ViolationCode::UnknownLicense, 'house'),
            new Violation(ViolationCode::MissingCredit, 'cc-by-4.0', 0, 'Uncredited'),
            new Violation(ViolationCode::MissingResourceNotice, 'cc-by-4.0', 0, 'Uncredited'),
        ], $report->violations);
        self::assertTrue($report->has(ViolationCode::UnknownLicense));
        self::assertTrue($report->has(ViolationCode::MissingCredit));
        self::assertFalse($report->has(ViolationCode::NoDeclaration));
        self::assertTrue(new LicenseCompliance(new LicenseCatalog([$house]))->check(Declarations::of(LicenseCatalog::CC0))->isCompliant());
    }

    public function testAViolationAndItsMessageKey(): void
    {
        $violation = new Violation(ViolationCode::NoDeclaration);

        self::assertNull($violation->license);
        self::assertNull($violation->resource);
        self::assertNull($violation->resourceTitle);
        self::assertSame('violation.no_declaration', $violation->code->messageKey());
        self::assertEquals(new ComplianceReport([$violation]), new ComplianceReport()->with($violation));
    }

    private function check(LicenseDeclaration $declaration): ComplianceReport
    {
        return new LicenseCompliance(new LicenseCatalog())->check($declaration);
    }
}
