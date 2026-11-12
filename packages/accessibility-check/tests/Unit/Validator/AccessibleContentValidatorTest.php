<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Validator;

use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\Validator\AccessibleContent;
use Phil\AccessibilityCheck\Validator\AccessibleContentValidator;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AccessibleContentValidator>
 */
#[CoversClass(AccessibleContent::class)]
#[CoversClass(AccessibleContentValidator::class)]
final class AccessibleContentValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleContentValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validate(null, new AccessibleContent());
        $this->validate('', new AccessibleContent());

        $this->assertNoViolation();
    }

    public function testAnAccessibleContentIsValid(): void
    {
        $this->validate('<p>Hello <a href="/">our prices</a></p><img src="a.png" alt="A cat">', new AccessibleContent());

        $this->assertNoViolation();
    }

    public function testEachFindingAtTheSeverityIsAViolation(): void
    {
        $this->validate("<p>x</p>\n<img src=\"a.png\"><h2>A</h2><h4>Skip</h4>", new AccessibleContent());

        $this->buildViolation('{{ message }}')
            ->setParameter('{{ message }}', 'The image a.png has no text alternative: describe it in an alt text, or give it alt="" when it is only decoration.')
            ->setParameter('{{ rule }}', 'image-alt')
            ->setParameter('{{ criterion }}', '1.1.1')
            ->setParameter('{{ severity }}', 'error')
            ->setParameter('{{ location }}', 'line 2, img')
            ->setCode(AccessibleContent::INACCESSIBLE_ERROR)
            ->assertRaised();
    }

    public function testTheSeverityTheLanguageAndTheLocaleAreTheConstraints(): void
    {
        $this->validate(new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return '<h2>A</h2><h4>Skip</h4><a href="/x">ici</a>';
            }
        }, new AccessibleContent(minSeverity: Severity::Warning, language: 'fr', locale: 'fr', message: '{{ rule }}: {{ message }}'));

        $this->buildViolation('{{ rule }}: {{ message }}')
            ->setParameter('{{ message }}', 'Ce titre h4 suit un h2 : un niveau est sauté.')
            ->setParameter('{{ rule }}', 'heading-order')
            ->setParameter('{{ criterion }}', '1.3.1')
            ->setParameter('{{ severity }}', 'warning')
            ->setParameter('{{ location }}', 'line 1, h4')
            ->setCode(AccessibleContent::INACCESSIBLE_ERROR)
            ->buildNextViolation('{{ rule }}: {{ message }}')
            ->setParameter('{{ message }}', 'Le texte du lien vers /x, « ici », ne dit pas où il mène : décrivez sa destination.')
            ->setParameter('{{ rule }}', 'link-text')
            ->setParameter('{{ criterion }}', '2.4.4')
            ->setParameter('{{ severity }}', 'warning')
            ->setParameter('{{ location }}', 'line 1, a')
            ->setCode(AccessibleContent::INACCESSIBLE_ERROR)
            ->assertRaised();
    }

    public function testAUjoDocumentIsCheckedInTheValidatorsLocale(): void
    {
        $this->validator = new AccessibleContentValidator(new AccessibilityChecker(), 'de');

        $this->validate(new Document([new Block('i1', 'image', ['file' => ['url' => '/a.png']])]), new AccessibleContent());

        $this->buildViolation('{{ message }}')
            ->setParameter('{{ message }}', 'Das Bild /a.png hat keine Textalternative: Beschreiben Sie es in einem Alternativtext, oder geben Sie ihm alt="", wenn es nur Dekoration ist.')
            ->setParameter('{{ rule }}', 'image-alt')
            ->setParameter('{{ criterion }}', '1.1.1')
            ->setParameter('{{ severity }}', 'error')
            ->setParameter('{{ location }}', 'block "i1" (image), blocks[0]')
            ->setCode(AccessibleContent::INACCESSIBLE_ERROR)
            ->assertRaised();
    }

    public function testAContentTheCheckerRefusesIsOneViolation(): void
    {
        $this->validator = new AccessibleContentValidator(new AccessibilityChecker(new Policy(maxBytes: 4)));

        $this->validate('<p>long</p>', new AccessibleContent(refusedMessage: 'Refused: {{ reason }}'));

        $this->buildViolation('Refused: {{ reason }}')
            ->setParameter('{{ reason }}', 'The content holds 11 bytes; at most 4 are checked.')
            ->setCode(AccessibleContent::REFUSED_ERROR)
            ->assertRaised();
    }

    public function testTheConstraintHasItsDefaults(): void
    {
        $constraint = new AccessibleContent();

        self::assertSame(Severity::Error, $constraint->minSeverity);
        self::assertNull($constraint->language);
        self::assertNull($constraint->locale);
        self::assertSame('This content cannot be checked for accessibility: {{ reason }}', $constraint->refusedMessage);
        self::assertSame('INACCESSIBLE_ERROR', AccessibleContent::getErrorName(AccessibleContent::INACCESSIBLE_ERROR));
        self::assertSame('REFUSED_ERROR', AccessibleContent::getErrorName(AccessibleContent::REFUSED_ERROR));
        self::assertSame(['Default', 'strict'], new AccessibleContent(groups: ['Default', 'strict'])->groups);
    }

    public function testAnotherConstraintIsRefused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('<p>x</p>', new NotBlank());
    }

    public function testAValueThatIsNeitherHtmlNorADocumentIsRefused(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('"string|'.Document::class.'"');

        $this->validate(new stdClass(), new AccessibleContent());
    }
}
