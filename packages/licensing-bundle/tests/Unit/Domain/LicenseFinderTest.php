<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Domain;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Finder\CustomTerms;
use Phil\LicensingBundle\Domain\Finder\LicenseChoices;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Domain\Finder\Modifications;
use Phil\LicensingBundle\Domain\Finder\Question;
use Phil\LicensingBundle\Domain\Finder\Recommendation;
use Phil\LicensingBundle\Domain\Finder\TermsClause;
use Phil\LicensingBundle\Domain\ValueObject\License;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseFinder::class)]
#[CoversClass(LicenseChoices::class)]
#[CoversClass(Recommendation::class)]
#[CoversClass(CustomTerms::class)]
final class LicenseFinderTest extends TestCase
{
    public function testTheQuestionsComeInOrder(): void
    {
        $finder = new LicenseFinder(new LicenseCatalog());
        $choices = LicenseChoices::none();

        self::assertSame(Question::AdaptedMaterial, $finder->nextQuestion($choices));
        $choices = $choices->withAdaptedLicense(null);
        self::assertSame(Question::Attribution, $finder->nextQuestion($choices));
        $choices = $choices->withAttribution(true);
        self::assertSame(Question::CommercialUse, $finder->nextQuestion($choices));
        $choices = $choices->withCommercialUse(false);
        self::assertSame(Question::Modifications, $finder->nextQuestion($choices));
        $choices = $choices->withModifications(Modifications::ShareAlike);
        self::assertNull($finder->nextQuestion($choices));

        self::assertSame([true, null, true, false, Modifications::ShareAlike], [$choices->adaptedMaterialAnswered, $choices->adaptedLicense, $choices->attribution, $choices->commercialUse, $choices->modifications]);
    }

    public function testAnAnswerKeepsTheOthers(): void
    {
        $choices = LicenseChoices::none()->withModifications(Modifications::Forbidden)->withCommercialUse(true)->withAttribution(false)->withAdaptedLicense(LicenseCatalog::ORC);

        self::assertSame([true, LicenseCatalog::ORC, false, true, Modifications::Forbidden], [$choices->adaptedMaterialAnswered, $choices->adaptedLicense, $choices->attribution, $choices->commercialUse, $choices->modifications]);
        self::assertSame([false, null, null, null, null], [LicenseChoices::none()->adaptedMaterialAnswered, LicenseChoices::none()->adaptedLicense, LicenseChoices::none()->attribution, LicenseChoices::none()->commercialUse, LicenseChoices::none()->modifications]);
    }

    public function testShareAlikeMaterialImposesItsLicenseAndEndsTheQuestions(): void
    {
        $finder = new LicenseFinder(new LicenseCatalog());
        $choices = LicenseChoices::none()->withAdaptedLicense(LicenseCatalog::OGL);

        self::assertNull($finder->nextQuestion($choices));
        $recommendation = $finder->recommend($choices);
        self::assertSame(LicenseCatalog::OGL, $recommendation->license);
        self::assertTrue($recommendation->imposed);
        self::assertFalse($recommendation->isCustom());
        self::assertNull($recommendation->terms);
    }

    public function testTheShareAlikeLicensesAreThoseOfTheCatalog(): void
    {
        $house = License::of('house-sa', 'House share-alike', [Requirement::ShareAlike]);
        $finder = new LicenseFinder(new LicenseCatalog([$house, License::of('house', 'House', [Requirement::Attribution])]));

        self::assertSame(
            [LicenseCatalog::ORC, LicenseCatalog::CC_BY_SA, LicenseCatalog::CC_BY_NC_SA, LicenseCatalog::OGL, 'house-sa'],
            array_map(static fn (License $license): string => $license->code, $finder->shareAlikeLicenses()),
        );
        self::assertSame('house-sa', $finder->recommend(LicenseChoices::none()->withAdaptedLicense('house-sa'))->license);
    }

    public function testALicenseThatIsNotShareAlikeImposesNothing(): void
    {
        $this->expectException(InvalidLicensing::class);
        $this->expectExceptionMessageIsOrContains('The license "cc-by-4.0" is not a share-alike license of the catalog.');

        new LicenseFinder(new LicenseCatalog())->recommend(LicenseChoices::none()->withAdaptedLicense(LicenseCatalog::CC_BY));
    }

