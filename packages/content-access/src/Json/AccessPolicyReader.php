<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Json;

use DateTimeImmutable;
use JsonException;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Audience;
use Phil\ContentAccess\Exception\InvalidPolicy;
use Phil\ContentAccess\WhenDenied;
use Phil\Rules\Exception\RulesException;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Rule;

/**
 * Reads the versioned JSON form of an access policy, strictly:
 *
 *     {"version": 1, "audience": "restricted", "rule": {"version": 1, "condition": …},
 *      "public_from": "2026-12-01T00:00:00+00:00", "preview": true, "when_denied": "locked"}
 *
 * `version` and `audience` are required; `rule` is given for a restricted audience only; `public_from` is an RFC 3339
 * instant with a numeric offset; `preview` is a boolean, false by default; `when_denied` is `locked`, by default, or
 * `hidden`. Any other member, a wrong type or a contradiction is refused, and the rule is read by the reader of
 * `phil/rules`, within its limits.
 */
final readonly class AccessPolicyReader
{
    public const int VERSION = 1;

    public const string INSTANT_FORMAT = 'Y-m-d\TH:i:sP';

    private const array MEMBERS = ['version', 'audience', 'rule', 'public_from', 'preview', 'when_denied'];

    public function __construct(
        private RuleReader $rules,
        private int $maxBytes = 65536,
    ) {
    }

    /**
     * @throws InvalidPolicy when the JSON is malformed, too large or the policy is refused
     */
    public function fromJson(string $json): AccessPolicy
    {
        if (\strlen($json) > $this->maxBytes) {
            throw InvalidPolicy::tooLarge(\strlen($json), $this->maxBytes);
        }

        try {
            $decoded = json_decode($json, true, 128, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidPolicy::notJson($jsonException->getMessage());
        }

        if (!\is_array($decoded)) {
            throw InvalidPolicy::malformed('', 'a policy is an object');
        }

        return $this->fromArray($decoded);
    }

    /**
     * The same form, already decoded, such as the body of an API request.
     *
     * @param array<array-key, mixed> $policy
     *
     * @throws InvalidPolicy when the policy is refused
     */
    public function fromArray(array $policy): AccessPolicy
    {
        if ([] === $policy || array_is_list($policy)) {
            throw InvalidPolicy::malformed('', 'a policy is an object');
        }

        foreach (array_keys($policy) as $member) {
            if (!\in_array($member, self::MEMBERS, true)) {
                throw InvalidPolicy::malformed('/'.$this->shown((string) $member), 'is not a member of a policy');
            }
        }

        if (self::VERSION !== ($policy['version'] ?? null)) {
            throw InvalidPolicy::unsupportedVersion($policy['version'] ?? null);
        }

        $audience = \is_string($policy['audience'] ?? null) ? Audience::tryFrom($policy['audience']) : null;

        if (!$audience instanceof Audience) {
            throw InvalidPolicy::malformed('/audience', 'is "public", "members" or "restricted"');
        }

        $result = match ($audience) {
            Audience::Public => AccessPolicy::public(),
            Audience::Members => AccessPolicy::members(),
            Audience::Restricted => AccessPolicy::restricted($this->rule($policy['rule'] ?? null)),
        };

        if (Audience::Restricted !== $audience && \array_key_exists('rule', $policy)) {
            throw InvalidPolicy::contradiction('only a restricted content has a rule');
        }

        if (\array_key_exists('public_from', $policy)) {
            $result = $result->withPublicFrom($this->instant($policy['public_from']));
        }

        if (\array_key_exists('preview', $policy)) {
            if (!\is_bool($policy['preview'])) {
                throw InvalidPolicy::malformed('/preview', 'is true or false');
            }

            $result = $result->withPreview($policy['preview']);
        }

        if (\array_key_exists('when_denied', $policy)) {
            $whenDenied = \is_string($policy['when_denied']) ? WhenDenied::tryFrom($policy['when_denied']) : null;

            if (!$whenDenied instanceof WhenDenied) {
                throw InvalidPolicy::malformed('/when_denied', 'is "locked" or "hidden"');
            }

            $result = $result->withWhenDenied($whenDenied);
        }

        return $result;
    }

    private function rule(mixed $rule): Rule
    {
        if (!\is_array($rule)) {
            throw InvalidPolicy::malformed('/rule', 'a restricted policy holds its rule as an object');
        }

        try {
            return $this->rules->fromArray($rule);
        } catch (RulesException $rulesException) {
            throw InvalidPolicy::rule($rulesException);
        }
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        $instant = \is_string($value) ? DateTimeImmutable::createFromFormat('!'.self::INSTANT_FORMAT, $value) : false;

        if (!$instant instanceof DateTimeImmutable || $instant->format(self::INSTANT_FORMAT) !== $value) {
            throw InvalidPolicy::malformed('/public_from', 'is an RFC 3339 instant with a numeric offset, such as "2026-12-01T00:00:00+00:00"');
        }

        return $instant;
    }

    /**
     * A member name read from a policy, shortened and without control characters, to show in a message.
     */
    private function shown(string $name): string
    {
        $clean = (string) preg_replace('/[\x00-\x1F\x7F]/', '?', $name);

        return \strlen($clean) > 64 ? substr($clean, 0, 64).'…' : $clean;
    }
}
