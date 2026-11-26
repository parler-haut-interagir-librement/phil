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
 * descriptions by language, the file or address that launches it, and for a question its interaction type, its
 * correct responses and its lists of components, each list checked against the interaction type and each correct
 * response against the components it names. A launch
 * file is resolved and checked to stay inside the package; an absolute address is read by `phil/url` and kept in its
 * canonical form. What can be set aside — a missing name, an IRI used twice, an unknown interaction type, a component
 * without an identifier — is noted as an `XapiWarning`; a package with no launchable activity, an activity without an IRI, or a launch that leaves the
 * package is refused. Bounded as the other manifests are, in size, XML nodes and activities.
 */
final readonly class XapiCourseReader
{
    public const string FILE = 'tincan.xml';

    public const int MAX_LANGUAGES = 32;

    public const int MAX_COMPONENTS = 100;

    public const int MAX_RESPONSES = 32;

    private const int COMPONENT_LENGTH = 255;

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

        $descriptions = $this->texts($element, 'description', $id, $warnings);
        $launch = $this->launch($element, $id);
        $interaction = $this->interactionType($element, $id, $type, $warnings);
        $responses = $this->correctResponses($element, $id, $interaction, $warnings);
        $components = $this->components($element, $id, $interaction, $warnings);
        $this->checkResponses($responses, $components, $id, $interaction, $warnings);

        return new XapiActivity($id, $type, $names, $descriptions, $launch, $interaction, $responses, $components);
    }

    /**
     * The correct response patterns of a question as written, the first `MAX_RESPONSES` kept.
     *
     * @param list<XapiWarning> $warnings
     *
     * @return list<string>
     */
    private function correctResponses(DOMElement $element, string $id, ?InteractionType $interaction, array &$warnings): array
    {
        $list = XmlDocument::child($element, 'correctResponsePatterns');

        if (!$list instanceof DOMElement) {
            return [];
        }

        if (!$interaction instanceof InteractionType) {
            $warnings[] = new XapiWarning(XapiWarningKind::MisplacedResponses, $id, \sprintf('The activity "%s" declares correct responses without an interaction type: they are left out.', $id));

            return [];
        }

        $patterns = array_map(XmlDocument::text(...), XmlDocument::children($list, 'correctResponsePattern'));

        if (\count($patterns) > self::MAX_RESPONSES) {
            $warnings[] = new XapiWarning(XapiWarningKind::TooManyResponses, $id, \sprintf('The activity "%s" declares more than %d correct responses: the others are left out.', $id, self::MAX_RESPONSES));
            $patterns = \array_slice($patterns, 0, self::MAX_RESPONSES);
        }

        return $patterns;
    }

    /**
     * The lists of components the interaction type uses, by their `ComponentList` value; a list it does not use is
     * left out.
     *
     * @param list<XapiWarning> $warnings
     *
     * @return array<string, list<InteractionComponent>>
     */
    private function components(DOMElement $element, string $id, ?InteractionType $interaction, array &$warnings): array
    {
        $used = ComponentList::of($interaction);
        $components = [];

        foreach (ComponentList::cases() as $list) {
            $node = XmlDocument::child($element, $list->value);

            if (!$node instanceof DOMElement) {
                continue;
            }

            if (!\in_array($list, $used, true)) {
                $warnings[] = new XapiWarning(XapiWarningKind::MisplacedComponents, $id, \sprintf('The activity "%s" declares %s its interaction type does not use: they are left out.', $id, $list->value));

                continue;
            }

            $components[$list->value] = $this->componentList($node, $list, $id, $warnings);
        }

        return $components;
    }

    /**
     * @param list<XapiWarning> $warnings
     *
     * @return list<InteractionComponent>
     */
    private function componentList(DOMElement $node, ComponentList $list, string $id, array &$warnings): array
    {
        $components = [];

        foreach (XmlDocument::children($node, 'component') as $child) {
            $componentId = XmlDocument::childText($child, 'id') ?? XmlDocument::attribute($child, 'id') ?? '';

            if ('' === $componentId || \strlen($componentId) > self::COMPONENT_LENGTH || 1 === preg_match('/\s/u', $componentId)) {
                $warnings[] = new XapiWarning(XapiWarningKind::InvalidComponent, $id, \sprintf('A component of the %s of the activity "%s" has no identifier of 1 to %d bytes without white space: it is left out.', $list->value, $id, self::COMPONENT_LENGTH));

                continue;
            }

            if (isset($components[$componentId])) {
                $warnings[] = new XapiWarning(XapiWarningKind::DuplicateComponent, $id, \sprintf('The component "%s" of the %s of the activity "%s" is declared twice: the second is left out.', $componentId, $list->value, $id));

                continue;
            }

            if (\count($components) >= self::MAX_COMPONENTS) {
                $warnings[] = new XapiWarning(XapiWarningKind::TooManyComponents, $id, \sprintf('The %s of the activity "%s" hold more than %d components: the others are left out.', $list->value, $id, self::MAX_COMPONENTS));

                break;
            }

            $components[$componentId] = new InteractionComponent($componentId, $this->texts($child, 'description', $id, $warnings));
        }

        return array_values($components);
    }

    /**
     * Notes each correct response that names a component its question does not declare, for each list the question
     * declares: the choices of a `choice` or `sequencing` response, the point of a `likert` one, the source and target
     * of each pair of a `matching` one, the step of each answer of a `performance` one. The delimiters
     * `{case_matters=…}`, `{order_matters=…}` and `{lang=…}` at the start of a response are not names.
     *
     * @param list<string>                              $responses
     * @param array<string, list<InteractionComponent>> $components
     * @param list<XapiWarning>                         $warnings
     */
    private function checkResponses(array $responses, array $components, string $id, ?InteractionType $interaction, array &$warnings): void
    {
        $known = array_map(static fn (array $list): array => array_map(static fn (InteractionComponent $component): string => $component->id, $list), $components);

        foreach ($responses as $response) {
            $body = (string) preg_replace('/^(?:\{[a-z_]+=[^}]*\})+/', '', $response);

            foreach ($this->namesOf($body, $interaction) as [$list, $name]) {
                if ('' !== $name && [] !== ($known[$list->value] ?? []) && !\in_array($name, $known[$list->value], true)) {
                    $warnings[] = new XapiWarning(XapiWarningKind::UnknownResponseComponent, $id, \sprintf('A correct response of the activity "%s" names "%s", which its %s do not declare: the response is kept.', $id, mb_substr($name, 0, 40), $list->value));

                    break;
                }
            }
        }
    }

    /**
     * @return list<array{ComponentList, string}> the components a response names, with the list each belongs to
     */
    private function namesOf(string $body, ?InteractionType $interaction): array
    {
        $names = [];

        foreach (explode('[,]', $body) as $part) {
            $pair = explode('[.]', $part, 2);

            array_push($names, ...match ($interaction) {
                InteractionType::Choice, InteractionType::Sequencing => [[ComponentList::Choices, $part]],
                InteractionType::Likert => [[ComponentList::Scale, $part]],
                InteractionType::Matching => [[ComponentList::Source, $pair[0]], [ComponentList::Target, $pair[1] ?? '']],
                InteractionType::Performance => [[ComponentList::Steps, $pair[0]]],
                default => [],
            });
        }

        return $names;
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
