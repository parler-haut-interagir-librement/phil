<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;

/**
 * Reads a product group as its page shows it. It holds no state between two calls.
 */
final readonly class ProductGroups
{
    public function __construct(
        private ProductGroupRepository $groups,
        private ProductTemplateRepository $templates,
        private ProductDesignRepository $designs,
    ) {
    }

    /**
     * The page of the group of a design on a template, in the locale given, or `null` for a group withdrawn.
     *
     * @throws TemplateRefused for a group, template or design that does not exist
     */
    public function page(string $design, string $template, string $locale): ?GroupPage
    {
        $id = ProductGroup::idOf($design, $template);
        $group = $this->groups->find($id) ?? throw TemplateRefused::unknownGroup($id);

        if (!$group->isActive()) {
            return null;
        }

        $definition = ($this->templates->find($template) ?? throw TemplateRefused::unknownTemplate($template))->template();
        $designDefinition = ($this->designs->find($design) ?? throw TemplateRefused::unknownDesign($design))->design();
        $members = array_values(array_filter($this->groups->membersOf($id), static fn (GroupMember $member): bool => $member->isActive()));
        $axes = [];

        foreach ($definition->axes() as $axis) {
            $used = array_flip(array_map(static fn (GroupMember $member): string => $member->values()[$axis->code] ?? '', $members));
            $values = array_values(array_filter($axis->values(), static fn (AxisValue $value): bool => isset($used[$value->code])));
            $axes[] = [
                'code' => $axis->code,
                'label' => $axis->label->in($locale),
                'values' => array_map(static fn (AxisValue $value): array => [
                    'code' => $value->code,
                    'label' => $value->label->in($locale),
                    'hex' => $value->swatch?->hex,
                    'photo' => $value->swatch?->photo,
                ], $values),
            ];
        }

        return new GroupPage($id, $designDefinition->name->in($locale).' – '.$definition->name->in($locale), $axes, array_map(static fn (GroupMember $member): array => [
            'sku' => $member->sku,
            'values' => $member->values(),
            'language' => $member->language(),
            'mockups' => $member->mockups(),
        ], $members));
    }
}
