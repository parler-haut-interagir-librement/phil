<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * Removes personal data and secrets from an event before it is written anywhere:
 *
 * - a header or a tag whose name looks sensitive — `Authorization`, `Cookie`, `X-Api-Key`, `password`, `token`… — keeps
 *   its name and loses its value;
 * - every value of the query of the request's address is filtered, whatever its name: a query carries search terms,
 *   reset links and invitations;
 * - in every text — messages, other headers, other tags, the path of the address — e-mail addresses, credentials of an
 *   address, `Bearer` and `Basic` tokens and `name=value` pairs with a sensitive name are filtered.
 *
 * Names are matched case-insensitively on a part of the name, so that `X-Csrf-Token` and `user_password` are
 * filtered; a name such as `author` is filtered too, on the safe side. The frames are left as they are: they hold
 * paths and function names, never arguments.
 */
final readonly class Scrubber
{
    public const string FILTERED = '[Filtered]';

    public const string EMAIL = '[email]';

    /**
     * The parts of a name that make it sensitive.
     */
    public const array DEFAULT_FIELDS = [
        'pass', 'secret', 'token', 'api-key', 'api_key', 'apikey', 'auth', 'credential', 'session', 'cookie', 'csrf',
        'xsrf', 'private-key', 'private_key', 'signature', 'card', 'cvv', 'iban', 'ssn',
    ];

    private string $pattern;

    /**
     * @param list<string> $fields parts of a name that make it sensitive, beside the defaults
     */
    public function __construct(array $fields = [])
    {
        $this->pattern = '/'.implode('|', array_map(static fn (string $field): string => preg_quote($field, '/'), [...self::DEFAULT_FIELDS, ...$fields])).'/i';
    }

    public function scrub(ErrorEvent $event): ErrorEvent
    {
        $exceptions = array_map(fn (ExceptionRecord $exception): ExceptionRecord => new ExceptionRecord($exception->type, $this->text($exception->message), $exception->frames), $event->exceptions);
        $request = $event->request;
        $context = [];
        $headers = [];

        foreach ($event->context as $key => $value) {
            $context[$key] = $this->isSensitive($key) ? self::FILTERED : (\is_string($value) ? $this->text($value) : $value);
        }

        foreach ($request->headers ?? [] as $name => $value) {
            $headers[$name] = $this->isSensitive($name) ? self::FILTERED : $this->text($value);
        }

        return $event->with($exceptions, $request instanceof RequestSummary ? new RequestSummary($request->method, $this->url($request->url), $request->route, $headers, $request->clientAddress) : null, $context);
    }

    public function isSensitive(string $name): bool
    {
        return 1 === preg_match($this->pattern, $name);
    }

    /**
     * A text with its e-mail addresses, credentials, tokens and sensitive pairs filtered.
     */
    public function text(string $text): string
    {
        $text = (string) preg_replace([
            '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+/i',
            '/\b(bearer|basic|token)(\s+)[a-z0-9._~+\/=-]+/i',
            '#(://)[^/\s:@]+:[^/\s@]+@#',
        ], [self::EMAIL, '$1$2'.self::FILTERED, '$1'.self::FILTERED.'@'], $text);

        return (string) preg_replace_callback(
            '/([\w.-]+)(["\']?\s*[=:]\s*["\']?)[^\s&,;"\']+/',
            fn (array $pair): string => $this->isSensitive($pair[1]) ? $pair[1].$pair[2].self::FILTERED : $pair[0],
            $text,
        );
    }

    /**
     * An address with every value of its query filtered and its fragment dropped.
     */
    public function url(string $url): string
    {
        $address = substr($url, 0, strcspn($url, '#'));
        $mark = strpos($address, '?');

        if (false === $mark) {
            return $this->text($address);
        }

        $names = [];

        foreach (explode('&', substr($address, $mark + 1)) as $pair) {
            $name = substr($pair, 0, strcspn($pair, '='));

            if ('' !== $name) {
                $names[] = $name.'='.self::FILTERED;
            }
        }

        return $this->text(substr($address, 0, $mark).'?'.implode('&', $names));
    }
}
