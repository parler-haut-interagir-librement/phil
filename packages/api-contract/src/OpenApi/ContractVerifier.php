<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\Exception\InvalidContractValue;
use Phil\ApiContract\OpenApi\Rule\DeprecationSunset;
use Phil\ApiContract\OpenApi\Rule\ErrorResponses;
use Phil\ApiContract\OpenApi\Rule\ExtensibleEnum;
use Phil\ApiContract\OpenApi\Rule\Info;
use Phil\ApiContract\OpenApi\Rule\JsonContent;
use Phil\ApiContract\OpenApi\Rule\LocalReferences;
use Phil\ApiContract\OpenApi\Rule\NoVersionInPath;
use Phil\ApiContract\OpenApi\Rule\OpenApiVersion;
use Phil\ApiContract\OpenApi\Rule\OperationDescription;
use Phil\ApiContract\OpenApi\Rule\OperationId;
use Phil\ApiContract\OpenApi\Rule\OperationIdUnique;
use Phil\ApiContract\OpenApi\Rule\OperationResponses;
use Phil\ApiContract\OpenApi\Rule\PathParameters;
use Phil\ApiContract\OpenApi\Rule\Paths;
use Phil\ApiContract\OpenApi\Rule\ProblemDetails;
use Phil\ApiContract\OpenApi\Rule\SecurityRequirement;
use Phil\ApiContract\OpenApi\Rule\SuccessResponse;

/**
 * Verifies an OpenAPI document against the rules of a level: those of the level itself and of the levels before it.
 *
 * The verifier holds its rules and nothing else: one instance verifies any number of documents, in any order, from
 * any request.
 */
final readonly class ContractVerifier
{
    /**
     * @var list<OpenApiRule>
     */
    public array $rules;

    /**
     * @param iterable<OpenApiRule> $rules each with an id of lowercase letters, digits and hyphens, unique
     */
    public function __construct(iterable $rules)
    {
        $kept = [];

        foreach ($rules as $rule) {
            $id = $rule->id();

            if (1 !== preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id)) {
                throw InvalidContractValue::because(\sprintf('A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter; %s is not.', $this->quoted($id)));
            }

            if (isset($kept[$id])) {
                throw InvalidContractValue::because(\sprintf('Two rules have the id "%s".', $id));
            }

            $kept[$id] = $rule;
        }

        $this->rules = array_values($kept);
    }

    /**
     * The rules this package ships, by level then in the order the documentation lists them, followed by `$more`.
     */
    public static function withBuiltInRules(OpenApiRule ...$more): self
    {
        return new self([...self::builtInRules(), ...$more]);
    }

    /**
     * @return list<OpenApiRule>
     */
    public static function builtInRules(): array
    {
        return [
            new OpenApiVersion(),
            new Info(),
            new Paths(),
            new OperationResponses(),
            new PathParameters(),
            new OperationIdUnique(),
            new LocalReferences(),
            new NoVersionInPath(),
            new SuccessResponse(),
            new JsonContent(),
            new ProblemDetails(),
            new DeprecationSunset(),
            new OperationId(),
            new ExtensibleEnum(),
            new ErrorResponses(),
            new OperationDescription(),
            new SecurityRequirement(),
        ];
    }

    /**
     * The same verifier without the rules of these ids; an id it does not hold is refused, so that a typo does not
     * keep a rule silently.
     */
    public function without(string ...$ids): self
    {
        $known = array_map(static fn (OpenApiRule $rule): string => $rule->id(), $this->rules);
        $unknown = array_diff($ids, $known);

        if ([] !== $unknown) {
            throw InvalidContractValue::because(\sprintf('No rule has the id %s.', $this->quoted(reset($unknown))));
        }

        return new self(array_filter($this->rules, static fn (OpenApiRule $rule): bool => !\in_array($rule->id(), $ids, true)));
    }

    /**
     * The rules a verification at this level runs, in order.
     *
     * @return list<OpenApiRule>
     */
    public function rulesAt(RuleLevel $level): array
    {
        return array_values(array_filter($this->rules, static fn (OpenApiRule $rule): bool => $level->includes($rule->level())));
    }

    /**
     * The first 64 bytes of an id, as a JSON string: quoted, escaped, valid UTF-8.
     */
    private function quoted(string $id): string
    {
        return json_encode(substr($id, 0, 64), \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    public function verify(OpenApiDocument $document, RuleLevel $level = RuleLevel::Recommended): Report
    {
        $findings = [];

        foreach ($this->rulesAt($level) as $rule) {
            foreach ($rule->check($document) as $finding) {
                $findings[] = $finding;
            }
        }

        return new Report($level, $findings);
    }
}
