<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Key\KeySet;
use Phil\Url\Url;

/**
 * Where the key set of the other side is read from, by its address.
 */
interface KeySetSource
{
    /**
     * @param bool $refresh whether a cached copy is set aside and the set read again: a token names a key the copy
     *                      does not hold, after a rotation of the other side
     *
     * @throws ServiceFailed when the set cannot be read
     * @throws InvalidToken  when the document read is not a key set
     */
    public function keySet(Url $url, bool $refresh = false): KeySet;
}
