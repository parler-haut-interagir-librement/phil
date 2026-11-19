<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Option\OptionKind;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PrintZone as TextZone;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceProductTemplate\Exception\PlanRefused;
use Phil\CommerceProductTemplate\Face;
use Phil\CommerceProductTemplate\PrintZone;
use Phil\CommerceProductTemplate\Variant;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\Port\ImageStore;
use Phil\CommerceProductTemplateBundle\Application\Port\PrintFonts;
use Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Print\PrintElementKind;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceCode;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintFile;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionLine;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\ProductionOrder;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\PrintCompositorException;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintFiles;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\TextItem;
use Phil\PrintCompositor\Zone;
use RuntimeException;

/**
 * Produces an order for the printer: the print files of each product generated from a template, composed by
 * `phil/print-compositor`, and the production order that lists them.
 *
 * For each unit of each line, each zone of the template that prints something gets one print file per ink: the
 * artwork the plan places there, then the elements of the design's print layout that apply — fixed images, the text of
 * a text option in its imposed font and rectangle, the image of the choice made, the objects the dice picked. Units
 * printed alike make one line of the production order; units whose draws differ make one each.
 *
 * A print file is named by a digest of what it is made of, so producing an order again composes nothing already in
 * the store and writes the same production order: it is idempotent. An order is produced once it is placed and its
 * draws are made. It holds no state between two calls.
 */