    public function testUnansweredChoicesHaveNoRecommendation(): void
    {
        $finder = new LicenseFinder(new LicenseCatalog());

        foreach ([
            'adapted_material' => LicenseChoices::none()->withAttribution(true)->withCommercialUse(true)->withModifications(Modifications::Allowed),
            'attribution' => LicenseChoices::none()->withAdaptedLicense(null)->withCommercialUse(true)->withModifications(Modifications::Allowed),
            'commercial_use' => LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(true)->withModifications(Modifications::Allowed),
            'modifications' => LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(true)->withCommercialUse(true),
        ] as $question => $choices) {
            try {
                $finder->recommend($choices);
                self::fail('A recommendation was given.');
            } catch (InvalidLicensing $invalidLicensing) {
                self::assertSame(\sprintf('The question "%s" of the license finder is unanswered.', $question), $invalidLicensing->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{bool, bool, Modifications, string}>
     */
    public static function catalogAnswers(): iterable
    {
        yield 'credit, commercial, adaptations' => [true, true, Modifications::Allowed, LicenseCatalog::CC_BY];
        yield 'credit, commercial, share-alike' => [true, true, Modifications::ShareAlike, LicenseCatalog::CC_BY_SA];
        yield 'credit, commercial, no adaptations' => [true, true, Modifications::Forbidden, LicenseCatalog::CC_BY_ND];
        yield 'credit, non-commercial, adaptations' => [true, false, Modifications::Allowed, LicenseCatalog::CC_BY_NC];
        yield 'credit, non-commercial, share-alike' => [true, false, Modifications::ShareAlike, LicenseCatalog::CC_BY_NC_SA];
        yield 'credit, non-commercial, no adaptations' => [true, false, Modifications::Forbidden, LicenseCatalog::CC_BY_NC_ND];
        yield 'no credit, every use' => [false, true, Modifications::Allowed, LicenseCatalog::CC0];
    }

    #[DataProvider('catalogAnswers')]
    public function testALicenseOfTheCatalogAnswers(bool $attribution, bool $commercialUse, Modifications $modifications, string $license): void
    {
        $recommendation = new LicenseFinder(new LicenseCatalog())->recommend(LicenseChoices::none()->withAdaptedLicense(null)->withAttribution($attribution)->withCommercialUse($commercialUse)->withModifications($modifications));

        self::assertSame($license, $recommendation->license);
        self::assertFalse($recommendation->imposed);
        self::assertNull($recommendation->terms);
    }

    /**
     * @return iterable<string, array{bool, Modifications, list<TermsClause>}>
     */
    public static function customAnswers(): iterable
    {
        yield 'commercial, share-alike' => [true, Modifications::ShareAlike, [TermsClause::Share, TermsClause::CommercialUse, TermsClause::AdaptationsShareAlike, TermsClause::NoAttribution, TermsClause::NoWarranty]];
        yield 'commercial, no adaptations' => [true, Modifications::Forbidden, [TermsClause::Share, TermsClause::CommercialUse, TermsClause::NoAdaptations, TermsClause::NoAttribution, TermsClause::NoWarranty]];
        yield 'non-commercial, adaptations' => [false, Modifications::Allowed, [TermsClause::Share, TermsClause::NoCommercialUse, TermsClause::Adaptations, TermsClause::NoAttribution, TermsClause::NoWarranty]];
    }

    /**
     * @param list<TermsClause> $clauses
     */
    #[DataProvider('customAnswers')]
    public function testCustomTermsAnswerTheRest(bool $commercialUse, Modifications $modifications, array $clauses): void
    {
        $recommendation = new LicenseFinder(new LicenseCatalog())->recommend(LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(false)->withCommercialUse($commercialUse)->withModifications($modifications));

        self::assertSame(LicenseCatalog::CUSTOM, $recommendation->license);
        self::assertTrue($recommendation->isCustom());
        self::assertFalse($recommendation->imposed);
        self::assertSame($clauses, $recommendation->terms?->clauses);
    }

    public function testCustomTermsWithCredit(): void
    {
        $terms = CustomTerms::of(true, false, Modifications::Forbidden);

        self::assertSame([TermsClause::Share, TermsClause::NoCommercialUse, TermsClause::NoAdaptations, TermsClause::Attribution, TermsClause::NoWarranty], $terms->clauses);
        self::assertTrue($terms->has(TermsClause::Attribution));
        self::assertFalse($terms->has(TermsClause::NoAttribution));
    }
}
