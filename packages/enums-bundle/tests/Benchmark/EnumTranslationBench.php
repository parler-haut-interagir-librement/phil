<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Benchmark;

use Generator;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;
use Phil\EnumsBundle\Infrastructure\Twig\EnumsExtension;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\TicketKind;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use UnitEnum;

/**
 * The enum_label filter and the serializer's labelled output resolve a translation key and domain for every case of
 * a list, so a table or an API page of a thousand rows pays it a thousand times.
 */
final class EnumTranslationBench
{
    private readonly EnumsExtension $extension;

    private readonly EnumsExtension $untranslated;

    private readonly EnumsNormalizer $normalizer;

    /**
     * @var array<int, list<UnitEnum>>
     */
    private array $labelled = [];

    /**
     * @var array<int, list<UnitEnum>>
     */
    private array $generated = [];

    public function __construct()
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Pending' => 'En attente', 'In transit' => 'En transit', 'Waiting for payment.' => 'En attente du paiement.'], 'fr', 'enums');
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Rapport de bogue'], 'fr', 'support');

        $this->extension = new EnumsExtension($translator);
        $this->untranslated = new EnumsExtension();
        $this->normalizer = new EnumsNormalizer($translator);

        foreach ([10, 100, 1_000] as $size) {
            $this->labelled[$size] = $this->cycle(OrderStatus::cases(), $size);
            $this->generated[$size] = $this->cycle(TicketKind::cases(), $size);
        }

        // Load the catalogues and read the attributes once, as every request after the first finds them.
        foreach ([...OrderStatus::cases(), ...TicketKind::cases()] as $case) {
            $this->extension->label($case);
            $this->normalizer->normalize($case, 'json', [EnumsNormalizer::LABELS => true]);
        }
    }

    /**
     * @ParamProviders("sizes")
     *
     * @param array{size: int} $params
     */
    public function benchTwigLabelTranslated(array $params): void
    {
        foreach ($this->labelled[$params['size']] as $case) {
            $this->extension->label($case);
        }
    }

    /**
     * @ParamProviders("sizes")
     *
     * @param array{size: int} $params
     */
    public function benchTwigLabelUntranslated(array $params): void
    {
        foreach ($this->labelled[$params['size']] as $case) {
            $this->untranslated->label($case);
        }
    }

    /**
     * @ParamProviders("sizes")
     *
     * @param array{size: int} $params
     */
    public function benchGeneratedLabelKey(array $params): void
    {
        foreach ($this->generated[$params['size']] as $case) {
            EnumTranslation::labelKey($case);
            EnumTranslation::domain($case);
        }
    }

    /**
     * @ParamProviders("sizes")
     *
     * @param array{size: int} $params
     */
    public function benchNormalizeWithLabels(array $params): void
    {
        foreach ($this->labelled[$params['size']] as $case) {
            $this->normalizer->normalize($case, 'json', [EnumsNormalizer::LABELS => true]);
        }
    }

    /**
     * @return Generator<string, array{size: int}>
     */
    public function sizes(): Generator
    {
        yield '10 rows' => ['size' => 10];
        yield '100 rows' => ['size' => 100];
        yield '1000 rows' => ['size' => 1_000];
    }

    /**
     * @param list<UnitEnum> $cases
     *
     * @return list<UnitEnum>
     */
    private function cycle(array $cases, int $size): array
    {
        return array_map(static fn (int $index): UnitEnum => $cases[$index % \count($cases)], range(0, $size - 1));
    }
}
