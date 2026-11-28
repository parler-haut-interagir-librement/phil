<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Status;

/**
 * Where the draft of a template stands for a site and a locale: never published, or taken offline; published, with
 * changes the sites do not send yet; or sent exactly as it is. The values are the places of the editorial workflow of
 * `phil/content-bundle`.
 */
enum PublicationState: string
{
    case Unpublished = 'unpublished';
    case Pending = 'draft';
    case Published = 'published';
}
