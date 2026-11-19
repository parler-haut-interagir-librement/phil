<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Rma;

/**
 * Why an item comes back.
 */
enum ReturnReason: string
{
    case Defective = 'defective';
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    /**
     * Whether the shop is at fault: no restocking fee is charged then.
     */
    public function isShopsFault(): bool
    {
        return match ($this) {
            self::Defective, self::Damaged, self::WrongItem, self::NotAsDescribed => true,
            self::ChangedMind, self::Other => false,
        };
    }
}
