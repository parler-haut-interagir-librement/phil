<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

/**
 * A rule whose dates of a year fall in that year, or at most a week out of it — `saturday after 12-31` —: the almanac
 * reads only the years a period needs, not the two before and the one after it. Every rule of the library is one, an
 * `Offset` when its rule is; a rule of an application that is not is read over the wider years.
 */
interface WithinTheYear extends Rule
{
}
