<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use DateTimeInterface;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Every version of one document, by number: the one in force at a moment, the next one to take effect, and the list
 * the public sees. Its versions are of its type, each number once, a thousand at most.
 */
final readonly class DocumentHistory
{
    public const int MAX_VERSIONS = 1000;

    /**
     * @var list<LegalDocumentVersion> by number, the oldest first
     */
    public array $versions;

    /**
     * @param iterable<LegalDocumentVersion> $versions
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(
        public DocumentType $type,
        iterable $versions = [],
    ) {
        $byNumber = [];

        foreach ($versions as $version) {
            if ($version->type !== $type) {
                throw InvalidLegalNotice::because('other_type', \sprintf('Version %d is a version of the %s, not of the %s.', $version->number, $version->type->value, $type->value));
            }

            if (isset($byNumber[$version->number])) {
                throw InvalidLegalNotice::because('duplicate_number', \sprintf('The %s has two versions %d.', $type->value, $version->number));
            }

            if (self::MAX_VERSIONS === \count($byNumber)) {
                throw InvalidLegalNotice::because('too_many_versions', \sprintf('The %s has 1000 versions at most.', $type->value));
            }

            $byNumber[$version->number] = $version;
        }

        ksort($byNumber);
        $this->versions = array_values($byNumber);
    }

    /**
     * The published version in force at a moment: the one that took effect last, the highest number on the same date.
     */
    public function inForce(DateTimeInterface $at): ?LegalDocumentVersion
    {
        $found = null;

        foreach ($this->versions as $version) {
            if ($version->isInForceAt($at) && (null === $found || $version->effectiveAt >= $found->effectiveAt)) {
                $found = $version;
            }
        }

        return $found;
    }

    /**
     * The published version that takes effect next after a moment, the highest number on the same date.
     */
    public function upcoming(DateTimeInterface $at): ?LegalDocumentVersion
    {
        $found = null;

        foreach ($this->versions as $version) {
            if (PublicationState::Published === $version->state && $version->effectiveAt > $at && (null === $found || $version->effectiveAt <= $found->effectiveAt)) {
                $found = $version;
            }
        }

        return $found;
    }

    /**
     * The versions the public sees — published or withdrawn —, the newest first.
     *
     * @return list<LegalDocumentVersion>
     */
    public function publicVersions(): array
    {
        $public = [];

        foreach ($this->versions as $version) {
            if ($version->isPublic()) {
                array_unshift($public, $version);
            }
        }

        return $public;
    }

    /**
     * A version the public sees, by number.
     */
    public function find(int $number): ?LegalDocumentVersion
    {
        foreach ($this->versions as $version) {
            if ($number === $version->number && $version->isPublic()) {
                return $version;
            }
        }

        return null;
    }
}
