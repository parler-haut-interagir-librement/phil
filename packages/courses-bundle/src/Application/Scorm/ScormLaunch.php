<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\ElearningPackages\Runtime\ScormVersion;

/**
 * What the page of a SCORM session hands the browser: the attempt, its number and its token, the version, the
 * tracking data the session begins with, and the data model the browser checks the content's calls against.
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
    ) {
    }

    /**
     * The state the frame of the content receives, as JSON.
     *
     * @param string $sco the path of the launch file on the content origin, with its query
     *
     * @return array{version: string, api: string, sco: string, values: array<string, string>, model: array<string, mixed>}
     */
    public function state(string $sco): array
    {
        return [
            'version' => $this->version->value,
            'api' => $this->version->apiName(),
            'sco' => $sco,
            'values' => $this->values,
            'model' => $this->model,
        ];
    }
}
