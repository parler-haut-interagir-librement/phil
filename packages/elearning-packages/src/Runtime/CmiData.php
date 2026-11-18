<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;

/**
 * The tracking data of one attempt at a SCO, by the rules of the data model of its version: `get()` and `set()` answer
 * as `GetValue()` and `SetValue()` do — the value, or the error code of the version —, so that the platform checks a
 * commit the browser sends exactly as the content's own calls were checked.
 *
 * - An element the data model does not define, a read of a write-only element and a write of a read-only one or of a
 *   keyword are refused; `_children`, `_count` and `_version` are answered.
 * - A collection grows one element at a time, up to its bound; an element of SCORM 2004 needs the identifier of its
 *   objective or interaction first, and a response the type of its interaction; identifiers of objectives are unique
 *   and never change.
 * - A value is checked against the pattern, length and range of its element; the data of an attempt is bounded in
 *   bytes as a whole.
 * - `cmi.completion_status` and `cmi.success_status` of SCORM 2004 are evaluated against the completion threshold and
 *   the scaled passing score when the package sets them.
 *
 * `put()` is the platform's own write, at launch: it checks the element exists, not its access.
 */
final class CmiData
{
    public const int MAX_BYTES = 1_048_576;

    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, int> the number of elements of each collection, by its name with indexes filled */
    private array $counts = [];

    private int $bytes = 0;

    /**
     * @param array<array-key, mixed> $values the values kept by an earlier commit, by element
     *
     * @throws RuntimeRefused when a value kept is not text, or names no element of the version
     */
    public function __construct(
        public readonly ScormVersion $version,
        array $values = [],
        private readonly int $maxBytes = self::MAX_BYTES,
    ) {
        foreach ($values as $element => $value) {
            if (!\is_string($element) || !\is_string($value)) {
                throw RuntimeRefused::because('The tracking data kept holds an element or a value that is not text.');
            }

            $name = ElementName::parse($element);

            if (!$name instanceof ElementName || null !== $name->keyword || !DataModel::definition($version, $name->pattern) instanceof ElementDefinition) {
                throw RuntimeRefused::because(\sprintf('The tracking data kept names "%s", which is not an element of SCORM %s.', mb_substr($element, 0, 80), $version->value));
            }

            $this->store($name, $value);
        }
    }

    /**
     * The values kept, by element, in the order they were first written.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The value kept for an element, whatever its access; null when none is.
     */
    public function value(string $element): ?string
    {
        return $this->values[$element] ?? null;
    }

    /**
     * How many elements a collection holds, by its name with its indexes filled: `cmi.interactions.0.objectives`.
     */
    public function count(string $collection): int
    {
        return $this->counts[$collection] ?? 0;
    }

    /**
     * The platform's write: a value set at launch, such as `cmi.core.student_id`, or one the platform changes at the
     * end of a session, such as `cmi.core.total_time`.
     *
     * @throws RuntimeRefused when the element is not one of the version
     */
    public function put(string $element, string $value): void
    {
        $name = ElementName::parse($element);

        if (!$name instanceof ElementName || null !== $name->keyword || !DataModel::definition($this->version, $name->pattern) instanceof ElementDefinition) {
            throw RuntimeRefused::because(\sprintf('"%s" is not an element of SCORM %s.', mb_substr($element, 0, 80), $this->version->value));
        }

        $this->store($name, $value);
    }

    /**
     * Forgets the value of an element, as the platform does with `cmi.core.exit` when a session begins.
     */
    public function forget(string $element): void
    {
        if (isset($this->values[$element])) {
            $this->bytes -= \strlen($element) + \strlen($this->values[$element]);
            unset($this->values[$element]);
        }
    }

