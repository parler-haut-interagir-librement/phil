<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use DOMElement;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Scorm\ScormManifestReader;
use Phil\ElearningPackages\Warnings;
use Phil\ElearningPackages\Xml\XmlDocument;

/**
 * Reads the activity tree of an organization of `imsmanifest.xml`. For SCORM 2004, the IMS Simple Sequencing of the
 * organization and of each item — with what the `<imsss:sequencingCollection>` entry its `IDRef` names adds, element
 * by element —: control modes, sequencing rules, limit conditions, rollup rules and considerations, objectives and
 * their maps to global objectives, randomization and delivery controls. For SCORM 1.2, the prerequisites of each item,
 * and flow and choice open on every cluster. The manifest is first read and checked by `ScormManifestReader`, within
 * the same limits; the rules, conditions, objectives and maps of an activity are bounded too. A value the reader cannot
 * use is noted and its default kept; a rule it cannot use is noted and left out.
 */
final readonly class SequencingReader
{
    public const int MAX_RULES = 32;

    public const int MAX_CONDITIONS = 16;

    public const int MAX_OBJECTIVES = 64;

    public const int MAX_MAPS = 16;

    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
    ) {
    }

    /**
     * @param ?string $organization the identifier of the organization; the default one of the manifest when null
     *
     * @throws PackageRefused when the manifest is refused by `ScormManifestReader`, has no such organization, or an
     *                        activity declares more rules, conditions, objectives or maps than the bounds allow
     */
    public function read(string $xml, ?string $organization = null): ActivityTree
    {
        $manifest = new ScormManifestReader($this->limits)->read($xml);
        $identifier = $organization ?? $manifest->organization()->identifier;
        $root = XmlDocument::root($xml, $this->limits, 'The SCORM manifest');
        $organizations = XmlDocument::child($root, 'organizations');
        $element = $organizations instanceof DOMElement ? array_find(
            XmlDocument::children($organizations, 'organization'),
            static fn (DOMElement $candidate): bool => XmlDocument::attribute($candidate, 'identifier') === $identifier,
        ) : null;

        if (!$element instanceof DOMElement) {
            throw PackageRefused::because(\sprintf('The SCORM manifest has no organization "%s".', mb_substr($identifier, 0, 120)));
        }

        $collection = [];
        $collectionElement = XmlDocument::child($root, 'sequencingCollection');

        foreach ($collectionElement instanceof DOMElement ? XmlDocument::children($collectionElement, 'sequencing') : [] as $sequencing) {
            $id = XmlDocument::attribute($sequencing, 'ID');

            if (null !== $id && '' !== $id) {
                $collection[$id] ??= $sequencing;
            }
        }

        $warnings = new Warnings();
        $activity = new SequencingBuilder($manifest->format, $collection, $warnings)->activity($element, false);

        return new ActivityTree($activity, $manifest->format, $warnings->all());
    }
}
