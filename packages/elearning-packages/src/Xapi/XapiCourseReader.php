<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

use DOMElement;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Xml\XmlDocument;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Reads the `tincan.xml` of an xAPI package: its activities in order, each with its IRI, its type, its names and
 * descriptions by language, the file or address that launches it and the interaction type of a question. A launch
 * file is resolved and checked to stay inside the package; an absolute address is read by `phil/url` and kept in its
 * canonical form. What can be set aside — a missing name, an IRI used twice, an unknown interaction type — is noted as
 * an `XapiWarning`; a package with no launchable activity, an activity without an IRI, or a launch that leaves the
 * package is refused. Bounded as the other manifests are, in size, XML nodes and activities.
 */
final readonly class XapiCourseReader
{
    public const string FILE = 'tincan.xml';

    public const int MAX_LANGUAGES = 32;

    private const int IRI_LENGTH = 2048;

    private const int LANGUAGE_LENGTH = 35;

    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
        private ?string $language = null,
    ) {
    }

    /**
     * @throws PackageRefused when the document is unsafe or malformed, declares no launchable activity, an activity
     *                        without an IRI, more activities than the limits allow, or a launch that is not a file of
     *                        the package nor an absolute web address
     */
    public function read(string $xml): XapiCourse
    {
        $root = XmlDocument::root($xml, $this->limits, 'The xAPI package description');

        if ('tincan' !== $root->localName) {
            throw PackageRefused::because('The xAPI package description has no <tincan> root.');
        }

        $list = XmlDocument::child($root, 'activities') ?? throw PackageRefused::because('The xAPI package description has no <activities>.');
        $elements = XmlDocument::children($list, 'activity');

        if (\count($elements) > $this->limits->items) {
            throw PackageRefused::because(\sprintf('The xAPI package description declares more than %d activities.', $this->limits->items));
        }

        $activities = [];
        $warnings = [];

        foreach ($elements as $element) {
            $activity = $this->activity($element, $warnings);

            if (isset($activities[$activity->id])) {
                $warnings[] = new XapiWarning(XapiWarningKind::DuplicateActivity, $activity->id, \sprintf('The activity "%s" is declared twice: the second is left out.', $activity->id));

                continue;
            }

            $activities[$activity->id] = $activity;
        }

        $activities = array_values($activities);
        $launchable = array_values(array_filter($activities, static fn (XapiActivity $activity): bool => $activity->isLaunchable()));
        $course = array_find($launchable, static fn (XapiActivity $activity): bool => XapiActivity::COURSE === $activity->type)
            ?? $launchable[0]
            ?? throw PackageRefused::because('The xAPI package description declares no activity with a <launch>.');

        return new XapiCourse($course, $course->name($this->language), $activities, $warnings);
    }

    /**
     * @param list<XapiWarning> $warnings
     */
    private function activity(DOMElement $element, array &$warnings): XapiActivity
    {
        $id = XmlDocument::attribute($element, 'id');

        if (null === $id || !$this->isIri($id)) {
            throw PackageRefused::because(\sprintf('An activity of the xAPI package description has no IRI of 1 to %d bytes.', self::IRI_LENGTH));
        }

        $type = XmlDocument::attribute($element, 'type');

        if (null !== $type && !$this->isIri($type)) {
            $warnings[] = new XapiWarning(XapiWarningKind::InvalidActivityType, $id, \sprintf('The type of the activity "%s" is not an IRI: it is left out.', $id));
            $type = null;
        }

        $names = $this->texts($element, 'name', $id, $warnings);

        if ([] === $names) {
            $warnings[] = new XapiWarning(XapiWarningKind::UnnamedActivity, $id, \sprintf('The activity "%s" has no name: its IRI stands for it.', $id));
        }

        return new XapiActivity(
            $id,
            $type,
            $names,
            $this->texts($element, 'description', $id, $warnings),
            $this->launch($element, $id),
            $this->interactionType($element, $id, $type, $warnings),
        );
    }

    /**
     * The texts of the children of that local name by their language tag, the first of each tag kept, empty ones
     * left out.
     *
     * @param list<XapiWarning> $warnings
     *
     * @return array<string, string>
     */
    private function texts(DOMElement $element, string $localName, string $id, array &$warnings): array
    {
        $texts = [];

        foreach (XmlDocument::children($element, $localName) as $child) {
            $text = XmlDocument::text($child);

            if ('' === $text) {
                continue;
            }

            $tag = $this->tag(XmlDocument::attribute($child, 'lang'));

            if (isset($texts[$tag])) {
                continue;
            }

            if (\count($texts) >= self::MAX_LANGUAGES) {
                $warnings[] = new XapiWarning(XapiWarningKind::TooManyLanguages, $id, \sprintf('The %s of the activity "%s" is written in more than %d languages: the others are left out.', $localName, $id, self::MAX_LANGUAGES));

                break;
            }

            $texts[$tag] = $text;
        }

        return $texts;
    }

    /**
     * The launch in the language asked for, else the first written, resolved inside the package or read as an
     * absolute web address.
     */
    private function launch(DOMElement $element, string $id): ?string
    {
        $launches = [];

        foreach (XmlDocument::children($element, 'launch') as $child) {
            $text = XmlDocument::text($child);

            if ('' !== $text) {
                $launches[$this->tag(XmlDocument::attribute($child, 'lang'))] ??= $text;
            }
        }

        if ([] === $launches) {
            return null;
        }

        $launch = new XapiActivity($id, names: $launches)->name($this->language);

        if (PackagePath::isWebAddress($launch)) {
            try {
                return Url::fromString($launch)->toString();
            } catch (UrlException $exception) {
                throw PackageRefused::because(\sprintf('The launch address of the activity "%s" is refused: %s', $id, $exception->getMessage()));
            }
        }

        [$path, $suffix] = PackagePath::resolve([], $launch);

        return $path.($suffix ?? '');
    }

    /**
     * @param list<XapiWarning> $warnings
     */
    private function interactionType(DOMElement $element, string $id, ?string $type, array &$warnings): ?InteractionType
    {
        $value = XmlDocument::childText($element, 'interactionType');

        if (null === $value || '' === $value) {
            return null;
        }

        $interaction = InteractionType::tryFrom(strtolower($value));

        if (!$interaction instanceof InteractionType) {
            $warnings[] = new XapiWarning(XapiWarningKind::UnknownInteractionType, $id, \sprintf('The interaction type "%s" of the activity "%s" is not one xAPI defines: it is left out.', mb_substr($value, 0, 40), $id));

            return null;
        }

        if (XapiActivity::INTERACTION !== $type) {
            $warnings[] = new XapiWarning(XapiWarningKind::InteractionTypeWithoutInteraction, $id, \sprintf('The activity "%s" names an interaction type without being of type cmi.interaction.', $id));
        }

        return $interaction;
    }

    private function isIri(string $value): bool
    {
        return \strlen($value) <= self::IRI_LENGTH && 1 === preg_match('/^[a-z][a-z0-9+.\-]*:[^\s]+$/iD', $value);
    }

    /**
     * The language tag in lowercase, or `und` when it is absent or is not a tag.
     */
    private function tag(?string $lang): string
    {
        if (null === $lang || \strlen($lang) > self::LANGUAGE_LENGTH || 1 !== preg_match('/^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/iD', $lang)) {
            return 'und';
        }

        return strtolower($lang);
    }
}
