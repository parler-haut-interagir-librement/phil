<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The addresses of the imported files on the content origin: its base URL, then the course, then the file's path, each
 * segment percent-encoded. The content origin is not the application's, so that the scripts of a package never run
 * with the application's cookies.
 */
final readonly class ContentUrls
{
    private ?string $base;

    /**
     * @param ?string $base           the base URL of the content origin; null when the application serves no content
     * @param bool    $allowLocalhost whether the base may be on `localhost`, in development
     *
     * @throws InvalidCourse when the base is not an absolute HTTP address
     */
    public function __construct(?string $base, bool $allowLocalhost = false)
    {
        if (null === $base) {
            $this->base = null;

            return;
        }

        try {
            $this->base = rtrim(Url::fromString($base, $allowLocalhost)->toString(), '/');
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidCourse::because(\sprintf('The base URL of the content origin is not an address: %s', $invalidUrl->getMessage()));
        }
    }

    public function isConfigured(): bool
    {
        return null !== $this->base;
    }

    /**
     * The origin of the content, its scheme, host and port; null when none is configured.
     */
    public function origin(): ?string
    {
        if (null === $this->base) {
            return null;
        }

        return 1 === preg_match('#^https?://[^/]+#', $this->base, $match) ? $match[0] : null;
    }

    /**
     * The address of a file of the frame of the SCORM run time, served under the base URL of the content origin.
     *
     * @throws ActivityUnavailable when no content origin is configured
     */
    public function runtime(string $file): string
    {
        if (null === $this->base) {
            throw ActivityUnavailable::because('The SCORM run time is served from the content origin, which "phil_courses.content.base_url" does not name.');
        }

        return $this->base.'/_phil/scorm/'.$file;
    }

    /**
     * @param string  $path  the file's path inside the course's directory
     * @param ?string $query what follows the path: the parameters of a SCORM item, with their `?` or `#`
     *
     * @throws ActivityUnavailable when no content origin is configured
     */
    public function url(CourseId $course, string $path, ?string $query = null): string
    {
        if (null === $this->base) {
            throw ActivityUnavailable::because('The content of imported courses is served from the content origin, which "phil_courses.content.base_url" does not name.');
        }

        $segments = array_map(rawurlencode(...), explode('/', $course->value.'/'.$path));

        return $this->base.'/'.implode('/', $segments).$this->query($query);
    }

    private function query(?string $query): string
    {
        if (null === $query || '' === $query) {
            return '';
        }

        return str_starts_with($query, '?') || str_starts_with($query, '#') ? $query : '?'.$query;
    }
}
