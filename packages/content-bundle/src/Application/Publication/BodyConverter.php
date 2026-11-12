<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\Body;

/**
 * Turns the body of a draft into the body its published row stores: **the source converted into UJO** — UJO
 * (Universal JSON Output), the block document format of `phil/ujo` —, the format `ujo`, and the HTML compiled from
 * that UJO, purified.
 *
 * A draft keeps the format its author writes in — Markdown, BBCode, MediaWiki, DokuWiki, Creole, HTML, plain text,
 * UJO, or a format an add-on brings —; `ContentPublisher` calls the converter on every publication, so every live row
 * holds one format, whatever the draft was written in, and a block editor opens any published text. A body already in UJO is
 * read again, which puts its JSON in canonical form. The UJO is dated with the moment of the publication, its `time`
 * in milliseconds since the Unix epoch.
 */
interface BodyConverter
{
    /**
     * @throws InvalidContent when the source cannot be read in its format: UJO that is not valid
     */
    public function toUjo(Body $body, DateTimeImmutable $publishedAt): Body;
}
