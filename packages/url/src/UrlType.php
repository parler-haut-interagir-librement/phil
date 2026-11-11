<?php

declare(strict_types=1);

namespace Phil\Url;

use UnitEnum;

/**
 * A kind of web address, and the rule an address of that kind follows: a Facebook page is on `facebook.com`, a
 * website is any address. An enum implements it, each case a kind; `KnownUrlType` holds the international ones, and
 * an application declares its own — the addresses of its region's registries, say — the same way.
 *
 * **Declare the most specific cases first and the catch-all ones last**: a type is detected as the first case, in
 * declaration order, whose rule accepts the address.
 */
interface UrlType extends UnitEnum
{
    public function accepts(Url $url): bool;
}
