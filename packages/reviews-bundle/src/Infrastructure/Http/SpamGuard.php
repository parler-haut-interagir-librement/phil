<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Http;

use Phil\SpamProtectionBundle\Application\Protection;
use Phil\SpamProtectionBundle\Application\RefusalPolicy;
use Phil\SpamProtectionBundle\Application\Submission;
use Phil\SpamProtectionBundle\Application\SubmissionInspector;
use Phil\SpamProtectionBundle\Infrastructure\Http\ClientKey;
use Symfony\Component\HttpFoundation\Request;

/**
 * The review form held to phil/spam-protection-bundle: its honeypot, its signed single-use token, the proof of work its
 * Stimulus controller writes, the content rules on the title and the text, and its rate limit, weighed by the
 * bundle's refusal policy. A refused submission is told nothing of why.
 */
final readonly class SpamGuard
{
    public const string SCOPE = 'phil_reviews';

    public const string HONEYPOT = 'website_url';

    public const string TOKEN = '_phil_spam_token';

    public const string PROOF = '_phil_spam_proof';

    /** The Stimulus controller of the proof of work, as Symfony UX names it. */
    public const string CONTROLLER = 'phil--spam-protection-bundle--proof-of-work';

    public function __construct(
        private SubmissionInspector $inspector,
        private RefusalPolicy $refusal,
        private ?ClientKey $client = null,
    ) {
    }

    /**
     * The token, challenge and difficulty of a new form.
     */
    public function protect(): Protection
    {
        return $this->inspector->protect(self::SCOPE);
    }

    /**
     * @param list<string> $texts the texts the content rules read
     */
    public function refuses(Request $request, array $texts): bool
    {
        return $this->refusal->refuses($this->inspector->inspect(new Submission(
            self::SCOPE,
            $request->request->getString(self::HONEYPOT),
            $request->request->getString(self::TOKEN),
            $request->request->getString(self::PROOF),
            $texts,
            $this->client?->current(),
        )));
    }
}
