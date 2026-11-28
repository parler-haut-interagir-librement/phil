<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Json;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentAccess\AccessPolicy;
use Phil\Rules\Json\RuleWriter;
use Phil\Rules\Rule;

/**
 * Writes the versioned JSON form of an access policy, canonically: the members in a fixed order, the rule in the
 * canonical form of `phil/rules`, the instant in UTC, `preview` and `when_denied` always written. A canonical form reads
 * back to itself, so that a stored policy has a fingerprint.
 */
final readonly class AccessPolicyWriter
{
    private const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(private RuleWriter $rules = new RuleWriter())
    {
    }

    /**
     * @return array{version: int, audience: string, rule?: array<string, mixed>, public_from?: string, preview: bool, when_denied: string}
     */
    public function toArray(AccessPolicy $policy): array
    {
        $array = ['version' => AccessPolicyReader::VERSION, 'audience' => $policy->audience->value];

        if ($policy->rule instanceof Rule) {
            $array['rule'] = $this->rules->toArray($policy->rule);
        }

        if ($policy->publicFrom instanceof DateTimeImmutable) {
            $array['public_from'] = $this->instant($policy->publicFrom);
        }

        $array['preview'] = $policy->preview;
        $array['when_denied'] = $policy->whenDenied->value;

        return $array;
    }

    public function toJson(AccessPolicy $policy): string
    {
        $object = $this->toArray($policy);

        if ($policy->rule instanceof Rule) {
            // The rule's own canonical JSON keeps its empty operands as objects, which the array form cannot tell apart.
            $object['rule'] = json_decode($this->rules->toJson($policy->rule), false, 512, \JSON_THROW_ON_ERROR);
        }

        return json_encode($object, self::FLAGS);
    }

    /**
     * The SHA-256 of the canonical form, in hexadecimal: two policies that mean the same have the same fingerprint.
     */
    public function fingerprint(AccessPolicy $policy): string
    {
        return hash('sha256', $this->toJson($policy));
    }

    private function instant(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format(AccessPolicyReader::INSTANT_FORMAT);
    }
}
