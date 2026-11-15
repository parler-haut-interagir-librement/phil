<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;

/**
 * The context of a launch — a course, a section, a group — on the platform.
 */
final readonly class Context
{
    public const string COURSE_OFFERING = 'http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering';

    public const string COURSE_SECTION = 'http://purl.imsglobal.org/vocab/lis/v2/course#CourseSection';

    public const string COURSE_TEMPLATE = 'http://purl.imsglobal.org/vocab/lis/v2/course#CourseTemplate';

    public const string GROUP = 'http://purl.imsglobal.org/vocab/lis/v2/course#Group';

    /**
     * @param list<string> $types
     */
    public function __construct(
        public string $id,
        public ?string $label = null,
        public ?string $title = null,
        public array $types = [],
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self($claim->string('id', 255), $claim->optionalString('label'), $claim->optionalString('title'), $claim->stringList('type', 16));
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toClaim(): array
    {
        return array_filter(['id' => $this->id, 'label' => $this->label, 'title' => $this->title, 'type' => $this->types], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
