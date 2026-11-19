<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\ElearningPackages\Runtime\ScormVersion;

/**
 * What the page of a SCORM session hands the browser: the attempt, its number and its token, the version, the
 * tracking data the session begins with, and the data model the browser checks the content's calls against; whether
 * the attempt is open for review, its token empty, so that nothing is sent; and the seconds left before the deadline of
 * a time limit.
 */
final readonly class ScormLaunch
{
    /**
     * @param array<string, string> $values
     * @param array<string, mixed>  $model
     */
    public function __construct(
        public ScormAttemptId $attempt,
        public int $number,
        public string $token,
        public ScormVersion $version,
        public array $values,
        public array $model,
        public bool $resumed = false,
        public bool $review = false,
        public ?int $remaining = null,
    ) {
    }

    /**
     * The state the frame of the content receives, as JSON.
     *
     * @param string $sco the path of the launch file on the content origin, with its query
     *
     * @return array{version: string, api: string, sco: string, values: array<string, string>, model: array<string, mixed>, review: bool, remaining: ?int}
     */
    public function state(string $sco): array
    {
        return [
            'version' => $this->version->value,
            'api' => $this->version->apiName(),
            'sco' => $sco,
            'values' => $this->values,
            'model' => $this->model,
            'review' => $this->review,
            'remaining' => $this->remaining,
        ];
    }
}
