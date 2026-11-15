<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * How the platform presents the tool: where, at what size, in which language, and where to send the person back.
 */
final readonly class LaunchPresentation
{
    public function __construct(
        public ?DocumentTarget $documentTarget = null,
        public ?Url $returnUrl = null,
        public ?string $locale = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        $target = $claim->optionalString('document_target', 16);

        return new self(
            null === $target ? null : DocumentTarget::tryFrom($target),
            $claim->optionalUrl('return_url'),
            $claim->optionalString('locale', 35),
            $claim->optionalInt('width'),
            $claim->optionalInt('height'),
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function toClaim(): array
    {
        return array_filter([
            'document_target' => $this->documentTarget?->value,
            'return_url' => $this->returnUrl?->toString(),
            'locale' => $this->locale,
            'width' => $this->width,
            'height' => $this->height,
        ], static fn (string|int|null $value): bool => null !== $value);
    }
}