final readonly class PrintProduction
{
    public function __construct(
        private OrderRepository $orders,
        private ProductTemplateRepository $templates,
        private ProductDesignRepository $designs,
        private ProductGroupRepository $groups,
        private VariantPlanner $planner,
        private TemplateOptions $options,
        private ImageStore $images,
        private ProductionStore $store,
        private PrintFonts $fonts,
        private PrintCompositor $compositor,
        private string $sellableType,
        private string $defaultLocale,
        private string $filesPrefix,
        private string $ordersPrefix,
    ) {
    }

    /**
     * The path of the JSON production order of an order in the store; the CSV one ends in `.csv` instead. A number
     * of letters, digits, ".", "_" and "-" names its file; any other is written in hexadecimal under `hex/`.
     */
    public function orderPath(string $number): string
    {
        $name = 1 === preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,63}$/D', $number) ? $number : 'hex/'.sodium_bin2hex($number);

        return $this->ordersPrefix.'/'.$name.'.json';
    }

    /**
     * Composes what the store lacks of the order's print files, and writes its production order, in JSON and CSV, when
     * it holds a product generated from a template.
     *
     * @throws TemplateRefused  for an unknown order, an order not placed, a line with draws still to make, a product its
     *                          plan no longer gives, an unknown font, a draw that does not read, or a print file the
     *                          compositor refuses — a text that does not fit, a character its font lacks
     * @throws RuntimeException for a store that cannot write
     */
    public function produce(string $orderNumber): ProductionOrder
    {
        $order = $this->orders->find($orderNumber) ?? throw TemplateRefused::unknownOrder($orderNumber);

        if (OrderState::Placed !== $order->state()) {
            throw TemplateRefused::orderNotPlaced($orderNumber, $order->state()->value);
        }

        $lines = [];

        foreach ($order->lines() as $line) {
            $member = $line->reference->type === $this->sellableType ? $this->groups->findMember($line->reference->id) : null;

            if ($member instanceof GroupMember) {
                if ([] !== $line->configuration->pending()) {
                    throw TemplateRefused::drawsPending($orderNumber, $line->reference->value());
                }

                $lines[] = [$line, $member];
            }
        }

        $production = [];
        $cache = new ProductionCache();

        foreach ($lines as [$line, $member]) {
            foreach ($this->linesOf($order, $line, $member, $cache) as $productionLine) {
                $production[] = $productionLine;
            }
        }

        $productionOrder = new ProductionOrder($orderNumber, $production);

        if (!$productionOrder->isEmpty()) {
            $json = $this->orderPath($orderNumber);
            $this->writeIfChanged($json, $productionOrder->json());
            $this->writeIfChanged(substr($json, 0, -4).'csv', $productionOrder->csv());
        }

        return $productionOrder;
    }

    /**
     * @return list<ProductionLine>
     */
    private function linesOf(Order $order, OrderLine $line, GroupMember $member, ProductionCache $cache): array
    {
        $variant = $this->variant($member, $cache);
        $design = $this->designs->find($variant->design->code) ?? throw TemplateRefused::unknownDesign($variant->design->code);
        $layout = $design->printLayout();
        $options = $this->options->optionsOf($line->reference->unconfigured(), $order->currency);
        $values = $variant->combination->codes();
        $choices = [];

        foreach ($line->configuration->values as $code => $value) {
            $kind = $options->option($code)?->kind;

            if ((OptionKind::Choice === $kind || OptionKind::Ink === $kind) && null !== $value->value) {
                $choices[$code] = $value->value;
            }
        }

        $applying = array_values(array_filter($layout, static fn (PrintElement $element): bool => $element->appliesTo($values, $choices)));
        $axisValues = [];

        foreach ($variant->combination->values as $axis => $axisValue) {
            $axisValues[$axis] = ['code' => $axisValue->code, 'label' => $axisValue->label->in($variant->locale($this->defaultLocale)), 'supplier' => $axisValue->supplierName];
        }

        /** @var array<string, ProductionLine> $units */
        $units = [];

        for ($unit = 1; $unit <= $line->quantity; ++$unit) {
            $unitOptions = [];

            foreach ($line->configuration->values as $code => $value) {
                $unitOptions[$code] = $value->value ?? $value->draws[$unit - 1] ?? '';
            }

            $dice = $this->dice($variant->sku, $applying, $unitOptions);
            $files = $this->files($variant, $applying, $unitOptions, $dice, $options, $cache);
            $shown = array_replace($unitOptions, array_map(static fn (DiceCode $code): string => $code->code(), $dice));
            $key = json_encode([$shown, $files], \JSON_THROW_ON_ERROR);

            $units[$key] = isset($units[$key])
                ? $units[$key]->plusOne()
                : new ProductionLine($variant->sku, 1, $variant->language?->code, $axisValues, $shown, array_map(static fn (DiceCode $code): string => $code->code(), $dice), $files);
        }

        return array_values($units);
    }

    private function variant(GroupMember $member, ProductionCache $cache): Variant
    {
        if (!isset($cache->plans[$member->groupId])) {
            $group = $this->groups->find($member->groupId) ?? throw TemplateRefused::unknownGroup($member->groupId);
            $template = $this->templates->find($group->template) ?? throw TemplateRefused::unknownTemplate($group->template);
            $design = $this->designs->find($group->design) ?? throw TemplateRefused::unknownDesign($group->design);

            try {
                $variants = $this->planner->plan($template->template(), $design->design());
            } catch (PlanRefused $planRefused) {
                throw TemplateRefused::plan($planRefused);
            }

            $cache->plans[$member->groupId] = [];

            foreach ($variants as $variant) {
                $cache->plans[$member->groupId][$variant->sku] = $variant;
            }
        }

        return $cache->plans[$member->groupId][$member->sku] ?? throw TemplateRefused::notPlanned($member->sku);
    }

    /**
     * The dice of each dice element's option drawn on the unit, read against its slots.
     *
     * @param list<PrintElement>    $elements
     * @param array<string, string> $unitOptions
     *
     * @return array<string, DiceCode>
     */
    private function dice(string $sku, array $elements, array $unitOptions): array
    {
        $dice = [];

        foreach ($elements as $element) {
            $option = (string) $element->option;

            if (PrintElementKind::Dice !== $element->kind || isset($dice[$option]) || '' === ($unitOptions[$option] ?? '')) {
                continue;
            }

            $sides = $element->sides();
            \assert([] !== $sides);

            try {
                $dice[$option] = DiceCode::read($unitOptions[$option], $sides);
            } catch (InvalidValue $invalidValue) {
                throw TemplateRefused::draw($sku, $option, $invalidValue);
            }
        }

        return $dice;
    }

    /**
     * @param list<PrintElement>      $elements
     * @param array<string, string>   $unitOptions
     * @param array<string, DiceCode> $dice
     *
     * @return list<PrintFile>
     */
    private function files(Variant $variant, array $elements, array $unitOptions, array $dice, OptionSet $options, ProductionCache $cache): array
    {
        $files = [];

        foreach ($variant->template->faces() as $face) {
            foreach ($face->zones() as $zone) {
                foreach ($this->zoneFiles($variant, $face, $zone, $elements, $unitOptions, $dice, $options, $cache) as $file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * @param list<PrintElement>      $elements
     * @param array<string, string>   $unitOptions
     * @param array<string, DiceCode> $dice
     *
     * @return list<PrintFile>
     */
    private function zoneFiles(Variant $variant, Face $face, PrintZone $zone, array $elements, array $unitOptions, array $dice, OptionSet $options, ProductionCache $cache): array
    {
        /** @var list<array{ink: string, items: list<array{kind: string, box: Box, image?: string, text?: string, font?: string, colour?: string, max?: float, min?: float}>}> $layers */
        $layers = [];

        foreach ($variant->artworks as $artwork) {
            if ($artwork->face->code === $face->code && $artwork->zone->name === $zone->name) {
                $layers[] = ['ink' => Layer::DEFAULT_INK, 'items' => [['kind' => 'image', 'box' => new Box(0, 0, $zone->width, $zone->height), 'image' => $artwork->path]]];
            }
        }

        foreach ($elements as $element) {
            if ($element->face !== $face->code || $element->zone !== $zone->name) {
                continue;
            }

            $items = $this->items($element, $unitOptions, $dice, $options);

            if ([] !== $items) {
                $layers[] = ['ink' => $element->ink, 'items' => $items];
            }
        }

        if ([] === $layers) {
            return [];
        }

        $paths = [];

        foreach (array_unique(array_map(static fn (array $layer): string => $layer['ink'], $layers)) as $ink) {
            $paths[$ink] = $this->filesPrefix.'/'.$this->digest($zone, array_filter($layers, static fn (array $layer): bool => $layer['ink'] === $ink), $cache).'-'.$ink.'.png';
        }

        $missing = array_filter($paths, fn (string $path): bool => !isset($cache->written[$path]) && !$this->store->has($path));

        if ([] !== $missing) {
            $composed = $this->compose($variant->sku, $face->code, $zone, $layers, $cache);

            foreach ($missing as $ink => $path) {
                $this->store->write($path, $composed->png($ink));
            }
        }

        $files = [];

        foreach ($paths as $ink => $path) {
            $cache->written[$path] = true;
            $files[] = new PrintFile($face->code, $zone->name, $ink, $zone->width, $zone->height, $zone->dpi, $path);
        }

        return $files;
    }

    /**
     * What the print file of one ink is made of, each image and font by the digest of its bytes: the name of its file.
     *
     * @param array<int, array{ink: string, items: list<array{kind: string, box: Box, image?: string, text?: string, font?: string, colour?: string, max?: float, min?: float}>}> $layers
     */
    private function digest(PrintZone $zone, array $layers, ProductionCache $cache): string
    {
        $description = [$zone->name, $zone->width, $zone->height, $zone->dpi];

        foreach ($layers as $layer) {
            $items = [];

            foreach ($layer['items'] as $item) {
                $items[] = [
                    $item['kind'], $item['box']->x, $item['box']->y, $item['box']->width, $item['box']->height,
                    isset($item['image']) ? hash('sha256', $this->image($item['image'], $cache)) : null,
                    isset($item['font']) ? $this->fontDigest($item['font'], $cache) : null,
                    $item['text'] ?? null, $item['colour'] ?? null, $item['max'] ?? null, $item['min'] ?? null,
                ];
            }

            $description[] = [$layer['ink'], $items];
        }

        return substr(hash('sha256', json_encode($description, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION)), 0, 32);
    }

    /**
     * @param array<string, string>   $unitOptions
     * @param array<string, DiceCode> $dice
     *
     * @return list<array{kind: string, box: Box, image?: string, text?: string, font?: string, colour?: string, max?: float, min?: float}>
     */
    private function items(PrintElement $element, array $unitOptions, array $dice, OptionSet $options): array
    {
        $option = (string) $element->option;
        $value = $unitOptions[$option] ?? null;

        return match ($element->kind) {
            PrintElementKind::Image => [['kind' => 'image', 'box' => $this->box($element), 'image' => (string) $element->path]],
            PrintElementKind::Choice => null !== $value && isset($element->images[$value]) ? [['kind' => 'image', 'box' => $this->box($element), 'image' => $element->images[$value]]] : [],
            PrintElementKind::Dice => isset($dice[$option]) ? array_map(
                static fn (int $index, int $result): array => ['kind' => 'image', 'box' => $element->slots[$index]->box, 'image' => $element->slots[$index]->image($result)],
                array_keys($dice[$option]->results),
                $dice[$option]->results,
            ) : [],
            PrintElementKind::Text => null === $value ? [] : [$this->textItem($element, $value, $options->option($option))],
        };
    }

    /**
     * @return array{kind: string, box: Box, text: string, font: string, colour: string, max: float, min: float}
     */
    private function textItem(PrintElement $element, string $text, ?OptionDefinition $option): array
    {
        $constraints = $option?->text;

        if (!$constraints instanceof TextConstraints) {
            throw TemplateRefused::textOption((string) $element->option, 'the product has no text option of that code');
        }

        $font = $element->font ?? $constraints->font ?? throw TemplateRefused::textOption((string) $element->option, 'neither the element nor the option names its font');
        $zone = $constraints->zone;
        $box = $element->box ?? ($zone instanceof TextZone ? new Box($zone->x, $zone->y, $zone->width, $zone->height) : throw TemplateRefused::textOption((string) $element->option, 'neither the element nor the option gives its rectangle'));

        return ['kind' => 'text', 'box' => $box, 'text' => $text, 'font' => $font, 'colour' => $element->colour->toHex(), 'max' => $element->maxSize, 'min' => $element->minSize];
    }

    /**
     * @param non-empty-list<array{ink: string, items: list<array{kind: string, box: Box, image?: string, text?: string, font?: string, colour?: string, max?: float, min?: float}>}> $layers
     */
    private function compose(string $sku, string $face, PrintZone $zone, array $layers, ProductionCache $cache): PrintFiles
    {
        try {
            $jobLayers = [];

            foreach ($layers as $index => $layer) {
                $items = [];

                foreach ($layer['items'] as $item) {
                    $items[] = isset($item['text'], $item['font'], $item['colour'], $item['max'], $item['min'])
                        ? new TextItem($item['text'], $this->fonts->typeFace($item['font']), $item['box'], Colour::fromHex($item['colour']), $item['max'], $item['min'])
                        : new ImageItem(Raster::fromBytes($this->image($item['image'] ?? '', $cache)), $item['box']);
                }

                $jobLayers[] = new Layer('layer-'.$index, $items, $layer['ink']);
            }

            return $this->compositor->compose(new PrintJob(new Zone($zone->name, $zone->width, $zone->height), $jobLayers, $zone->dpi));
        } catch (PrintCompositorException $printCompositorException) {
            throw TemplateRefused::printFile($sku, $face, $zone->name, $printCompositorException);
        }
    }

    private function image(string $path, ProductionCache $cache): string
    {
        return $cache->images[$path] ??= $this->images->read($path) ?? throw TemplateRefused::missingImage($path);
    }

    private function fontDigest(string $name, ProductionCache $cache): string
    {
        return $cache->fonts[$name] ??= (string) hash_file('sha256', $this->fonts->typeFace($name)->path);
    }

    /**
     * The box of an image or choice element, which always has one.
     */
    private function box(PrintElement $element): Box
    {
        \assert($element->box instanceof Box);

        return $element->box;
    }

    private function writeIfChanged(string $path, string $bytes): void
    {
        if ($this->store->read($path) !== $bytes) {
            $this->store->write($path, $bytes);
        }
    }
}
