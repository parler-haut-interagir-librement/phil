<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Phil\PagedMedia\Gotenberg\Internal\Url;

/**
 * A file Gotenberg downloads before it starts, as if it had been uploaded. The server must answer with a
 * `Content-Disposition` header that names the file.
 */
final readonly class Download
{
    public string $url;

    /**
     * @param array<string, string> $extraHttpHeaders headers sent with the download, such as `Authorization`
     */
    public function __construct(string $url, public array $extraHttpHeaders = [])
    {
        $this->url = Url::public($url, 'A download');
    }

    /**
     * @return array{url: string, extraHttpHeaders?: array<string, string>}
     */
    public function toArray(): array
    {
        return [] === $this->extraHttpHeaders ? ['url' => $this->url] : ['url' => $this->url, 'extraHttpHeaders' => $this->extraHttpHeaders];
    }
}
