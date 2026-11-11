<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Tests\Unit;

use Override;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use Phil\Ujo\Validation;
use Phil\UjoBundle\Infrastructure\Form\UjoTransformer;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Twig\UjoExtension;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjo;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidUjoValidator>
 */
#[CoversClass(ValidUjo::class)]
#[CoversClass(ValidUjoValidator::class)]
#[CoversClass(UjoTransformer::class)]
#[CoversClass(UjoExtension::class)]
#[CoversClass(UjoType::class)]
final class IntegrationTest extends ConstraintValidatorTestCase
{
    private const string JSON = '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>Hi</strong>"}},{"id":"w","type":"wide","data":{"blocks":[{"id":"q","type":"quote","data":{"text":"Q"}},{"id":"r","type":"quote","data":{"text":"R"}}]}}]}';

    private const string CANONICAL = '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>Hi</b>"}},{"id":"w","type":"wide","data":{"blocks":[{"id":"q","type":"quote","data":{"text":"Q","caption":"","alignment":"left"}},{"id":"r","type":"quote","data":{"text":"R","caption":"","alignment":"left"}}]}}]}';

    #[Override]
    protected function createValidator(): ValidUjoValidator
    {
        return new ValidUjoValidator(new Ujo());
    }

    public function testNothingIsValid(): void
    {
        $this->validate(null, new ValidUjo());
        $this->validate('', new ValidUjo());

        $this->assertNoViolation();
    }

    public function testAValidDocumentOrItsJsonIsValid(): void
    {
        $this->validate(self::JSON, new ValidUjo());
        $this->validate(new Document([new Block('a', 'paragraph', ['text' => 't'])]), new ValidUjo(['paragraph']));

        $this->assertNoViolation();
    }

    public function testAnInvalidDocumentSaysWhy(): void
    {
        $this->validate('{"blocks":[{"id":"a","type":"header","data":{"text":"t","level":9}}]}', new ValidUjo());

        $this->buildViolation('This value is not a valid UJO document: {{ error }}')
            ->setParameter('{{ error }}', 'The UJO document is invalid at blocks[0].data.level: 9 is above the maximum 6.')
            ->setCode(ValidUjo::INVALID_ERROR)
            ->assertRaised();
    }

    public function testADraftIsValidForItsShapeAndNotForPublication(): void
    {
        $json = '{"blocks":[{"id":"h","type":"header","data":{"text":""}}]}';

        $this->validate($json, new ValidUjo());
        $this->validate(new Document([new Block('h', 'header', ['text' => ''])]), new ValidUjo(level: Validation::Shape));
        $this->assertNoViolation();

        $this->validate($json, new ValidUjo(level: Validation::Publication));

        $this->buildViolation('This value is not a valid UJO document: {{ error }}')
            ->setParameter('{{ error }}', 'The UJO document is invalid at blocks[0].data.text: this value must be filled in to publish.')
            ->setCode(ValidUjo::INVALID_ERROR)
            ->assertRaised();
    }

    public function testADocumentObjectIsCheckedForPublication(): void
    {
        $this->validate(new Document([new Block('l', 'list', ['style' => 'ordered', 'items' => []])]), new ValidUjo(level: Validation::Publication));

        $this->buildViolation('This value is not a valid UJO document: {{ error }}')
            ->setParameter('{{ error }}', 'The UJO document is invalid at blocks[0].data.items: at least 1 items are required to publish, 0 are given.')
            ->setCode(ValidUjo::INVALID_ERROR)
            ->assertRaised();
    }

    public function testAnInvalidDocumentObjectSaysWhy(): void
    {
        $this->validate(new Document([new Block('a', 'sparkle')]), new ValidUjo(message: 'Bad: {{ error }}'));

        $this->buildViolation('Bad: {{ error }}')
            ->setParameter('{{ error }}', 'The block type "sparkle" at blocks[0] is not registered.')
            ->setCode(ValidUjo::INVALID_ERROR)
            ->assertRaised();
    }

