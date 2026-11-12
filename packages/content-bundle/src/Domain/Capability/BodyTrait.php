<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\MarkupFormat;

/**
 * The implementation of `BodyCapable`, without any persistence mapping: the source, the name of the format, the
 * HTML and whether its author held the raw HTML permission, one property and one column each, written together by
 * `setBody()`.
 *
 * @phpstan-require-implements BodyCapable
 */
trait BodyTrait
{
    private ?string $bodySource = null;

    private ?string $bodyFormat = null;

    private ?string $bodyHtml = null;

    private bool $bodyRawHtml = false;

    public function getBody(): ?Body
    {
        if (null === $this->bodySource) {
            return null;
        }

        return Body::of($this->bodySource, $this->bodyFormat ?? MarkupFormat::Markdown, $this->bodyHtml ?? '', $this->bodyRawHtml);
    }

    public function setBody(?Body $body): void
    {
        $this->bodySource = $body?->source;
        $this->bodyFormat = $body?->format;
        $this->bodyHtml = $body?->html;
        $this->bodyRawHtml = $body instanceof Body && $body->rawHtml;
    }
}
