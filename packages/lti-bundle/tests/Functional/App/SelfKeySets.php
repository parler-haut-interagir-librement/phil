<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional\App;

use Override;
use Phil\Lti\Contract\KeySetSource;
use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Key\KeySet;
use Phil\Url\Url;

/**
 * The key set of every address is the application's own: the test application is the platform and the tool at once.
 */
final readonly class SelfKeySets implements KeySetSource
{
    public function __construct(
        private KeyStore $keys,
    ) {
    }

    #[Override]
    public function keySet(Url $url, bool $refresh = false): KeySet
    {
        return $this->keys->publicKeys();
    }
}
