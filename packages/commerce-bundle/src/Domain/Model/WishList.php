<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceBundle\Domain\ValueObject\Identifier;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\WishListItem;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Uid\Uuid;

/**
 * A wish list: what its owner wishes for, shared by a link so that others buy from it. Each thing counts how many are
 * wished for and how many were bought, never who bought them.
 *
 * The link's token is shown once, when the list is shared; only its SHA-256 is stored, so a stolen database gives no
 * link. Sharing again gives a new token and the old link stops working.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_wish_list')]
#[ORM\Index(columns: ['owner'])]
#[ORM\UniqueConstraint(columns: ['share_hash'])]
final class WishList
{
    /** The most things one list holds. */
    public const int MAX_ITEMS = 500;

    /** @var array<string, array{wanted: int, bought: int}> keyed by the reference's value */
    #[ORM\Column(name: 'items', type: 'json')]
    private array $items = [];

    #[ORM\Column(name: 'share_hash', length: 64, nullable: true, options: ['fixed' => true])]
    private ?string $shareHash = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'owner', length: 255)]
        public readonly string $owner,
        #[ORM\Column(name: 'name', length: 255)]
        private string $name,
        #[ORM\Column(name: 'created_at')]
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param string $owner the owner's stable identifier
     */
    public static function create(string $owner, string $name, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), Identifier::check($owner, 'wish list owner'), self::checkedName($name), $at);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = self::checkedName($name);
    }

    /**
     * Wishes for more of a thing, or for a new one.
     */
    public function wish(SellableReference $reference, int $quantity): void
    {
        $key = $reference->value();
        $item = $this->items[$key] ?? null;

        if (null === $item && \count($this->items) >= self::MAX_ITEMS) {
            throw InvalidValue::because(\sprintf('A wish list holds at most %d things.', self::MAX_ITEMS));
        }

        if ($quantity < 1) {
            throw InvalidValue::because('A thing is wished for by one unit or more.');
        }

        $checked = WishListItem::of($reference, ($item['wanted'] ?? 0) + $quantity, $item['bought'] ?? 0);
        $this->items[$key] = ['wanted' => $checked->wanted, 'bought' => $checked->bought];
    }

    public function forget(SellableReference $reference): void
    {
        unset($this->items[$reference->value()]);
    }

    /**
     * Counts units bought by others, never more than are still wished for; a thing no longer on the list is ignored.
     *
     * @return int how many units counted
     */
    public function recordPurchase(SellableReference $reference, int $quantity): int
    {
        $key = $reference->value();

        if (!isset($this->items[$key]) || $quantity < 1) {
            return 0;
        }

        $counted = min($quantity, $this->items[$key]['wanted'] - $this->items[$key]['bought']);
        $this->items[$key]['bought'] += $counted;

        return $counted;
    }

    public function item(SellableReference $reference): ?WishListItem
    {
        $item = $this->items[$reference->value()] ?? null;

        return null === $item ? null : WishListItem::of($reference, $item['wanted'], $item['bought']);
    }

    /**
     * @return list<WishListItem> in the order first wished for
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->items as $key => $item) {
            $items[] = WishListItem::of(SellableReference::fromValue($key), $item['wanted'], $item['bought']);
        }

        return $items;
    }

    /**
     * Shares the list under the SHA-256 of a new token, which the caller shows once; the old link stops working.
     */
    public function shareUnder(string $tokenHash): void
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $tokenHash)) {
            throw InvalidValue::because('A wish list is shared under the hexadecimal SHA-256 of its token.');
        }

        $this->shareHash = $tokenHash;
    }

    public function stopSharing(): void
    {
        $this->shareHash = null;
    }

    public function isShared(): bool
    {
        return null !== $this->shareHash;
    }

    /**
     * Whether the list is shared under that hash, compared in constant time.
     */
    public function isSharedUnder(string $tokenHash): bool
    {
        return null !== $this->shareHash && hash_equals($this->shareHash, $tokenHash);
    }

    public function version(): int
    {
        return $this->version;
    }

    private static function checkedName(string $name): string
    {
        if ('' === trim($name) || mb_strlen($name) > 255 || 1 !== preg_match('/^\P{Cc}+$/Du', $name)) {
            throw InvalidValue::because('A wish list has a name: 1 to 255 characters on one line.');
        }

        return $name;
    }
}
