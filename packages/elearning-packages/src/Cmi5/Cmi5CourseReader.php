<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

use DOMElement;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Xml\XmlDocument;

/**
 * Reads a cmi5 course structure, `cmi5.xml`: the course, its blocks and assignable units in order, each with its IRI,
 * title and description in the language asked for — else the first one written —, and for each unit its URL,
 * `moveOn`, `masteryScore`, `launchMethod`, `activityType`, `launchParameters` and `entitlementKey`. The identifiers
 * are unique, the values those the specification allows, and the structure bounded in size, depth and units.
 */
final readonly class Cmi5CourseReader
{
    public const string FILE = 'cmi5.xml';

    private const int IRI_LENGTH = 255;

    private const int URL_LENGTH = 2048;

    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
        private ?string $language = null,
    ) {
    }

    /**
     * @throws PackageRefused when the structure is unsafe, malformed, or holds a value cmi5 does not allow
     */
    public function read(string $xml): Cmi5Course
    {
        $root = XmlDocument::root($xml, $this->limits->manifestBytes, 'The cmi5 course structure');

        if ('courseStructure' !== $root->localName) {
            throw PackageRefused::because('The cmi5 course structure has no <courseStructure> root.');
        }

        $course = XmlDocument::child($root, 'course') ?? throw PackageRefused::because('The cmi5 course structure has no <course>.');
        $id = $this->iri(XmlDocument::attribute($course, 'id'), 'The course');
        $seen = [$id => $id];
        $count = 0;
        $children = $this->children($root, 1, $seen, $count);

        if (!self::holdsUnits($children)) {
            throw PackageRefused::because('The cmi5 course structure holds no assignable unit.');
        }

        return new Cmi5Course(
            $id,
            $this->langString($course, 'title') ?? $id,
            $children,
            $this->langString($course, 'description') ?? '',
            XmlDocument::child($root, 'objectives') instanceof DOMElement,
        );
    }

    /**
     * @param list<Cmi5Block|Cmi5Au> $children
     */
    private static function holdsUnits(array $children): bool
    {
        return null !== array_find($children, static fn (Cmi5Block|Cmi5Au $child): bool => $child instanceof Cmi5Au || self::holdsUnits($child->children));
    }

    /**
     * @param array<string, string> $seen
     *
     * @return list<Cmi5Block|Cmi5Au>
     */
    private function children(DOMElement $parent, int $depth, array &$seen, int &$count): array
    {
        if ($depth > $this->limits->depth) {
            throw PackageRefused::because(\sprintf('The cmi5 course structure nests its blocks deeper than %d levels.', $this->limits->depth));
        }

        $children = [];

        foreach ($parent->childNodes as $node) {
            if (!$node instanceof DOMElement || !\in_array($node->localName, ['block', 'au'], true)) {
                continue;
            }

            if (++$count > $this->limits->items) {
                throw PackageRefused::because(\sprintf('The cmi5 course structure describes more than %d blocks and units.', $this->limits->items));
            }

            $id = $this->iri(XmlDocument::attribute($node, 'id'), 'au' === $node->localName ? 'An assignable unit' : 'A block');

            if (isset($seen[$id])) {
                throw PackageRefused::because(\sprintf('The identifier "%s" is used twice in the cmi5 course structure.', $id));
            }

            $seen[$id] = $id;
            $children[] = 'au' === $node->localName ? $this->au($node, $id) : $this->block($node, $id, $depth, $seen, $count);
        }

        return $children;
    }

    /**
     * @param array<string, string> $seen
     */
    private function block(DOMElement $element, string $id, int $depth, array &$seen, int &$count): Cmi5Block
    {
        return new Cmi5Block($id, $this->langString($element, 'title') ?? $id, $this->children($element, $depth + 1, $seen, $count), $this->langString($element, 'description') ?? '');
    }

    private function au(DOMElement $element, string $id): Cmi5Au
    {
        $url = XmlDocument::childText($element, 'url');

        if (null === $url || '' === $url || \strlen($url) > self::URL_LENGTH) {
            throw PackageRefused::because(\sprintf('The assignable unit "%s" has no URL of 1 to %d bytes.', $id, self::URL_LENGTH));
        }

        $moveOn = XmlDocument::attribute($element, 'moveOn') ?? MoveOn::NotApplicable->value;
        $method = XmlDocument::attribute($element, 'launchMethod') ?? LaunchMethod::AnyWindow->value;

        return new Cmi5Au(
            $id,
            $this->langString($element, 'title') ?? $id,
            $url,
            MoveOn::tryFrom($moveOn) ?? throw PackageRefused::because(\sprintf('The moveOn "%s" of the assignable unit "%s" is not one cmi5 defines.', mb_substr($moveOn, 0, 40), $id)),
            $this->masteryScore(XmlDocument::attribute($element, 'masteryScore'), $id),
            LaunchMethod::tryFrom($method) ?? throw PackageRefused::because(\sprintf('The launchMethod "%s" of the assignable unit "%s" is not one cmi5 defines.', mb_substr($method, 0, 40), $id)),
            $this->langString($element, 'description') ?? '',
            XmlDocument::childText($element, 'launchParameters'),
            XmlDocument::childText($element, 'entitlementKey'),
            XmlDocument::attribute($element, 'activityType'),
        );
    }

    /**
     * The text of the element's `<langstring>` in the language asked for, else its first; null when it has none.
     */
    private function langString(DOMElement $parent, string $localName): ?string
    {
        $element = XmlDocument::child($parent, $localName);

        if (!$element instanceof DOMElement) {
            return null;
        }

        $strings = XmlDocument::children($element, 'langstring');

        if ([] === $strings) {
            return null;
        }

        $chosen = null === $this->language ? null : array_find($strings, fn (DOMElement $string): bool => str_starts_with(strtolower(XmlDocument::attribute($string, 'lang') ?? ''), strtolower($this->language ?? '')));
        $text = XmlDocument::text($chosen ?? $strings[0]);

        return '' === $text ? null : $text;
    }

    private function iri(?string $value, string $what): string
    {
        if (null === $value || '' === $value || \strlen($value) > self::IRI_LENGTH || 1 === preg_match('/\s/', $value)) {
            throw PackageRefused::because(\sprintf('%s of the cmi5 course structure has no identifier of 1 to %d bytes without spaces.', $what, self::IRI_LENGTH));
        }

        return $value;
    }

    private function masteryScore(?string $value, string $id): ?float
    {
        if (null === $value) {
            return null;
        }

        $score = is_numeric($value) ? (float) $value : -1.0;

        if ($score < 0.0 || $score > 1.0) {
            throw PackageRefused::because(\sprintf('The masteryScore of the assignable unit "%s" is not a decimal from 0 to 1.', $id));
        }

        return $score;
    }
}
