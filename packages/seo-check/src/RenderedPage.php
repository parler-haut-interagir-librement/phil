<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Phil\SeoCheck\Exception\InvalidPage;
use Phil\Url\Url;

/**
 * A page as a reader receives it: the address it was served at, its HTML, its status code, the headers search engines
 * read — `X-Robots-Tag`, `Content-Language` — and the channel it was rendered for, when the application serves the
 * same page in several storefronts or channels.
 *
 * Header names are kept in lower case, each with its values in the order given.
 */
final readonly class RenderedPage
{
    /** @var array<string, list<string>> */
    public array $headers;

    /**
     * @param array<string, string|list<string>> $headers the headers of the response, by name in any case
     * @param string|null                        $channel the storefront or channel this rendering is for, if the
     *                                                    application declares several
     *
     * @throws InvalidPage when the status is not between 100 and 599, or a header name is not a token
     */
    public function __construct(
        public Url $url,
        public string $html,
        public int $status = 200,
        array $headers = [],
        public ?string $channel = null,
    ) {
        if ($status < 100 || $status > 599) {
            throw InvalidPage::status($status);
        }

        $normalised = [];

        foreach ($headers as $name => $values) {
            if (1 !== preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/D', $name)) {
                throw InvalidPage::headerName($name);
            }

            foreach ((array) $values as $value) {
                $normalised[strtolower($name)][] = $value;
            }
        }

        $this->headers = $normalised;
    }

    /**
     * Every value of a header, in the order given; none when the response lacks it.
     *
     * @return list<string>
     */
    public function header(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }
}
