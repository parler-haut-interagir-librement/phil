<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Storage;

use BackedEnum;
use JsonException;
use Phil\PagedMedia\Gotenberg\Download;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Job\StoredStage;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use SodiumException;
use TypeError;

/**
 * Writes a job as JSON and reads it back, for the job stores: a file read from a path keeps its path, a file held in
 * memory its bytes in base64; each stage keeps its operation, its options and the files they name, and comes back as a
 * `StoredStage`; the LibreOffice options keep the ones set. Nothing is unserialized: a JSON the codec did not write is
 * refused with `JobUnavailable`.
 */
final readonly class JobCodec
{
    private const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION;

    public function encode(RenderJob|PipelineJob|ConversionJob $job): string
    {
        return json_encode(match (true) {
            $job instanceof RenderJob => ['type' => 'render', 'template' => $job->template, 'parameters' => $job->parameters, 'filename' => $job->filename, 'pipeline' => $this->stages($job->pipeline)],
            $job instanceof PipelineJob => ['type' => 'pipeline', 'files' => $this->files($job->files), 'pipeline' => $this->stages($job->pipeline)],
            $job instanceof ConversionJob => ['type' => 'conversion', 'files' => $this->files($job->documents), 'options' => $this->options($job->options), 'pipeline' => $this->stages($job->pipeline)],
        } + ['path' => $job->path, 'sign' => $job->sign, 'timestamp' => $job->timestamp], self::FLAGS);
    }

    public function decode(string $json): RenderJob|PipelineJob|ConversionJob
    {
        try {
            $data = json_decode($json, true, flags: self::FLAGS);
        } catch (JsonException $jsonException) {
            throw JobUnavailable::because('A kept job is not JSON: '.$jsonException->getMessage());
        }

        $data = self::object($data, 'job');
        $path = self::string($data, 'path');
        $sign = $this->bool($data, 'sign');
        $timestamp = $this->bool($data, 'timestamp');
        $pipeline = null === ($data['pipeline'] ?? null) ? null : $this->pipeline(self::list($data, 'pipeline'));

        return match ($data['type'] ?? null) {
            'render' => new RenderJob(self::string($data, 'template'), $this->parameters(self::object($data['parameters'] ?? null, 'parameters')), $path, $pipeline, $sign, $timestamp, self::string($data, 'filename')),
            'pipeline' => new PipelineJob($this->readFiles(self::object($data['files'] ?? null, 'files')), $pipeline ?? throw self::malformed('pipeline'), $path, $sign, $timestamp),
            'conversion' => new ConversionJob($this->readFiles(self::object($data['files'] ?? null, 'files')), $path, $this->readOptions(self::object($data['options'] ?? null, 'options')), $pipeline, $sign, $timestamp),
            default => throw self::malformed('type'),
        };
    }

    /**
     * @return list<array{op: string, options: array<string, scalar|array<array-key, mixed>>, files: list<array{name: string, path?: string, contents?: string}>}>|null
     */
    private function stages(?Pipeline $pipeline): ?array
    {
        return $pipeline instanceof Pipeline ? array_map(static fn (Stage $stage): array => ['op' => $stage->operation()->value, 'options' => $stage->options(), 'files' => array_map(self::file(...), $stage->files())], $pipeline->stages) : null;
    }

    /**
     * @return array{files: list<array{name: string, path?: string, contents?: string}>, archives: list<array{name: string, path?: string, contents?: string}>, downloads: list<array{url: string, extraHttpHeaders?: array<string, string>}>}
     */
    private function files(Files $files): array
    {
        return [
            'files' => array_map(self::file(...), $files->files),
            'archives' => array_map(self::file(...), $files->archives),
            'downloads' => array_map(static fn (Download $download): array => $download->toArray(), $files->downloads),
        ];
    }

    /**
     * @return array{name: string, path?: string, contents?: string}
     */
    private static function file(InputFile $file): array
    {
        $path = $file->path();

        if (null !== $path) {
            return ['name' => $file->name, 'path' => $path];
        }

        $contents = '';

        foreach ($file->chunks() as $chunk) {
            $contents .= $chunk;
        }

        return ['name' => $file->name, 'contents' => sodium_bin2base64($contents, \SODIUM_BASE64_VARIANT_ORIGINAL)];
    }

    /**
     * The options set, each enumeration by its value.
     *
     * @return array<string, scalar>
     */
    private function options(LibreOfficeOptions $options): array
    {
        $set = [];

        foreach (new ReflectionObject($options)->getProperties() as $property) {
            $value = $property->getValue($options);

            if (\is_scalar($value) || $value instanceof BackedEnum) {
                $set[$property->getName()] = $value instanceof BackedEnum ? $value->value : $value;
            }
        }

        return $set;
    }

    /**
     * @param list<mixed> $stages
     */
    private function pipeline(array $stages): Pipeline
    {
        return new Pipeline(...array_map(static function (mixed $stage): StoredStage {
            $stage = self::object($stage, 'pipeline');
            $operation = Operation::tryFrom(self::string($stage, 'op')) ?? throw self::malformed('op');
            $options = [];

            foreach (self::object($stage['options'] ?? null, 'options') as $name => $value) {
                $options[\is_string($name) && (\is_scalar($value) || \is_array($value)) ? $name : throw self::malformed('options')] = $value;
            }

            return new StoredStage($operation, $options, array_map(self::readFile(...), self::list($stage, 'files')));
        }, $stages));
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function parameters(array $parameters): array
    {
        $named = [];

        foreach ($parameters as $name => $value) {
            $named[\is_string($name) ? $name : throw self::malformed('parameters')] = $value;
        }

        return $named;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function readFiles(array $data): Files
    {
        $files = Files::of(...array_map(self::readFile(...), self::list($data, 'files')));

        foreach (self::list($data, 'archives') as $archive) {
            $files = $files->withArchive(self::readFile($archive));
        }

        foreach (self::list($data, 'downloads') as $download) {
            $download = self::object($download, 'downloads');
            $headers = [];

            foreach (self::object($download['extraHttpHeaders'] ?? [], 'extraHttpHeaders') as $name => $value) {
                $headers[\is_string($name) && \is_string($value) ? $name : throw self::malformed('extraHttpHeaders')] = $value;
            }

            $files = $files->withDownload(new Download(self::string($download, 'url'), $headers));
        }

        return $files;
    }

    private static function readFile(mixed $file): InputFile
    {
        $file = self::object($file, 'file');
        $name = self::string($file, 'name');

        if (\array_key_exists('path', $file)) {
            return InputFile::fromPath(self::string($file, 'path'), $name);
        }

        try {
            return InputFile::fromContents($name, sodium_base642bin(self::string($file, 'contents'), \SODIUM_BASE64_VARIANT_ORIGINAL));
        } catch (SodiumException) {
            throw self::malformed('contents');
        }
    }

    /**
     * @param array<array-key, mixed> $set
     */
    private function readOptions(array $set): LibreOfficeOptions
    {
        $class = new ReflectionClass(LibreOfficeOptions::class);
        $parameters = [];

        foreach (new ReflectionMethod(LibreOfficeOptions::class, '__construct')->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        $arguments = [];

        foreach ($set as $name => $value) {
            $parameter = $parameters[$name] ?? throw self::malformed('options');
            $arguments[$parameter->getName()] = $this->argument($parameter, $value);
        }

        try {
            $options = $class->newInstanceArgs($arguments);
        } catch (TypeError) {
            throw self::malformed('options');
        }

        // Reflection converts a scalar of another type as a call without strict types would: what it converted reads
        // back differently.
        return $this->options($options) === $set ? $options : throw self::malformed('options');
    }

    /**
     * An option as the constructor takes it: an enumeration from its value, null for a value it does not have, which
     * then reads back differently; anything else as it is.
     */
    private function argument(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();
        $class = $type instanceof ReflectionNamedType ? $type->getName() : '';

        if (!is_subclass_of($class, BackedEnum::class)) {
            return $value;
        }

        return \is_string($value) ? $class::tryFrom($value) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function object(mixed $value, string $key): array
    {
        return \is_array($value) ? $value : throw self::malformed($key);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) && array_is_list($value) ? $value : throw self::malformed($key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw self::malformed($key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function bool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::malformed($key);
    }

    private static function malformed(string $key): JobUnavailable
    {
        return JobUnavailable::because(\sprintf('A kept job holds no valid "%s": the job store did not write it.', $key));
    }
}
