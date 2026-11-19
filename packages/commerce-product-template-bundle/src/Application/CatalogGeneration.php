<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceProductTemplate\Exception\PlanRefused;
use Phil\CommerceProductTemplate\Variant;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\GenerationReport;

/**
 * Generates the products of a design made on a template in the catalogue, and keeps them as one group.
 *
 * Generating is idempotent: it plans the products again, creates those the catalogue lacks, updates those it holds —
 * name, price, tax category, attributes, channels —, restores those withdrawn that the plan has again, and withdraws
 * the members the plan no longer has. Nothing is ever deleted. Every check runs before the first change, so a refused
 * generation leaves the catalogue as it was. The application flushes its unit of work once a generation returns.
 *
 * It holds no state between two calls.
 */
final readonly class CatalogGeneration
{
    public function __construct(
        private ProductTemplateRepository $templates,
        private ProductDesignRepository $designs,
        private ProductGroupRepository $groups,
        private ProductRepository $products,
        private VariantPlanner $planner,
        private string $defaultLocale,
    ) {
    }

    /**
     * @throws TemplateRefused for an unknown template or design, a plan refused, a SKU the catalogue holds for something
     *                         else, or a product whose kind or currency the template changed
     */
    public function generate(string $design, string $template): GenerationReport
    {
        $productTemplate = $this->templates->find($template) ?? throw TemplateRefused::unknownTemplate($template);
        $productDesign = $this->designs->find($design) ?? throw TemplateRefused::unknownDesign($design);

        try {
            $variants = $this->planner->plan($productTemplate->template(), $productDesign->design());
        } catch (PlanRefused $planRefused) {
            throw TemplateRefused::plan($planRefused);
        }

        $groupId = ProductGroup::idOf($productDesign->code, $productTemplate->code);
        $existing = $this->checked($variants, $groupId, $productTemplate);

        $group = $this->groups->find($groupId);

        if (!$group instanceof ProductGroup) {
            $group = ProductGroup::of($productDesign->code, $productTemplate->code);
            $this->groups->add($group);
        }

        $group->restore();
        $report = ['created' => [], 'updated' => [], 'restored' => [], 'unchanged' => [], 'withdrawn' => []];
        $planned = [];

        foreach ($variants as $variant) {
            $planned[$variant->sku] = $variant->sku;
            [$member, $product] = $existing[$variant->sku];
            $report[$this->apply($variant, $groupId, $productTemplate, $member, $product)][] = $variant->sku;
        }

        foreach ($this->groups->membersOf($groupId) as $member) {
            if (!isset($planned[$member->sku]) && $this->withdrawMember($member)) {
                $report['withdrawn'][] = $member->sku;
            }
        }

        return new GenerationReport($report['created'], $report['updated'], $report['restored'], $report['unchanged'], $report['withdrawn']);
    }

    /**
     * Withdraws the group of a design on a template, its members and their products; generating it again restores
     * them.
     *
     * @throws TemplateRefused for a group that does not exist
     */
    public function withdraw(string $design, string $template): GenerationReport
    {
        $groupId = ProductGroup::idOf($design, $template);
        $group = $this->groups->find($groupId) ?? throw TemplateRefused::unknownGroup($groupId);
        $group->withdraw();

        $withdrawn = [];

        foreach ($this->groups->membersOf($groupId) as $member) {
            if ($this->withdrawMember($member)) {
                $withdrawn[] = $member->sku;
            }
        }

        return new GenerationReport(withdrawn: $withdrawn);
    }

    /**
     * Each planned SKU's member and product, checked before anything changes.
     *
     * @param list<Variant> $variants
     *
     * @return array<string, array{GroupMember|null, Product|null}>
     */
    private function checked(array $variants, string $groupId, ProductTemplate $template): array
    {
        $existing = [];

        foreach ($variants as $variant) {
            $member = $this->groups->findMember($variant->sku);
            $product = $this->products->find($variant->sku);

            if ($member instanceof GroupMember && $member->groupId !== $groupId) {
                throw TemplateRefused::foreignSku($variant->sku, $member->groupId);
            }

            if (!$member instanceof GroupMember && $product instanceof Product) {
                throw TemplateRefused::foreignSku($variant->sku, null);
            }

            if ($product instanceof Product && $product->kind !== $template->kind()) {
                throw TemplateRefused::kind($variant->sku, $product->kind->value);
            }

            if ($product instanceof Product && !$product->price->currency->equals($variant->price->currency)) {
                throw TemplateRefused::currency(\sprintf('The product %s', $variant->sku), $product->price->currency->code, $variant->price->currency->code);
            }

            $existing[$variant->sku] = [$member, $product];
        }

        return $existing;
    }

    /**
     * @return 'created'|'updated'|'restored'|'unchanged'
     */
    private function apply(Variant $variant, string $groupId, ProductTemplate $template, ?GroupMember $member, ?Product $product): string
    {
        $values = $variant->combination->codes();
        $language = $variant->language?->code;

        if (!$member instanceof GroupMember) {
            $this->groups->addMember(GroupMember::of($variant->sku, $groupId, $values, $language));
        } else {
            $member->place($values, $language);
            $member->restore();
        }

        if (!$product instanceof Product) {
            $product = Product::create($variant->sku, $template->kind(), $variant->name($this->defaultLocale), $variant->price, $template->taxCategory());
            $this->describe($product, $variant);
            $this->products->add($product);

            return 'created';
        }

        $before = $this->snapshot($product);
        $product->rename($variant->name($this->defaultLocale));
        $product->reprice($variant->price);
        $product->classify($template->taxCategory());
        $this->describe($product, $variant);
        $product->restore();

        return match (true) {
            !$before['active'] => 'restored',
            $before !== $this->snapshot($product) => 'updated',
            default => 'unchanged',
        };
    }

    private function describe(Product $product, Variant $variant): void
    {
        $attributes = $variant->attributes();

        foreach (array_keys($product->attributes()) as $attribute) {
            if (!isset($attributes[$attribute])) {
                $product->describe($attribute, null);
            }
        }

        foreach ($attributes as $attribute => $value) {
            $product->describe($attribute, $value);
        }

        $product->sellThrough($variant->channels);
    }

    /**
     * @return array{name: string, price: int, tax: string, attributes: array<string, string>, channels: list<string>, active: bool}
     */
    private function snapshot(Product $product): array
    {
        return [
            'name' => $product->name(),
            'price' => $product->price->minorUnits,
            'tax' => $product->taxCategory(),
            'attributes' => $product->attributes(),
            'channels' => $product->channels(),
            'active' => $product->isActive(),
        ];
    }

    /**
     * Withdraws the member and its product; whether the member was still active.
     */
    private function withdrawMember(GroupMember $member): bool
    {
        $wasActive = $member->isActive();
        $member->withdraw();
        $this->products->find($member->sku)?->withdraw();

        return $wasActive;
    }
}
