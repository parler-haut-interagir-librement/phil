<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use DateTimeInterface;
use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\Contract\ConsentCategories;
use Phil\LegalNotices\Contract\DocumentSource;
use Phil\LegalNotices\Contract\RetentionDurations;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * The documents of a site, read from their source: the version in force, its body rendered as HTML with the
 * placeholders filled, the retention periods and consent categories a privacy policy lists, the versions published in
 * a span of time, and the check of what a public document lacks. It keeps nothing between two calls.
 */
final readonly class LegalNotices
{
    public function __construct(
        private DocumentSource $source,
        private BodyRenderer $body,
        private SiteIdentity $identity,
        private ?RetentionDurations $retention = null,
        private ?ConsentCategories $consent = null,
        private string $fallbackLocale = 'en',
    ) {
    }

    /**
     * @throws InvalidLegalNotice when the source gives the history of another document
     */
    public function history(DocumentType $type): DocumentHistory
    {
        $history = $this->source->history($type);

        if ($history->type !== $type) {
            throw InvalidLegalNotice::because('other_type', \sprintf('The source gave the history of the %s for the %s.', $history->type->value, $type->value));
        }

        return $history;
    }

    public function inForce(DocumentType $type, DateTimeInterface $at): ?LegalDocumentVersion
    {
        return $this->history($type)->inForce($at);
    }

    public function identity(): SiteIdentity
    {
        return $this->identity;
    }

    /**
     * The body of a version in a locale, as HTML, its placeholders filled.
     *
     * @throws InvalidLegalNotice when the body cannot be rendered
     */
    public function render(LegalDocumentVersion $version, string $locale): string
    {
        return $this->placeholders($version, $locale)->fill($this->body->toHtml($version->format, $version->body($locale, $this->fallbackLocale)));
    }

    /**
     * @return list<RetentionDuration>
     */
    public function retention(): array
    {
        return $this->retention?->durations() ?? [];
    }

    /**
     * @return list<ConsentCategory>
     */
    public function consent(): array
    {
        return $this->consent?->categories() ?? [];
    }

    /**
     * The published versions whose publication falls in a span of time, both ends included, by document then number.
     *
     * @return list<LegalDocumentVersion>
     */
    public function publishedBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $published = [];

        foreach (DocumentType::cases() as $type) {
            foreach ($this->history($type)->versions as $version) {
                if (PublicationState::Published === $version->state && $version->publishedAt >= $from && $version->publishedAt <= $to) {
                    $published[] = $version;
                }
            }
        }

        return $published;
    }

    /**
     * What each document the public sees lacks: the fields of the identity it requires, then, version by version and
     * locale by locale, a body that cannot be rendered, a placeholder without a value or a placeholder written outside
     * the text, in a tag, a comment or a script, which is never filled.
     *
     * @return list<Problem>
     */
    public function check(): array
    {
        $problems = [];

        foreach (DocumentType::cases() as $type) {
            $versions = $this->history($type)->publicVersions();

            if ([] === $versions) {
                continue;
            }

            foreach ($this->identity->missing($type) as $field) {
                $problems[] = new Problem($type, 'missing_field', \sprintf('The %s requires the field "%s" of the identity.', $type->value, $field->value));
            }

            foreach ($versions as $version) {
                foreach ($version->locales() as $locale) {
                    array_push($problems, ...$this->checkBody($version, $locale));
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<Problem>
     */
    private function checkBody(LegalDocumentVersion $version, string $locale): array
    {
        try {
            $html = $this->body->toHtml($version->format, $version->body($locale, $locale));
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            return [new Problem($version->type, 'unreadable_body', $invalidLegalNotice->getMessage(), $version->number, $locale)];
        }

        $placeholders = $this->placeholders($version, $locale);

        return [
            ...array_map(
                static fn (string $name): Problem => new Problem($version->type, 'unknown_placeholder', \sprintf('Version %d in "%s" names "${%s}", which has no value.', $version->number, $locale, $name), $version->number, $locale),
                $placeholders->unknown($html),
            ),
            ...array_map(
                static fn (string $name): Problem => new Problem($version->type, 'misplaced_placeholder', \sprintf('Version %d in "%s" names "${%s}" outside the text, in a tag, a comment or a script, where it is not filled.', $version->number, $locale, $name), $version->number, $locale),
                $placeholders->misplaced($html),
            ),
        ];
    }

    private function placeholders(LegalDocumentVersion $version, string $locale): Placeholders
    {
        return Placeholders::of($version, $this->identity, $this->retention(), $this->consent(), $locale, $this->fallbackLocale);
    }
}
