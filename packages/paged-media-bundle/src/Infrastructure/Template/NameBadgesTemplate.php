<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Template;

use Override;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Template\Badge;
use Phil\PagedMedia\Template\LabelSheet;
use Phil\PagedMedia\Template\NameBadges;
use Phil\PagedMedia\Template\TemplateFonts;
use Phil\PagedMediaBundle\Application\AsDocumentTemplate;
use Phil\PagedMediaBundle\Application\DocumentTemplate;

/**
 * `paged_media.name_badges`: `event`, `badges` (each `name`, `subtitle`, `organisation`), and `accent`, `sheet` and
 * `title` when wanted.
 */
#[AsDocumentTemplate(self::NAME)]
final readonly class NameBadgesTemplate implements DocumentTemplate
{
    public const string NAME = 'paged_media.name_badges';

    public function __construct(private TemplateFonts $fonts = new TemplateFonts())
    {
    }

    #[Override]
    public function build(array $parameters): Document
    {
        $read = new Parameters(self::NAME, $parameters);
        $badges = array_map(static fn (Parameters $badge): Badge => new Badge($badge->string('name'), $badge->optionalString('subtitle'), $badge->optionalString('organisation')), $read->list('badges'));

        return new NameBadges($read->string('event'), $read->sheet(LabelSheet::letter6()), $this->fonts, $read->color('accent'), metadata: $read->metadata())->render($badges);
    }
}
