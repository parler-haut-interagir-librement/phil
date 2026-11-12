<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\OpenApi;

use Phil\ApiContract\Exception\UnreadableDocument;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads an OpenAPI document from a file: JSON, or YAML when its name ends in `.yaml` or `.yml` and Symfony's Yaml
 * component is installed. The file is refused before it is read when it is larger than the library's bound.
 */
final class DocumentReader
{
    public static function read(string $file): OpenApiDocument
    {
        $size = is_file($file) && is_readable($file) ? filesize($file) : false;

        if (false === $size) {
            throw UnreadableDocument::because(\sprintf('The file "%s" cannot be read.', $file));
        }

        if ($size > OpenApiDocument::MAX_BYTES) {
            throw UnreadableDocument::because(\sprintf('The document is larger than %d bytes.', OpenApiDocument::MAX_BYTES));
        }

        $text = (string) file_get_contents($file);

        if (1 !== preg_match('/\.ya?ml$/iD', $file)) {
            return OpenApiDocument::fromJson($text);
        }

        if (!class_exists(Yaml::class)) {
            throw UnreadableDocument::because('A YAML document needs symfony/yaml: composer require symfony/yaml.');
        }

        try {
            $data = Yaml::parse($text);
        } catch (ParseException $parseException) {
            throw UnreadableDocument::because('The document is not YAML: '.$parseException->getMessage());
        }

        if (!\is_array($data)) {
            throw UnreadableDocument::because('The document is not a YAML mapping.');
        }

        return OpenApiDocument::fromArray($data);
    }
}
