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

    private const int LAUNCH_DATA_LENGTH = 4096;

    private const array TIME_LIMIT_ACTIONS = ['exit,message', 'exit,no message', 'continue,message', 'continue,no message'];

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
            $this->bounded(XmlDocument::childText($element, 'datafromlms') ?? XmlDocument::childText($element, 'dataFromLMS'), self::LAUNCH_DATA_LENGTH),
            $this->maxTimeAllowed($element, $sequencing),
            $this->timeLimitAction(XmlDocument::childText($element, 'timelimitaction') ?? XmlDocument::childText($element, 'timeLimitAction')),
            $sequencing instanceof DOMElement ? $this->scaledPassingScore($sequencing) : null,
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

    private function bounded(?string $value, int $length): ?string
    {
        return null === $value || '' === $value ? null : mb_substr($value, 0, $length);
    }

    /**
     * The time allowed: `adlcp:maxtimeallowed` of SCORM 1.2, `HHHH:MM:SS`, or the `attemptAbsoluteDurationLimit` of the
     * limit conditions of SCORM 2004, a duration of ISO 8601; null when absent or not a span of time.
     */
    private function maxTimeAllowed(DOMElement $item, ?DOMElement $sequencing): ?string
    {
        $value = XmlDocument::childText($item, 'maxtimeallowed');

        if (null === $value && $sequencing instanceof DOMElement) {
            $limits = XmlDocument::child($sequencing, 'limitConditions');
            $value = $limits instanceof DOMElement ? XmlDocument::attribute($limits, 'attemptAbsoluteDurationLimit') : null;
        }

        if (null === $value) {
            return null;
        }

        return 1 === preg_match('/^(?:\d{2,4}:[0-5]\d:[0-5]\d(?:\.\d{1,2})?|P(?=\d|T\d)(?:\d+Y)?(?:\d+M)?(?:\d+D)?(?:T(?=\d)(?:\d+H)?(?:\d+M)?(?:\d+(?:\.\d{1,2})?S)?)?)$/D', $value) ? $value : null;
    }

    private function timeLimitAction(?string $value): ?string
    {
        $value = null === $value ? null : strtolower($value);

        return \in_array($value, self::TIME_LIMIT_ACTIONS, true) ? $value : null;
    }

    /**
     * The `minNormalizedMeasure` of the primary objective of SCORM 2004, when the objective is satisfied by it: the
     * scaled passing score of the SCO.
     */
    private function scaledPassingScore(DOMElement $sequencing): ?float
    {
        $objectives = XmlDocument::child($sequencing, 'objectives');
        $primary = $objectives instanceof DOMElement ? XmlDocument::child($objectives, 'primaryObjective') : null;

        if (!$primary instanceof DOMElement || 'true' !== strtolower(XmlDocument::attribute($primary, 'satisfiedByMeasure') ?? 'false')) {
            return null;
        }

        $value = XmlDocument::childText($primary, 'minNormalizedMeasure') ?? '1.0';

        if (!is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        return $score >= -1.0 && $score <= 1.0 ? $score : null;
    }
}
