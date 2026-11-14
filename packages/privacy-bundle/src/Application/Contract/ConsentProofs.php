<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;

/**
 * The dated record of every choice: which version of the text was shown, what was granted, and when.
 *
 * A record holds no address and no identifier of the person, only the random identifier of the choice.
 */
interface ConsentProofs
{
    public function record(ConsentChoice $choice): void;
}
