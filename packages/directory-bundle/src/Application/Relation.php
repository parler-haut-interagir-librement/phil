<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application;

use Phil\DirectoryBundle\Domain\Model\Listing;

/**
 * A relation between two genres, declared in the configuration: a listing of the first is related to listings of the
 * second — a larp is played at larp sites. Written on the listing of the first genre, shown on both: under `title` on
 * the first, under `inverseTitle` on the second. One related listing at most unless `multiple`.
 */
final readonly class Relation
{
    public string $key;

    public string $from;

    public string $to;

    public function __construct(
        string $key,
        string $from,
        string $to,
        public string $title,
        public string $inverseTitle,
        public bool $multiple = true,
    ) {
        $this->key = Listing::genreKey($key);
        $this->from = Listing::genreKey($from);
        $this->to = Listing::genreKey($to);
    }
}
