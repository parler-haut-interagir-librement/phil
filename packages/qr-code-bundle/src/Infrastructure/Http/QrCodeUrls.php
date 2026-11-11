<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Http;

use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCodeBundle\Application\QrCodes;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The signed addresses of the route that serves codes as images: the payload and the logo in the query, signed with
 * the secret of the application, so the route draws only the codes the application wrote and nobody else's.
 */
final readonly class QrCodeUrls
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private UriSigner $signer,
        private QrCodes $codes,
    ) {
    }

    /**
     * @param string|Content|array<array-key, mixed> $content
     *
     * @throws InvalidContent
     * @throws InvalidOption  when the format is neither svg nor png
     */
    public function url(string|array|Content $content, string $format = 'svg', string $logo = '', bool $absolute = false): string
    {
        if (!\in_array($format, ['svg', 'png'], true)) {
            throw InvalidOption::of('format', 'svg or png', '"'.$format.'"');
        }

        $content = $this->codes->content($content);
        $parameters = ['format' => $format, 'data' => $content instanceof Content ? $content->payload() : $content] + ('' === $logo ? [] : ['logo' => $logo]);

        // The signature covers the whole address, as the route checks it; a path keeps it after its host is dropped.
        $url = $this->signer->sign($this->router->generate(QrCodeController::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL));

        return $absolute ? $url : (string) preg_replace('#^[a-z][a-z0-9+.\-]*://[^/]+#i', '', $url);
    }
}
