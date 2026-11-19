<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

use JsonException;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Reads the report the Stimulus controller posts, or anyone posts:
 *
 * ```json
 * {"type": "TypeError", "message": "x is undefined", "url": "https://example.com/cart", "stack": "TypeError: …\n    at …"}
 * ```
 *
 * The report is one flat object. The page is an `http` or `https` address that `phil/url` reads — `localhost` allowed,
 * an IP address, credentials, port 0 or a control character refused —, kept normalised and without its query nor its fragment, which carry
 * search terms, tokens and invitations; the type is a JavaScript name, `Error` when it is anything else; the message is
 * cut and loses its control characters; the stack is read line by line in the formats of Chromium
 * (`at fn (url:line:column)`) and of Firefox and Safari (`fn@url:line:column`), up to a number of frames, the other
 * lines ignored. A frame's file is its script address without query nor fragment, and it is the application's when
 * `phil/url` reads it and it comes from the page's origin.
 */
final readonly class BrowserErrorParser
{
    public const int DEFAULT_MAX_FRAMES = 50;

    public const int MAX_STACK_LENGTH = 16_384;

    private const string CHROMIUM = '/^\s*at (?:(.{1,255}?) \()?(https?:\/\/[^\s()]{1,2048}?):(\d{1,7}):\d{1,7}\)?\s*$/D';

    private const string GECKO = '/^\s*([^@\s]{0,255})@(https?:\/\/[^\s]{1,2048}?):(\d{1,7}):\d{1,7}\s*$/D';

    public function __construct(
        private int $maxFrames = self::DEFAULT_MAX_FRAMES,
    ) {
    }

    /**
     * @throws InvalidBrowserError when the body is not a JSON object with a message and a page
     */
    public function parse(string $body): BrowserError
    {
        try {
            $report = json_decode($body, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidBrowserError::because('The report is not JSON.');
        }

        if (!\is_array($report) || !\is_string($report['message'] ?? null) || !\is_string($report['url'] ?? null)) {
            throw InvalidBrowserError::because('The report has no message or no page.');
        }

        $page = (1 === preg_match('/[\x00-\x1F\x7F]/', $report['url']) ? null : $this->url($report['url'])) ?? throw InvalidBrowserError::because('The page is not an http address.');
        $origin = self::originOf($page);

        $type = \is_string($report['type'] ?? null) && 1 === preg_match('/^[A-Za-z_$][\w$.]{0,63}$/D', $report['type']) ? $report['type'] : 'Error';
        $stack = \is_string($report['stack'] ?? null) ? substr($report['stack'], 0, self::MAX_STACK_LENGTH) : '';
        $message = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', mb_substr($report['message'], 0, ExceptionRecord::MAX_MESSAGE_LENGTH, 'UTF-8'));

        return new BrowserError($type, $message, $origin.$page->path, $this->frames($stack, $origin));
    }

    /**
     * @return list<Frame>
     */
    private function frames(string $stack, string $origin): array
    {
        $frames = [];
        $lines = explode("\n", $stack);

        for ($index = 0; isset($lines[$index]) && \count($frames) < $this->maxFrames; ++$index) {
            if (1 === preg_match(self::CHROMIUM, $lines[$index], $match) || 1 === preg_match(self::GECKO, $lines[$index], $match)) {
                $script = substr($match[2], 0, strcspn($match[2], '?#'));
                $function = '' === $match[1] ? null : (string) preg_replace('/[\x00-\x1F\x7F]/', '', $match[1]);
                $url = $this->url($script);
                $frames[] = new Frame($script, (int) $match[3], $function, $url instanceof Url && self::originOf($url) === $origin);
            }
        }

        return $frames;
    }

    /**
     * The origin of an address, as a browser sends it in `Origin`: `https://example.com:8443`, the scheme and the host in
     * lower case, the host in its ASCII form, the default port left out.
     */
    public static function originOf(Url $url): string
    {
        return $url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port);
    }

    /**
     * The address `phil/url` reads of a text, `localhost` allowed, or `null`.
     */
    private function url(string $text): ?Url
    {
        try {
            return Url::fromString($text, allowLocalhost: true);
        } catch (UrlException) {
            return null;
        }
    }
}
