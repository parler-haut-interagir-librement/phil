<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

/**
 * One operation of a document: its path template, its method in lowercase, its JSON Pointer, the operation object
 * and the path item that holds it.
 */
final readonly class Operation
{
    /**
     * @param array<array-key, mixed> $operation
     * @param array<array-key, mixed> $pathItem
     */
    public function __construct(
        public string $path,
        public string $method,
        public string $pointer,
        public array $operation,
        public array $pathItem,
    ) {
    }

    /**
     * The operation's parameters after the path item's, each local reference followed, keyed by the JSON Pointer
     * where it is declared; a parameter of the operation replaces the path item's of the same name and location, as
     * OpenAPI says.
     *
     * @return array<string, array{name: string, in: string, ...}>
     */
    public function parameters(OpenApiDocument $document): array
    {
        $parameters = [];

        foreach ([[$this->pathItem, OpenApiDocument::pointer('paths', $this->path)], [$this->operation, $this->pointer]] as [$holder, $pointer]) {
            $list = $holder['parameters'] ?? null;

            if (!\is_array($list)) {
                continue;
            }

            foreach ($list as $index => $parameter) {
                $parameter = $document->resolve($parameter);

                if (!\is_array($parameter) || !\is_string($parameter['name'] ?? null) || !\is_string($parameter['in'] ?? null)) {
                    continue;
                }

                $key = $parameter['in'].' '.$parameter['name'];
                $parameters = array_filter($parameters, static fn (array $kept): bool => $kept['in'].' '.$kept['name'] !== $key);
                $parameters[$pointer.'/parameters/'.$index] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * The operation's responses, each local reference followed, keyed by status code (`200`, `4XX`, `default`), which
     * PHP turns into an integer when it is one; a response that does not resolve to an object is left out.
     *
     * @return array<int|string, array<array-key, mixed>>
     */
    public function responses(OpenApiDocument $document): array
    {
        $responses = [];
        $list = $this->operation['responses'] ?? null;

        foreach (\is_array($list) ? $list : [] as $code => $response) {
            $response = $document->resolve($response);

            if (\is_array($response)) {
                $responses[$code] = $response;
            }
        }

        return $responses;
    }
}
