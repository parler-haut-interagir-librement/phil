<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Status;

/**
 * What a restriction rests on, as a statement of reasons says it:
 *
 * - `Terms`: the content goes against the terms or the rules of the site;
 * - `Illegal`: the content is held to be illegal, under the law the statement names.
 */
enum Ground: string
{
    case Terms = 'terms';
    case Illegal = 'illegal';
}
