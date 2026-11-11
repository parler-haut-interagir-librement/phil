<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Form;

use Override;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\Exception\UrlException;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\Url\UrlType;
use Phil\Url\UrlTypes;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `TypedUrl` and the text of its field: the kind is detected among the accepted ones.
 *
 * @implements DataTransformerInterface<TypedUrl, string>
 */
final readonly class TypedUrlTransformer implements DataTransformerInterface
{
    /**
     * @param non-empty-list<EnumSet<covariant UrlType>> $accepted
     * @param bool                                       $allowLocalhost whether `localhost` and the names under `.localhost` are
     *                                                                   read as addresses
     */
    public function __construct(
        private array $accepted,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function transform(mixed $value): string
    {
        return $value instanceof TypedUrl ? $value->url->toString() : '';
    }

    #[Override]
    public function reverseTransform(mixed $value): ?TypedUrl
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return UrlTypes::type(Url::fromString($value, allowLocalhost: $this->allowLocalhost), ...$this->accepted);
        } catch (UrlException $urlException) {
            throw new TransformationFailedException($urlException->getMessage(), 0, $urlException);
        }
    }
}
