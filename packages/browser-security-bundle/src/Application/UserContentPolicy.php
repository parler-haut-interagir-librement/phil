<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\Directive;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use Phil\BrowserSecurityBundle\Domain\ValueObject\Policy;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The strict headers of a response that serves a file an untrusted person uploaded, so that the browser shows it as
 * the data it is and never runs it as a page of the site:
 *
 * - `Content-Security-Policy` of its own, `default-src 'none'; sandbox; …` by default, in place of any other, and no
 *   report-only policy: a file opened directly runs no script, loads nothing, posts no form and is framed nowhere;
 * - `X-Content-Type-Options: nosniff`, and `Content-Type: application/octet-stream` when the response has none, so that
 *   the browser never guesses HTML;
 * - `Content-Disposition: inline` only for a media type of the inline list — raster images, audio, video, plain text —,
 *   `attachment` for every other one and whenever the response already asked for it, with the file name kept or given;
 * - `Cross-Origin-Resource-Policy`, `same-origin` by default, `Referrer-Policy: no-referrer`,
 *   `X-Permitted-Cross-Domain-Policies: none`, and `X-Frame-Options: DENY` while the policy's `frame-ancestors` is
 *   `'none'`.
 *
 * Applied to a response before it leaves the controller, or by the route default `_user_content` and the attribute
 * `#[UserContent]`, these headers win over the global ones: the other listeners keep a header a response carries. The
 * policy is built once, checked with the container, and shared between requests.
 */
final readonly class UserContentPolicy
{
    /** The route default that applies the policy to a route's responses. */
    public const string ROUTE_DEFAULT = '_user_content';

    /** The policy of a file served by default: nothing loads, nothing runs, nothing frames it. */
    public const array DEFAULT_DIRECTIVES = [
        'default-src' => ["'none'"],
        'base-uri' => ["'none'"],
        'form-action' => ["'none'"],
        'frame-ancestors' => ["'none'"],
        'sandbox' => true,
    ];

    /** The media types shown in the browser by default: none of them can carry a script the browser runs. */
    public const array DEFAULT_INLINE_TYPES = [
        'image/avif',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
        'video/mp4',
        'video/ogg',
        'video/webm',
        'text/plain',
    ];

    /** The media types a browser may run as a document or a script, never shown inline. */
    public const array ACTIVE_TYPES = [
        'application/ecmascript',
        'application/javascript',
        'application/xhtml+xml',
        'application/xml',
        'image/svg+xml',
        'text/ecmascript',
        'text/html',
        'text/javascript',
        'text/xml',
        'text/xsl',
    ];

    public const array RESOURCE_POLICIES = ['same-origin', 'same-site', 'cross-origin'];

    private const string MEDIA_TYPE = '#^[a-z0-9][a-z0-9!\#$&^_.+-]{0,126}/[a-z0-9][a-z0-9!\#$&^_.+-]{0,126}$#D';

    private Policy $policy;

    /** @var list<string> */
    private array $inline;

    /**
     * @param array<array-key, list<string>|bool> $directives  the policy of the files, as a named policy declares them
     * @param list<string>                        $inlineTypes the media types shown in the browser, lowercase
     * @param string                              $resource    the value of Cross-Origin-Resource-Policy
     *
     * @throws InvalidPolicy when a directive, a media type or the resource policy is invalid, or an active type is inline
     */
    public function __construct(
        array $directives = self::DEFAULT_DIRECTIVES,
        array $inlineTypes = self::DEFAULT_INLINE_TYPES,
        private string $resource = 'same-origin',
    ) {
        $this->policy = new Policy($directives);

        if ('' === $this->policy->header()) {
            throw new InvalidPolicy('The policy of user content declares at least one directive.');
        }

        if (!\in_array($resource, self::RESOURCE_POLICIES, true)) {
            throw new InvalidPolicy(\sprintf('"%s" is not a value of Cross-Origin-Resource-Policy: same-origin, same-site or cross-origin.', $resource));
        }

        $inline = [];

        foreach ($inlineTypes as $type) {
            if (1 !== preg_match(self::MEDIA_TYPE, $type)) {
                throw new InvalidPolicy(\sprintf('"%s" is not a media type in lowercase, such as image/png.', $type));
            }

            if (\in_array($type, self::ACTIVE_TYPES, true)) {
                throw new InvalidPolicy(\sprintf('"%s" can run as a page or a script: it is always served as an attachment.', $type));
            }

            $inline[] = $type;
        }

        $this->inline = $inline;
    }

    /**
     * Writes the headers of a file uploaded by someone on the response.
     *
     * @param string|null $filename the name the browser saves the file under; the response's own when null
     */
    public function apply(Response $response, ?string $filename = null): void
    {
        $headers = $response->headers;
        $type = $this->mediaType($headers->get('Content-Type'));

        if (null === $type) {
            $type = 'application/octet-stream';
            $headers->set('Content-Type', $type);
        }

        $headers->set('Content-Security-Policy', $this->policy->header());
        $headers->remove('Content-Security-Policy-Report-Only');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Content-Disposition', $this->disposition($type, $filename, $headers->get('Content-Disposition')));
        $headers->set('Cross-Origin-Resource-Policy', $this->resource);
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ([SourceSyntax::NONE] === $this->policy->sources(Directive::FrameAncestors)) {
            $headers->set('X-Frame-Options', 'DENY');
        }
    }

    /**
     * Whether a media type is shown in the browser rather than saved.
     */
    public function isInline(string $mediaType): bool
    {
        return \in_array(strtolower($mediaType), $this->inline, true);
    }

    /**
     * The value of the policy's header.
     */
    public function header(): string
    {
        return $this->policy->header();
    }

    private function mediaType(?string $contentType): ?string
    {
        // Lowercased where it is compared, by isInline().
        $type = trim(explode(';', $contentType ?? '')[0]);

        return '' === $type ? null : $type;
    }

    private function disposition(string $type, ?string $filename, ?string $current): string
    {
        $current = trim($current ?? '');
        $asked = strtolower(trim(explode(';', $current)[0]));
        $kind = 'attachment' !== $asked && $this->isInline($type) ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT;

        if (null !== $filename) {
            $filename = $this->safeName($filename);

            return HeaderUtils::makeDisposition($kind, $filename, $this->asciiName($filename));
        }

        $parameters = strpos($current, ';');

        return false === $parameters ? $kind : $kind.substr($current, $parameters);
    }

    /**
     * The name without a path, a control character nor a quote, never empty.
     */
    private function safeName(string $filename): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\"]+/', '_', $filename));

        return '' === $name ? 'file' : $name;
    }

    /**
     * The name in printable ASCII without `%`, for the browsers that ignore `filename*`.
     */
    private function asciiName(string $filename): string
    {
        return (string) preg_replace('/[^\x20-\x24\x26-\x7E]/', '_', $filename);
    }
}
