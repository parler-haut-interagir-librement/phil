<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Contract;

use Phil\IpAnonymizer\IpAddress;

/**
 * A step that turns an address into another address, such as one with its host bits cleared. A `Pipeline` runs
 * transformers before the anonymizer that makes the text.
 */
interface IpTransformer
{
    public function transform(IpAddress $address): IpAddress;
}
