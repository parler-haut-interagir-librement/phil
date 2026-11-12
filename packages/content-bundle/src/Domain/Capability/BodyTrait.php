<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Capability;

use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\Markup\MarkupFormat;

/**
 * The implementation of `BodyCapable`, without any persistence mapping: the source, the name of the format and the
 * HTML, one property and one column each, written together by `setBody()`.
 *
 * @phpstan-require-implements BodyCapable
 */
trait BodyTrait
{
    private ?string $bodySource = null;

    private ?string $bodyFormat = null;

    private ?string $bodyHtml = null;

    public function getBody(): ?Body
    {
        if (null === $this->bodySource) {
            return null;
        }

        return Body::of($this->bodySource, $this->bodyFormat ?? MarkupFormat::Markdown, $this->bodyHtml ?? '');
    }

    public function setBody(?Body $body): void
    {
        $this->bodySource = $body?->source;
        $this->bodyFormat = $body?->format;
        $this->bodyHtml = $body?->html;
    }
}
