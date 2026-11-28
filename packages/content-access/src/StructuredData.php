<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The schema.org properties that tell search engines a content is reserved, `isAccessibleForFree` and `hasPart`
 * naming the reserved part by a CSS selector, so that a page showing less to a visitor than to a subscriber is not
 * taken for cloaking. They follow the policy as an anonymous visitor meets it, never the current viewer, so that the
 * markup is the same for everyone.
 */
final class StructuredData
{
    private function __construct()
    {
    }

    /**
     * The properties to merge into the content's JSON-LD.
     *
     * @param string $reservedSelector the CSS selector of the element that holds the reserved part, such as `.reserved`
     *
     * @return array{isAccessibleForFree: bool, hasPart?: list<array{'@type': string, isAccessibleForFree: bool, cssSelector: string}>}
     *
     * @throws InvalidArgumentException when the selector is empty, longer than 255 bytes or holds a control character or
     *                                  a `<`
     */
    public static function of(AccessPolicy $policy, DateTimeImmutable $at, string $reservedSelector = '.reserved'): array
    {
        if ('' === trim($reservedSelector) || \strlen($reservedSelector) > 255 || 1 === preg_match('/[\x00-\x1F\x7F<]/', $reservedSelector)) {
            throw new InvalidArgumentException('The selector of the reserved part is empty, longer than 255 bytes, or holds a control character or a "<".');
        }

        if ($policy->isPublicAt($at)) {
            return ['isAccessibleForFree' => true];
        }

        return [
            'isAccessibleForFree' => false,
            'hasPart' => [['@type' => 'WebPageElement', 'isAccessibleForFree' => false, 'cssSelector' => $reservedSelector]],
        ];
    }
}