    /**
     * Reads an element as `GetValue()` does.
     */
    public function get(string $element): RuntimeResult
    {
        if ('' === $element) {
            return $this->error(ScormVersion::Scorm12 === $this->version ? Mistake::OutOfOrder : Mistake::GetFailed, 'No element is named.');
        }

        $name = ElementName::parse($element);

        if (!$name instanceof ElementName) {
            return $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        if (null !== $name->keyword) {
            return $this->keyword($name);
        }

        $definition = DataModel::definition($this->version, $name->pattern);

        if (!$definition instanceof ElementDefinition) {
            return $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        if (!$definition->access->readable()) {
            return $this->error(Mistake::WriteOnly, 'The element is write only.');
        }

        foreach ($name->indexes as $depth => $index) {
            if ($index >= $this->count($name->collection($depth))) {
                return $this->error(Mistake::GetOutOfRange, 'The collection holds no element at this index.');
            }
        }

        $evaluated = CmiRules::evaluated($this, $name->pattern);
        $value = $evaluated ?? $this->values[$element] ?? $definition->default;

        if (null === $value) {
            return ScormVersion::Scorm12 === $this->version ? new RuntimeResult() : $this->error(Mistake::NotInitialized, 'The element holds no value yet.');
        }

        return new RuntimeResult($value);
    }

    /**
     * Writes an element as `SetValue()` does; a number is written as its text, as JavaScript would.
     */
    public function set(string $element, mixed $value): RuntimeResult
    {
        if ('' === $element) {
            return $this->error(ScormVersion::Scorm12 === $this->version ? Mistake::OutOfOrder : Mistake::SetFailed, 'No element is named.');
        }

        $name = ElementName::parse($element);

        if (!$name instanceof ElementName) {
            return $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        if (null !== $name->keyword) {
            return $this->knowsKeyword($name) ? $this->error(Mistake::Keyword, 'A keyword is read only.') : $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        $definition = DataModel::definition($this->version, $name->pattern);

        if (!$definition instanceof ElementDefinition) {
            return $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        if (!$definition->access->writable()) {
            return $this->error(Mistake::ReadOnly, 'The element is read only.');
        }

        $text = $this->text($value);

        if (null === $text) {
            return $this->error(Mistake::TypeMismatch, 'The value is not text or a number.');
        }

        $refused = $this->order($name) ?? CmiRules::dependency($this, $name) ?? $this->check($definition, $name, $text) ?? CmiRules::uniqueness($this, $name, $text);

        if (null !== $refused) {
            return $this->error(...$refused);
        }

        $growth = \strlen($text) - \strlen($this->values[$element] ?? '') + (isset($this->values[$element]) ? 0 : \strlen($element));

        if ($this->bytes + $growth > $this->maxBytes) {
            return $this->error(Mistake::SetFailed, \sprintf('The tracking data of an attempt holds %d bytes at most.', $this->maxBytes));
        }

        $this->store($name, $text);

        return new RuntimeResult();
    }

    /**
     * Where the learner stands, read in the words of SCORM 2004 whatever the version: the completion and the success,
     * evaluated as `GetValue()` would answer them, and the score scaled from -1 to 1.
     */
    public function status(): RuntimeStatus
    {
        if (ScormVersion::Scorm12 === $this->version) {
            return RuntimeStatus::fromLessonStatus($this->values['cmi.core.lesson_status'] ?? 'not attempted', $this->scaledScore());
        }

        return new RuntimeStatus(
            $this->get('cmi.completion_status')->value,
            $this->get('cmi.success_status')->value,
            $this->scaledScore(),
        );
    }

    /**
     * The score from -1 to 1: `cmi.score.scaled` when the content sets it, else the raw score within its minimum and
     * maximum — out of a hundred in SCORM 1.2 when they are not set —; null when the content sets no score.
     */
    public function scaledScore(): ?float
    {
        $prefix = ScormVersion::Scorm12 === $this->version ? 'cmi.core.score.' : 'cmi.score.';

        return Score::scaled(
            $this->values[$prefix.'scaled'] ?? null,
            $this->values[$prefix.'raw'] ?? null,
            $this->values[$prefix.'min'] ?? null,
            $this->values[$prefix.'max'] ?? null,
            ScormVersion::Scorm12 === $this->version,
        );
    }

    private function keyword(ElementName $name): RuntimeResult
    {
        if ('_version' === $name->keyword) {
            return 'cmi' === $name->pattern ? new RuntimeResult($this->version->dataModelVersion()) : $this->error(Mistake::Undefined, 'Only "cmi._version" is defined.');
        }

        foreach ($name->indexes as $depth => $index) {
            if ($index >= $this->count($name->collection($depth))) {
                return $this->error(Mistake::GetOutOfRange, 'The collection holds no element at this index.');
            }
        }

        if ('_children' === $name->keyword) {
            $children = DataModel::children($this->version, $name->pattern);

            if (null !== $children) {
                return new RuntimeResult($children);
            }

            return $this->known($name->pattern) ? $this->error(Mistake::NoChildren, 'The element has no children.') : $this->error(Mistake::Undefined, 'The data model defines no such element.');
        }

        if (isset(DataModel::collections($this->version)[$name->pattern])) {
            return new RuntimeResult((string) $this->count($this->filled($name)));
        }

        return $this->known($name->pattern) ? $this->error(Mistake::NoCount, 'The element is not a collection.') : $this->error(Mistake::Undefined, 'The data model defines no such element.');
    }

    private function knowsKeyword(ElementName $name): bool
    {
        return '_version' === $name->keyword ? 'cmi' === $name->pattern : $this->known($name->pattern);
    }

    private function known(string $pattern): bool
    {
        return DataModel::definition($this->version, $pattern) instanceof ElementDefinition || DataModel::isParent($this->version, $pattern);
    }

    /**
     * The name of a collection with its indexes, from a `_count` element: `cmi.interactions.0.objectives`.
     */
    private function filled(ElementName $name): string
    {
        $indexes = $name->indexes;

        return implode('.', array_map(static function (string $segment) use (&$indexes): string {
            return 'n' === $segment ? (string) array_shift($indexes) : $segment;
        }, explode('.', $name->pattern)));
    }

    /**
     * Refuses an index past the next free one, or past the bound of its collection.
     */
    /**
     * @return ?array{Mistake, string}
     */
    private function order(ElementName $name): ?array
    {
        foreach ($name->indexes as $depth => $index) {
            $count = $this->count($name->collection($depth));
            $pattern = $name->collectionPattern($depth);
            $bound = DataModel::collections($this->version)[$pattern] ?? 0;

            if ('cmi.interactions.n.correct_responses' === $pattern) {
                $bound = DataModel::correctResponses($this->version, $this->values[$name->sibling(0, 'type')] ?? '');
            }

            if ($index > $count) {
                return [Mistake::OutOfOrder, 'The elements of a collection are written in order of their index.'];
            }

            if ($index >= $bound) {
                return [Mistake::SetFailed, \sprintf('The collection holds %d elements at most.', $bound)];
            }
        }

        return null;
    }

    /**
     * @return ?array{Mistake, string}
     */
    private function check(ElementDefinition $definition, ElementName $name, string $text): ?array
    {
        if (1 !== preg_match('//u', $text)) {
            return [Mistake::TypeMismatch, 'The value is not UTF-8.'];
        }

        if (null !== $definition->maxChars && mb_strlen($text) > $definition->maxChars) {
            return [Mistake::TypeMismatch, \sprintf('The value holds %d characters at most.', $definition->maxChars)];
        }

        $pattern = match ($definition->check) {
            ValueCheck::Pattern => $definition->pattern,
            ValueCheck::InteractionPattern => DataModel::responsePattern($this->values[$name->sibling(0, 'type')] ?? '', true),
            ValueCheck::InteractionResponse => DataModel::responsePattern($this->values[$name->sibling(0, 'type')] ?? '', false),
        };

        if (null !== $pattern && 1 !== preg_match('/'.str_replace('/', '\/', $pattern).'/u', $text)) {
            return [Mistake::TypeMismatch, 'The value is not of the type of the element.'];
        }

        if ('' !== $text && (null !== $definition->min || null !== $definition->max)) {
            $number = (float) $text;

            if (null !== $definition->min && $number < $definition->min || null !== $definition->max && $number > $definition->max) {
                return [Mistake::OutOfRange, 'The value is out of the range of the element.'];
            }
        }

        return null;
    }

    private function store(ElementName $name, string $value): void
    {
        $this->bytes += \strlen($value) - \strlen($this->values[$name->name] ?? '') + (isset($this->values[$name->name]) ? 0 : \strlen($name->name));
        $this->values[$name->name] = $value;

        foreach ($name->indexes as $depth => $index) {
            $collection = $name->collection($depth);
            $this->counts[$collection] = max($this->counts[$collection] ?? 0, $index + 1);
        }
    }

    private function error(Mistake $mistake, string $diagnostic): RuntimeResult
    {
        return new RuntimeResult('', ScormError::for($this->version, $mistake), $diagnostic);
    }

    private function text(mixed $value): ?string
    {
        return match (true) {
            \is_string($value) => $value,
            \is_int($value) => (string) $value,
            \is_float($value) && is_finite($value) => (string) $value,
            default => null,
        };
    }
}
