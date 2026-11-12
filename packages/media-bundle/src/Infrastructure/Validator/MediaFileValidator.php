<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Validator;

use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks a `MediaFile`: the value is a `Media`, a `MediaId` or an identifier; `null` passes, as with every constraint
 * but `NotNull`.
 */
final class MediaFileValidator extends ConstraintValidator
{
    public function __construct(
        private readonly MediaRepository $media,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MediaFile) {
            throw new UnexpectedTypeException($constraint, MediaFile::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $media = $value instanceof Media ? $value : $this->find($value);

        if (!$media instanceof Media) {
            $this->context->buildViolation($constraint->missingMessage)->setCode('phil_media.missing')->addViolation();

            return;
        }

        if (!$this->matches($media->collection()->key ?? '', $constraint->collections)) {
            $this->context->buildViolation($constraint->collectionMessage)
                ->setParameter('{{ collections }}', implode(', ', $constraint->collections))
                ->setCode('phil_media.collection')
                ->addViolation();
        }

        $type = $media->file()->mimeType;

        if (!$this->matches($type, $constraint->mimeTypes)) {
            $this->context->buildViolation($constraint->typeMessage)
                ->setParameter('{{ type }}', $type)
                ->setParameter('{{ types }}', implode(', ', $constraint->mimeTypes))
                ->setCode('phil_media.type')
                ->addViolation();
        }
    }

    private function find(mixed $value): ?Media
    {
        if (!$value instanceof MediaId && !\is_string($value)) {
            throw new UnexpectedValueException($value, MediaId::class.'|'.Media::class.'|string');
        }

        try {
            return $this->media->get($value instanceof MediaId ? $value->value : MediaId::fromString($value)->value);
        } catch (InvalidMedia) {
            return null;
        }
    }

    /**
     * @param list<string> $patterns
     */
    private function matches(string $value, array $patterns): bool
    {
        if ([] === $patterns) {
            return true;
        }

        return array_any($patterns, static fn (string $pattern): bool => '' === $pattern ? '' === $value : fnmatch($pattern, $value, \FNM_CASEFOLD));
    }
}
