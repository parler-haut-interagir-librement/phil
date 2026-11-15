<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * What a deep linking request accepts back: where to return, the types of content items, the presentations, whether
 * several items and line items are accepted, and the opaque data the tool must echo.
 */
final readonly class DeepLinkingSettings
{
    /**
     * @param list<string> $acceptTypes                       `ltiResourceLink`, `link`, `file`, `html`, `image`
     * @param list<string> $acceptPresentationDocumentTargets `iframe`, `window`, `embed`
     */
    public function __construct(
        public Url $returnUrl,
        public array $acceptTypes,
        public array $acceptPresentationDocumentTargets = [],
        public ?string $acceptMediaTypes = null,
        public bool $acceptMultiple = false,
        public bool $acceptLineItem = false,
        public bool $autoCreate = false,
        public ?string $title = null,
        public ?string $text = null,
        public ?string $data = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self(
            $claim->url('deep_link_return_url'),
            $claim->stringList('accept_types', 16),
            $claim->stringList('accept_presentation_document_targets', 8),
            $claim->optionalString('accept_media_types'),
            $claim->bool('accept_multiple'),
            $claim->bool('accept_lineitem'),
            $claim->bool('auto_create'),
            $claim->optionalString('title'),
            $claim->optionalString('text'),
            $claim->optionalString('data'),
        );
    }

    public function accepts(string $type): bool
    {
        return \in_array($type, $this->acceptTypes, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toClaim(): array
    {
        return array_filter([
            'deep_link_return_url' => $this->returnUrl->toString(),
            'accept_types' => $this->acceptTypes,
            'accept_presentation_document_targets' => $this->acceptPresentationDocumentTargets,
            'accept_media_types' => $this->acceptMediaTypes,
            'accept_multiple' => $this->acceptMultiple,
            'accept_lineitem' => $this->acceptLineItem,
            'auto_create' => $this->autoCreate,
            'title' => $this->title,
            'text' => $this->text,
            'data' => $this->data,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
