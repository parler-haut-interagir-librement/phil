<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Contract;

use Phil\MediaBundle\Domain\Model\Media;

/**
 * How a medium was declared to be made, as the address of an IPTC digital source type: the value written back into the
 * images rendered from it, so that the marking of generated or edited media survives the stripping of metadata.
 */
interface DeclaredSourceType
{
    /**
     * The address, `http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia`; `null` when the medium
     * declares none.
     */
    public function uriOf(Media $media): ?string;
}
