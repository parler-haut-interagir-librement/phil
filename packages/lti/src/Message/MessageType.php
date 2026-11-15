<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * The messages of LTI 1.3 Core and Deep Linking 2.0.
 */
enum MessageType: string
{
    /** A launch of a resource link: a learner or an instructor opens the tool from the platform. */
    case ResourceLink = 'LtiResourceLinkRequest';

    /** A launch that asks the tool to pick content and send it back to the platform. */
    case DeepLinking = 'LtiDeepLinkingRequest';

    /** The answer of the tool to a deep linking request: the content items picked. */
    case DeepLinkingResponse = 'LtiDeepLinkingResponse';
}
