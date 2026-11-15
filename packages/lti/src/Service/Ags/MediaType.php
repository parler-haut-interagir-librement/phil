<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Ags;

/**
 * The media types of the Assignment and Grade Services.
 */
final class MediaType
{
    public const string LINE_ITEM_CONTAINER = 'application/vnd.ims.lis.v2.lineitemcontainer+json';

    public const string LINE_ITEM = 'application/vnd.ims.lis.v2.lineitem+json';

    public const string SCORE = 'application/vnd.ims.lis.v1.score+json';

    public const string RESULT_CONTAINER = 'application/vnd.ims.lis.v2.resultcontainer+json';
}
