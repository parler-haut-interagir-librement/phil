<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;

/**
 * A rule a PDF breaks: the standard (`ISO_19005_2`), its clause and test, what the rule says and where the PDF breaks
 * it.
 */
final readonly class Violation
{
    public function __construct(
        public string $specification,
        public string $clause,
        public int $testNumber,
        public string $description,
        public string $context,
        public Severity $severity,
    ) {
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        $severity = $json->string('severity');

        return new self(
            $json->string('specification'),
            $json->string('clause'),
            $json->int('testNumber'),
            $json->string('description'),
            $json->optionalString('context') ?? '',
            Severity::tryFrom($severity) ?? throw UnexpectedResponse::because(\sprintf('Gotenberg reported a violation of an unknown severity "%s".', $severity)),
        );
    }
}
