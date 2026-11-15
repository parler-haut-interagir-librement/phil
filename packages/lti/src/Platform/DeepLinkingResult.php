<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Contract\ContentItem;

/**
 * A deep linking response the platform verified: the tool and deployment it comes from, the items picked, the data the
 * platform put in the request, and the messages for the person and for the logs.
 */
final readonly class DeepLinkingResult
{
    /**
     * @param list<ContentItem> $items
     */
    public function __construct(
        public string $clientId,
        public string $deploymentId,
        public array $items,
        public ?string $data = null,
        public ?string $message = null,
        public ?string $log = null,
        public ?string $errorMessage = null,
        public ?string $errorLog = null,
    ) {
    }
}
