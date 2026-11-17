<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Scorm;

use DOMElement;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Xml\XmlDocument;

/**
 * Reads `imsmanifest.xml`, SCORM 1.2 or 2004: the version from the schema version of its metadata, else from the
 * namespaces it declares; its organizations and their items; its resources, each launch file resolved against the
 * `xml:base` values and checked to stay inside the package; and what a runtime would enforce — mastery scores,
 * prerequisites, completion thresholds, sequencing —, kept for information. Bounded in size, in depth and in items.
 */
final readonly class ScormManifestReader
{
    public const string FILE = 'imsmanifest.xml';

    private const int SEQUENCING_BYTES = 65_536;

    private const int IDENTIFIER_LENGTH = 255;

    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
    ) {
    }

    /**
     * @throws PackageRefused when the manifest is unsafe, malformed, of an unknown version, or describes no course
     */
    public function read(string $xml): ScormManifest
    {
        $root = XmlDocument::root($xml, $this->limits->manifestBytes, 'The SCORM manifest');

        if ('manifest' !== $root->localName) {
            throw PackageRefused::because('The SCORM manifest has no <manifest> root.');
        }

        $metadata = XmlDocument::child($root, 'metadata');
        $schemaVersion = $metadata instanceof DOMElement ? (XmlDocument::childText($metadata, 'schemaversion') ?? '') : '';
        $format = $this->format($schemaVersion, $xml);
        $count = 0;
        $organizations = [];
        $default = null;
        $organizationsElement = XmlDocument::child($root, 'organizations');

        if ($organizationsElement instanceof DOMElement) {
            $default = XmlDocument::attribute($organizationsElement, 'default');

            foreach (XmlDocument::children($organizationsElement, 'organization') as $organization) {
                $organizations[] = $this->organization($organization, $count);
            }
        }

        $organizations = array_values(array_filter($organizations, static fn (ScormOrganization $organization): bool => [] !== $organization->items));

        if ([] === $organizations) {
            throw PackageRefused::because('The SCORM manifest describes no organization with items: a package of resources alone is not a course.');
        }

        $collection = XmlDocument::child($root, 'sequencingCollection');

        return new ScormManifest(
            $this->identifier(XmlDocument::attribute($root, 'identifier'), 'manifest'),
            $format,
            '' === $schemaVersion ? $format->value : $schemaVersion,
            $organizations,
            $this->resources($root),
            $default,
            $collection instanceof DOMElement ? XmlDocument::markup($collection, self::SEQUENCING_BYTES) : null,
        );
    }

    private function format(string $schemaVersion, string $xml): PackageFormat
    {
        if ('1.2' === $schemaVersion) {
            return PackageFormat::Scorm12;
        }

        if (str_contains($schemaVersion, '2004') || str_contains($schemaVersion, 'CAM 1.3') || str_starts_with($schemaVersion, '1.3')) {
            return PackageFormat::Scorm2004;
        }

        if (str_contains($xml, 'adlcp_v1p3')) {
            return PackageFormat::Scorm2004;
        }

        if (str_contains($xml, 'adlcp_rootv1p2')) {
            return PackageFormat::Scorm12;
        }

        throw PackageRefused::because(\sprintf('The SCORM manifest names no version the reader knows: "%s".', mb_substr($schemaVersion, 0, 60)));
    }

    private function organization(DOMElement $element, int &$count): ScormOrganization
    {
        $identifier = $this->identifier(XmlDocument::attribute($element, 'identifier'), 'organization');
        $items = [];

        foreach (XmlDocument::children($element, 'item') as $item) {
            $items[] = $this->item($item, 1, $count);
        }

        $sequencing = XmlDocument::child($element, 'sequencing');

        return new ScormOrganization(
            $identifier,
            XmlDocument::childText($element, 'title') ?? $identifier,
            $items,
            $sequencing instanceof DOMElement ? XmlDocument::markup($sequencing, self::SEQUENCING_BYTES) : null,
        );
    }

    private function item(DOMElement $element, int $depth, int &$count): ScormItem
    {
        if ($depth > $this->limits->depth) {
            throw PackageRefused::because(\sprintf('The SCORM manifest nests its items deeper than %d levels.', $this->limits->depth));
        }

        if (++$count > $this->limits->items) {
            throw PackageRefused::because(\sprintf('The SCORM manifest describes more than %d items.', $this->limits->items));
        }

        $identifier = $this->identifier(XmlDocument::attribute($element, 'identifier'), 'item');
        $children = [];

        foreach (XmlDocument::children($element, 'item') as $child) {
            $children[] = $this->item($child, $depth + 1, $count);
        }

        $sequencing = XmlDocument::child($element, 'sequencing');
        $reference = XmlDocument::attribute($element, 'identifierref');

        return new ScormItem(
            $identifier,
            XmlDocument::childText($element, 'title') ?? $identifier,
            null === $reference || '' === $reference ? null : $reference,
            XmlDocument::attribute($element, 'parameters'),
            'false' !== strtolower(XmlDocument::attribute($element, 'isvisible') ?? 'true'),
            $children,
            $this->masteryScore(XmlDocument::childText($element, 'masteryscore')),
            XmlDocument::childText($element, 'prerequisites'),
            $this->completionThreshold($element),
            $sequencing instanceof DOMElement ? XmlDocument::markup($sequencing, self::SEQUENCING_BYTES) : null,
        );
    }

    /**
     * @return array<string, ScormResource>
     */
    private function resources(DOMElement $root): array
    {
        $resources = [];
        $element = XmlDocument::child($root, 'resources');

        if (!$element instanceof DOMElement) {
            return [];
        }

        $base = XmlDocument::attribute($element, 'base');

        foreach (XmlDocument::children($element, 'resource') as $resource) {
            if (\count($resources) >= $this->limits->items) {
                throw PackageRefused::because(\sprintf('The SCORM manifest describes more than %d resources.', $this->limits->items));
            }

            // An empty base adds an empty segment, which the resolution drops.
            $bases = [$base ?? '', XmlDocument::attribute($resource, 'base') ?? ''];
            $identifier = $this->identifier(XmlDocument::attribute($resource, 'identifier'), 'resource');
            $resources[$identifier] = new ScormResource(
                $identifier,
                strtolower(XmlDocument::attribute($resource, 'scormtype', 'scormType') ?? 'asset'),
                self::href($bases, XmlDocument::attribute($resource, 'href')),
                array_values(array_filter(array_map(
                    static fn (DOMElement $file): ?string => self::href($bases, XmlDocument::attribute($file, 'href')),
                    XmlDocument::children($resource, 'file'),
                ), static fn (?string $file): bool => null !== $file)),
                array_values(array_filter(array_map(
                    static fn (DOMElement $dependency): ?string => XmlDocument::attribute($dependency, 'identifierref'),
                    XmlDocument::children($resource, 'dependency'),
                ), static fn (?string $dependency): bool => null !== $dependency && '' !== $dependency)),
            );
        }

        return $resources;
    }

    /**
     * @param list<string> $bases
     */
    private static function href(array $bases, ?string $href): ?string
    {
        if (null === $href || '' === $href) {
            return null;
        }

        if (PackagePath::isWebAddress($href)) {
            return $href;
        }

        [$path, $suffix] = PackagePath::resolve($bases, $href);

        return $path.($suffix ?? '');
    }

    private function identifier(?string $value, string $what): string
    {
        if (null === $value || '' === $value || \strlen($value) > self::IDENTIFIER_LENGTH) {
            throw PackageRefused::because(\sprintf('An %s of the SCORM manifest has no identifier of 1 to %d bytes.', $what, self::IDENTIFIER_LENGTH));
        }

        return $value;
    }

    /**
     * The mastery score of SCORM 1.2, out of a hundred, as a scaled score; null when absent or not a number.
     */
    private function masteryScore(?string $value): ?float
    {
        if (null === $value || !is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        return $score >= 0.0 && $score <= 100.0 ? $score / 100 : null;
    }

    /**
     * The completion threshold of SCORM 2004: the text of the element, or its `minProgressMeasure`.
     */
    private function completionThreshold(DOMElement $item): ?float
    {
        $element = XmlDocument::child($item, 'completionThreshold');

        if (!$element instanceof DOMElement) {
            return null;
        }

        $value = XmlDocument::attribute($element, 'minProgressMeasure') ?? XmlDocument::text($element);

        if (!is_numeric($value)) {
            return null;
        }

        $threshold = (float) $value;

        return $threshold >= 0.0 && $threshold <= 1.0 ? $threshold : null;
    }
}
