<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

/**
 * A content item of Deep Linking 2.0, picked in the tool and sent back to the platform.
 */
interface ContentItem
{
    /**
     * `ltiResourceLink`, `link`, `file`, `html` or `image`.
     */
    public function type(): string;

    /**
     * The item as the content items claim writes it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
