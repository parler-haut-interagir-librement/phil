<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application;

use DateTimeImmutable;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;

/**
 * A short link as a form fills it in, before `ShortLinks` checks it: a code left empty is generated.
 */
final class ShortLinkDraft
{
    public ?string $target = null;

    public ?string $code = null;

    public ?string $label = null;

    public ?string $locale = null;

    public RedirectStatus $status = RedirectStatus::Found;

    public bool $keepQuery = false;

    public bool $enabled = true;

    public ?DateTimeImmutable $validFrom = null;

    public ?DateTimeImmutable $validUntil = null;

    public ?int $maxVisits = null;

    public static function of(ShortLink $link): self
    {
        $draft = new self();
        $draft->target = $link->target();
        $draft->code = $link->code();
        $draft->label = $link->label();
        $draft->locale = $link->locale();
        $draft->status = $link->status();
        $draft->keepQuery = QueryPolicy::Keep === $link->query();
        $draft->enabled = $link->isEnabled();
        $draft->validFrom = $link->validFrom();
        $draft->validUntil = $link->validUntil();
        $draft->maxVisits = $link->maxVisits();

        return $draft;
    }

    public function terms(): LinkTerms
    {
        $label = null === $this->label ? null : trim($this->label);
        $locale = null === $this->locale || '' === $this->locale ? null : $this->locale;

        return new LinkTerms($this->status, $this->keepQuery ? QueryPolicy::Keep : QueryPolicy::Drop, '' === $label ? null : $label, $locale, $this->enabled, $this->validFrom, $this->validUntil, $this->maxVisits);
    }
}
