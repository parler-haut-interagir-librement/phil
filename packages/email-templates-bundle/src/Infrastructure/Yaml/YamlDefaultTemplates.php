<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Yaml;

use FilesystemIterator;
use Override;
use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The default templates, one YAML file per key and locale, `<key>.<locale>.yaml` — `ticket.fr.yaml`,
 * `order.confirmation.en.yaml` — in the directories given, the first that has a file winning: the application's
 * directories come before the bundle's, so a file of the application overrides the bundle's file of the same name.
 *
 * ```yaml
 * subject: 'Your ticket for {{ event.title }}'
 * preheader: 'Show it at the door.'
 * format: markdown
 * body: |
 *     Hello {{ recipient.name }},
 *
 *     {{ button('See my ticket', ticket_url) }}
 * segments: [first-time]
 * variables:
 *     recipient: { type: object, required: true, fields: { name: { type: string, sample: 'Ada' } } }
 *     ticket_url: { type: url, required: true, sample: 'https://example.com/tickets/42' }
 * ```
 *
 * The directories are read once per process; a file is read and checked the first time its template is asked for.
 */
final class YamlDefaultTemplates implements DefaultTemplates
{
    private const string FILE = '/^([a-z][a-z0-9_.-]{0,63})\.([a-z]{2,3}(?:_[A-Za-z0-9]{2,8})?)\.ya?ml$/D';

    /** The largest default file read, in bytes. */
    private const int MAX_BYTES = 1_048_576;

    /** @var array<string, array<string, string>>|null the file of each locale, by key */
    private ?array $files = null;

    /** @var array<string, TemplateDefinition> */
    private array $read = [];

    /**
     * @param list<string> $directories the directories searched, the first winning; one that does not exist is skipped
     */
    public function __construct(
        private readonly array $directories,
    ) {
    }

    /**
     * @throws InvalidTemplate when the file of the template is not a valid template
     */
    #[Override]
    public function find(TemplateKey $key, Locale $locale): ?TemplateDefinition
    {
        $file = $this->files()[$key->value][$locale->value] ?? null;

        if (null === $file) {
            return null;
        }

        return $this->read[$file] ??= $this->read($file);
    }

    #[Override]
    public function catalog(): array
    {
        return array_map(array_keys(...), $this->files());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function files(): array
    {
        if (null !== $this->files) {
            return $this->files;
        }

        $files = [];

        foreach ($this->directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile() || 1 !== preg_match(self::FILE, $file->getFilename(), $match)) {
                    continue;
                }

                $locale = Locale::fromString($match[2])->value;
                $files[$match[1]][$locale] ??= $file->getPathname();
            }
        }

        ksort($files, \SORT_STRING);

        foreach ($files as &$locales) {
            ksort($locales, \SORT_STRING);
        }

        return $this->files = $files;
    }

    private function read(string $file): TemplateDefinition
    {
        $name = basename($file);
        $size = filesize($file);

        if (false === $size || $size > self::MAX_BYTES) {
            throw InvalidTemplate::because(\sprintf('The default template "%s" is larger than %d KiB.', $name, self::MAX_BYTES / 1024));
        }

        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $parseException) {
            throw InvalidTemplate::because(\sprintf('The default template "%s" is not valid YAML: %s', $name, $parseException->getMessage()));
        }

        if (!\is_array($data)) {
            throw InvalidTemplate::because(\sprintf('The default template "%s" is a map of its fields.', $name));
        }

        try {
            return TemplateDefinition::fromArray($data);
        } catch (InvalidTemplate $invalidTemplate) {
            throw InvalidTemplate::withErrors(array_map(static fn (string $error): string => $name.': '.$error, $invalidTemplate->errors));
        }
    }
}
