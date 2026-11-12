<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure;

/**
 * A part of a work whose making is declared on its own: the text of a book and its illustrations, the music and the
 * images of a game, the code of a module.
 */
enum Part: string
{
    case Text = 'text';

    case Image = 'image';

    case Audio = 'audio';

    case Video = 'video';

    case Code = 'code';
}
