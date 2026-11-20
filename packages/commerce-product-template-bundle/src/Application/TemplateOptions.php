<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The options of the products a template generated, for the core of commerce: the template's options, then the
 * design's, a design's option replacing the template's of the same code, each kept when its condition lists the
 * product's values. A product of the catalogue outside any group has none.
 *
 * Tagged `phil_commerce.sellable_options` by the core's autoconfiguration. It holds no state between two calls.
 */
final readonly class TemplateOptions implements SellableOptions
{
    public function __construct(
        private ProductGroupRepository $groups,
        private ProductTemplateRepository $templates,
        private ProductDesignRepository $designs,
        private string $sellableType,
    ) {
    }

    #[Override]
    public function supports(string $type): bool
    {
        return $type === $this->sellableType;
    }

    /**
     * @throws TemplateRefused for an option whose supplement counts in another currency than the order's
     */
    #[Override]
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        $member = $this->groups->findMember($reference->id);
        $group = $member instanceof GroupMember ? $this->groups->find($member->groupId) : null;

        if (!$member instanceof GroupMember || !$group instanceof ProductGroup) {
            return OptionSet::none();
        }

        $options = [];

        foreach ([...$this->templates->find($group->template)?->options() ?? [], ...$this->designs->find($group->design)?->options() ?? []] as $option) {
            unset($options[$option->definition->code]);

            if ($option->isOfferedOn($member->values())) {
                $this->checkCurrency($option->definition, $currency);
                $options[$option->definition->code] = $option->definition;
            }
        }

        return OptionSet::of(...$options);
    }

    private function checkCurrency(OptionDefinition $option, Currency $currency): void
    {
        $supplements = [$option->supplement, ...array_map(static fn (OptionChoice $choice): ?Money => $choice->supplement, $option->choices)];

        foreach ($supplements as $supplement) {
            if ($supplement instanceof Money && !$supplement->currency->equals($currency)) {
                throw TemplateRefused::currency(\sprintf('The option %s', $option->code), $supplement->currency->code, $currency->code);
            }
        }
    }
}
