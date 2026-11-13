<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\Calendar;
use Phil\PagedMedia\Template\CalendarMonth;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.calendar`: `weekdays` (seven names from the first day), `months` (each `year`, `month`, `title`,
 * and `events`, lists of strings by day of the month), and `first_day`, `accent` and `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class CalendarTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.calendar';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $months = array_map(static function (Parameters $month): CalendarMonth {
            $events = $month->object('events');
            $byDay = [];

            foreach ($events->integerKeys() as $day) {
                $byDay[$day] = $events->strings((string) $day);
            }

            return new CalendarMonth($month->int('year', 0), $month->int('month', 0), $month->string('title'), $byDay);
        }, $read->list('months'));

        return new Calendar($read->strings('weekdays'), $read->int('first_day', 1), fonts: $this->fonts, accent: $read->color('accent'), metadata: $read->metadata())->render($months);
    }
}
