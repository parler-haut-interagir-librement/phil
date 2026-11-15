<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Contract;

/**
 * Whether a member completed a course of another system — a course catalogue, a learning platform, the application's
 * own lessons —, asked by the key a certificate course names as its completion source and by the member's stable
 * identifier. The answer is the identifier of that completion in the other system, kept on the certificate as its
 * evidence; the other system keeps its own model of courses and learners.
 *
 * An implementation answers from its own storage, at once, and keeps nothing between requests.
 */
interface CourseCompletions
{
    /**
     * @param string $course the completion source a certificate course names: the key of the course in the other system
     * @param string $holder the stable identifier of the member, never an e-mail address
     *
     * @return string|null opaque non-empty identifier of the completion, or null when not completed
     */
    public function completion(string $course, string $holder): ?string;
}
