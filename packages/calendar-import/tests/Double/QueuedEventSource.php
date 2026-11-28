<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Double;

use LogicException;
use Override;
use Phil\CalendarImport\Exception\SourceUnavailable;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\EventSource;
use Phil\CalendarImport\Source\Fetched;
use Phil\CalendarImport\Source\FetchValidators;

/**
 * A source that gives what it was given, one answer per fetch, and remembers the validators it was asked with.
 */
final class QueuedEventSource implements EventSource
{
    /** @var list<?FetchValidators> */
    public array $asked = [];

    /**
     * @param list<Fetched|SourceUnavailable> $answers
     */
    public function __construct(
        public array $answers = [],
    ) {
    }

    #[Override]
    public function fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched
    {
        $this->asked[] = $validators;
        $answer = array_shift($this->answers) ?? throw new LogicException('No answer left.');

        if ($answer instanceof SourceUnavailable) {
            throw $answer;
        }

        return $answer;
    }
}
