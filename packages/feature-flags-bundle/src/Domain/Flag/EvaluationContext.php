<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a flag is evaluated against: the tenant, the project and the subject of the current request, each
 * optional and each an opaque identifier.
 *
 * Identifiers only, never a name or an address: the context reaches every provider. **Do not log the context
 * with an evaluation**: `subject` identifies a person, and a log of which person saw which feature is a record
 * about that person. A missing attribute is `null`, never an empty string, so that a rule which reads it can
 * tell "absent" from "the tenant whose identifier is empty".
 */
final readonly class EvaluationContext
{
    private function __construct(
        public ?string $tenant,
        public ?string $project,
        public ?string $subject,
    ) {
    }

    public static function of(?string $tenant = null, ?string $project = null, ?string $subject = null): self
    {
        foreach (['tenant' => $tenant, 'project' => $project, 'subject' => $subject] as $attribute => $value) {
            if ('' === $value) {
                throw InvalidValue::because(\sprintf('The %s of an evaluation context is null when absent, never an empty string.', $attribute));
            }
        }

        return new self($tenant, $project, $subject);
    }

    public static function anonymous(): self
    {
        return new self(null, null, null);
    }

    public function get(TargetingAttribute $attribute): ?string
    {
        return match ($attribute) {
            TargetingAttribute::Tenant => $this->tenant,
            TargetingAttribute::Project => $this->project,
            TargetingAttribute::Subject => $this->subject,
        };
    }
}
