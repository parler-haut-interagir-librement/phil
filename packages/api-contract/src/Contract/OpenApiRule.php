<?php

declare(strict_types=1);

namespace Phil\ApiContract\Contract;

use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;

/**
 * One rule of the verification of an OpenAPI document.
 *
 * A rule reads the document and yields a finding for each place that breaks it; it keeps no state between two
 * documents. Its id is unique among the rules of a verifier: lowercase letters, digits and hyphens.
 */
interface OpenApiRule
{
    public function id(): string;

    /**
     * The lowest level whose verification runs this rule.
     */
    public function level(): RuleLevel;

    public function severity(): Severity;

    /**
     * One sentence: what the rule asks of the document.
     */
    public function description(): string;

    /**
     * @return iterable<Finding>
     */
    public function check(OpenApiDocument $document): iterable;
}