    public function testOnlyTheNamedTypesAreAcceptedNestedOnesIncluded(): void
    {
        $this->validate(self::JSON, new ValidUjo(['paragraph'], typeMessage: 'No {{ type }}.'));

        $this->buildViolation('No {{ type }}.')
            ->setParameter('{{ type }}', '"wide"')
            ->setCode(ValidUjo::TYPE_ERROR)
            ->buildNextViolation('No {{ type }}.')
            ->setParameter('{{ type }}', '"quote"')
            ->setCode(ValidUjo::TYPE_ERROR)
            ->assertRaised();
    }

    public function testTheDefaultMessages(): void
    {
        $constraint = new ValidUjo();

        self::assertNull($constraint->types);
        self::assertSame(Validation::Shape, $constraint->level);
        self::assertSame('This value is not a valid UJO document: {{ error }}', $constraint->message);
        self::assertSame('Blocks of type {{ type }} are not accepted here.', $constraint->typeMessage);
        self::assertSame(['INVALID_ERROR', 'TYPE_ERROR'], [ValidUjo::getErrorName(ValidUjo::INVALID_ERROR), ValidUjo::getErrorName(ValidUjo::TYPE_ERROR)]);
    }

    public function testTheGroupsAndThePayloadReachTheConstraint(): void
    {
        $constraint = new ValidUjo(groups: ['draft'], payload: 'p');

        self::assertSame(['draft'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
    }

    public function testAnotherConstraintIsAMistake(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('x', new NotBlank());
    }

    public function testAnotherValueIsAMistake(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate(new stdClass(), new ValidUjo());
    }

    public function testTheFieldTransformsJsonToTheCanonicalForm(): void
    {
        $json = new UjoTransformer(new Ujo(), false);
        $document = new UjoTransformer(new Ujo(), true);

        self::assertSame(self::CANONICAL, $json->reverseTransform(self::JSON));
        self::assertEquals(new Ujo()->decode(self::JSON), $document->reverseTransform(self::JSON));
        self::assertNull($json->reverseTransform(''));
        self::assertNull($json->reverseTransform(null));
        self::assertSame('', $json->transform(null));
        self::assertSame('{"blocks":[]}', $json->transform('{"blocks":[]}'));
        self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"t"}}]}', $json->transform(new Document([new Block('a', 'paragraph', ['text' => 't'])])));
    }

    public function testAnInvalidSubmissionFailsWithTheReason(): void
    {
        try {
            new UjoTransformer(new Ujo(), false)->reverseTransform('{"blocks":3}');
            self::fail('An invalid document is accepted.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame('The UJO document is invalid at blocks: the blocks must be a list.', $transformationFailedException->getMessage());
            self::assertSame('This value is not a valid UJO document: {{ error }}', $transformationFailedException->getInvalidMessage());
            self::assertSame(['{{ error }}' => 'The UJO document is invalid at blocks: the blocks must be a list.'], $transformationFailedException->getInvalidMessageParameters());
            self::assertNotNull($transformationFailedException->getPrevious());
        }
    }

    public function testTheTwigFiltersRenderOrConvert(): void
    {
        $extension = new UjoExtension(new Ujo());

        self::assertSame(['', ''], [$extension->html(null), $extension->text(null)]);
        self::assertStringStartsWith('<p class="ujo-paragraph"><b>Hi</b></p>', $extension->html(self::JSON));
        self::assertSame("Hi\n\nQ\n\nR", $extension->text(self::JSON));
        self::assertSame('<p class="ujo-paragraph">t</p>', $extension->html(new Document([new Block('a', 'paragraph', ['text' => 't'])])));
    }

    public function testTheFieldOptions(): void
    {
        $resolver = new OptionsResolver();
        $type = new UjoType(new Ujo());
        $type->configureOptions($resolver);

        self::assertSame(['data_class' => null, 'invalid_message' => 'This value is not a valid UJO document.', 'document' => false], $resolver->resolve());
        self::assertSame(TextareaType::class, $type->getParent());
    }
}
