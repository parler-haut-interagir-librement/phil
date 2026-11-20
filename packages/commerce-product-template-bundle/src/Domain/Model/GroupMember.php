<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A product of a group, by its SKU: the values it has on each axis, its language version, whether the group still
 * sells it, and the path of its mockup on each face.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_product_group_member')]
#[ORM\Index(columns: ['group_id'])]
final class GroupMember
{
    #[ORM\Column(name: 'active')]
    private bool $active = true;

    /** @var array<string, string> by face code */
    #[ORM\Column(name: 'mockups', type: 'json')]
    private array $mockups = [];

    /**
     * @param array<string, string> $values
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'sku', length: 64)]
        public readonly string $sku,
        #[ORM\Column(name: 'group_id', length: 65)]
        public readonly string $groupId,
        #[ORM\Column(name: 'axis_values', type: 'json')]
        private array $values,
        #[ORM\Column(name: 'language', length: 8, nullable: true)]
        private ?string $language
    ) {
    }

    /**
     * @param array<string, string> $values the code of its value on each axis, by axis code
     */
    public static function of(string $sku, string $groupId, array $values, ?string $language): self
    {
        return new self($sku, $groupId, self::checkedValues($sku, $values), $language);
    }

    /**
     * @return array<string, string> the code of its value on each axis, by axis code
     */
    public function values(): array
    {
        return $this->values;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    /**
     * Its values and language as the last plan gives them.
     *
     * @param array<string, string> $values
     */
    public function place(array $values, ?string $language): void
    {
        $this->values = self::checkedValues($this->sku, $values);
        $this->language = $language;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function withdraw(): void
    {
        $this->active = false;
    }

    public function restore(): void
    {
        $this->active = true;
    }

    /**
     * @return array<string, string> the path of its mockup, by face code
     */
    public function mockups(): array
    {
        return $this->mockups;
    }

    /**
     * Replaces the paths of its mockups, by face code.
     *
     * @param array<string, string> $mockups
     */
    public function showMockups(array $mockups): void
    {
        ksort($mockups);
        $this->mockups = $mockups;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private static function checkedValues(string $sku, array $values): array
    {
        if ([] === $values) {
            throw InvalidValue::because(\sprintf('The member %s has a value on one axis at least.', $sku));
        }

        return $values;
    }
}
