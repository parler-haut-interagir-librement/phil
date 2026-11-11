<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\SecurityTxtSigner;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/.well-known/security.txt` (RFC 9116): the text written when the container is built, signed by the application's
 * signer when it names one.
 */
final readonly class SecurityTxtDocument implements WellKnownDocument
{
    public const string CONTENT_TYPE = 'text/plain; charset=utf-8';

    public function __construct(
        private string $text,
        private ?SecurityTxtSigner $signer = null,
    ) {
    }

    #[Override]
    public function serve(Request $request): Response
    {
        return DocumentResponses::body($this->signer?->sign($this->text) ?? $this->text, self::CONTENT_TYPE, false);
    }
}
