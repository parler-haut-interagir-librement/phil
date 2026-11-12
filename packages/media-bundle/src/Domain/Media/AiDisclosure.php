<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Media;

/**
 * What a medium owes to artificial intelligence, as the people who see it are told.
 */
enum AiDisclosure: string
{
    /** Made by people, without generation. */
    case None = 'none';

    /** Made by people, with the help of a generative tool: a filled background, an enhanced photo. */
    case Assisted = 'assisted';

    /** Generated. */
    case Generated = 'generated';
}
