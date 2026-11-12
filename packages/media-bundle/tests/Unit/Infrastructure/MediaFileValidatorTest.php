<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Infrastructure\Validator\MediaFile;
use Phil\MediaBundle\Infrastructure\Validator\MediaFileValidator;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MediaFileValidator>
 */
#[CoversClass(MediaFile::class)]
#[CoversClass(MediaFileValidator::class)]
final class MediaFileValidatorTest extends ConstraintValidatorTestCase
{
    private DoctrineMediaRepository $repository;

    private MediaLibrary $library;

    protected function setUp(): void
    {
        $this->repository = new DoctrineMediaRepository(Database::entityManager());
        $this->library = new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC')));
        parent::setUp();
    }

    public function testAnAllowedMediumPasses(): void
    {
        $covers = MediaCollection::create('covers-books', 'Covers');
        $this->repository->save($covers);
        $media = $this->library->upload(Images::file(Images::png(4, 4)), 'a.png', $covers);

        foreach ([$media, MediaId::fromString($media->id), $media->id, null, ''] as $value) {
            $this->validate($value, new MediaFile(collections: ['events', 'COVERS-*'], mimeTypes: ['image/*']));
        }

        $this->validate($media, new MediaFile());
        $this->validate($this->library->upload(Images::file('%PDF-1.4'), 'b.pdf'), new MediaFile(collections: ['covers-*', ''], mimeTypes: ['application/pdf']));
        $this->assertNoViolation();
    }

    public function testAMediumThatDoesNotExistIsRefused(): void
    {
        $this->validate('019a5c3e-1f2b-7c4d-8e9f-0a1b2c3d4e5f', new MediaFile());
        $this->buildViolation('This medium does not exist.')->setCode('phil_media.missing')->assertRaised();
    }

    public function testAnIdentifierThatIsNoneIsRefused(): void
    {
        $this->validate('not-a-medium', new MediaFile(collections: ['x']));
        $this->buildViolation('This medium does not exist.')->setCode('phil_media.missing')->assertRaised();
    }

    public function testAMediumOutsideTheCollectionsOrOfAnotherTypeIsRefused(): void
    {
        $media = $this->library->upload(Images::file('%PDF-1.4'), 'b.pdf');
        $this->validate($media, new MediaFile(collections: ['covers-*'], mimeTypes: ['image/*', 'text/plain']));

        $this->buildViolation('This medium is not in one of the collections {{ collections }}.')
            ->setParameter('{{ collections }}', 'covers-*')
            ->setCode('phil_media.collection')
            ->buildNextViolation('This file is a {{ type }}; allowed: {{ types }}.')
            ->setParameter('{{ type }}', 'application/pdf')
            ->setParameter('{{ types }}', 'image/*, text/plain')
            ->setCode('phil_media.type')
            ->assertRaised();
    }

    public function testAMediumInACollectionIsRefusedWhereOnlyNoneIsAllowed(): void
    {
        $events = MediaCollection::create('events', 'Events');
        $this->repository->save($events);
        $this->validate($this->library->upload(Images::file(Images::png(4, 4)), 'a.png', $events), new MediaFile(collections: ['']));

        $this->buildViolation('This medium is not in one of the collections {{ collections }}.')
            ->setParameter('{{ collections }}', '')
            ->setCode('phil_media.collection')
            ->assertRaised();
    }

    public function testOnlyItsConstraintAndMediaAreValidated(): void
    {
        try {
            $this->validate(42, new MediaFile());
            self::fail('Validated.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame(MediaId::class.'|'.Media::class.'|string', $unexpectedValueException->getExpectedType());
        }

        $this->expectException(UnexpectedTypeException::class);
        $this->validate('x', new NotBlank());
    }

    public function testTheConstraintNamesItsValidatorAndKeepsItsOptions(): void
    {
        $constraint = new MediaFile(collections: ['a'], mimeTypes: ['image/png'], groups: ['upload'], payload: 'p');

        self::assertSame([MediaFileValidator::class, ['a'], ['image/png'], ['upload'], 'p'], [$constraint->validatedBy(), $constraint->collections, $constraint->mimeTypes, $constraint->groups, $constraint->payload]);
        self::assertSame(['Default'], new MediaFile()->groups);
    }

    protected function createValidator(): MediaFileValidator
    {
        return new MediaFileValidator($this->repository);
    }
}
