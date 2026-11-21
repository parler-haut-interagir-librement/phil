<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The titles and headings of the training reports, in the `phil_reporting` domain of the translator, in the locale a
 * request names with its `locale` parameter or the translator's own; in English without a translator.
 */
final readonly class TrainingHeadings
{
    public const string DOMAIN = 'phil_reporting';

    /** @var array<string, string> */
    public const array ENGLISH = [
        'training.activities' => 'Activities',
        'training.costs' => 'Costs',
        'training.currency' => 'Currency',
        'training.eligible_costs' => 'Eligible costs',
        'training.end' => 'End',
        'training.hours' => 'Hours',
        'training.level' => 'Level',
        'training.no_unit' => 'No unit',
        'training.quebec.above_threshold' => 'Payroll above the threshold',
        'training.quebec.payroll' => 'Payroll',
        'training.quebec.ratio' => 'Eligible costs over payroll (%)',
        'training.quebec.required' => 'Required investment (1%)',
        'training.quebec.shortfall' => 'Shortfall',
        'training.quebec.title' => 'Workforce skills development: 1% of payroll (Quebec)',
        'training.start' => 'Start',
        'training.title' => 'Training hours and costs',
        'training.unit' => 'Unit',
        'training.unit_name' => 'Unit name',
    ];

    private const string LOCALE = '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8}){0,3}$/D';

    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @param key-of<self::ENGLISH> $id
     */
    public function text(string $id, ?ReportRequest $request = null): string
    {
        if (!$this->translator instanceof TranslatorInterface) {
            return self::ENGLISH[$id];
        }

        $text = $this->translator->trans($id, [], self::DOMAIN, $request instanceof ReportRequest ? $this->locale($request) : null);

        return $text === $id ? self::ENGLISH[$id] : $text;
    }

    /**
     * @param list<key-of<self::ENGLISH>> $ids
     *
     * @return list<string>
     */
    public function texts(array $ids, ReportRequest $request): array
    {
        return array_map(fn (string $id): string => $this->text($id, $request), $ids);
    }

    /**
     * @throws InvalidReportJob when the `locale` parameter is not a locale
     */
    private function locale(ReportRequest $request): ?string
    {
        $locale = $request->parameter('locale');

        if (null === $locale) {
            return null;
        }

        if (!\is_string($locale) || 1 !== preg_match(self::LOCALE, $locale)) {
            throw InvalidReportJob::because('the parameter "locale" is not a locale');
        }

        return $locale;
    }
}
