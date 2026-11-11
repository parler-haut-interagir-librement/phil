<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The metadata written before the text: the YAML front matter of Markdown, between two `---` lines, the second one
 * may be `...`. YAML that does not parse, or is not a mapping, is not metadata: the lines stay text. A dialect that
 * reads metadata of its own reads its YAML with `yaml()`.
 */
final class FrontMatter
{
    /**
     * @param list<string> $lines
     *
     * @return array{array<array-key, mixed>, list<string>}
     */
    public static function split(array $lines): array
    {
        $count = \count($lines);

        for ($end = 1; '---' === ($lines[0] ?? '') && $end < $count; ++$end) {
            if (\in_array($lines[$end], ['---', '...'], true)) {
                $metadata = self::yaml(implode("\n", \array_slice($lines, 1, $end - 1)));

                return null === $metadata ? [[], $lines] : [$metadata, \array_slice($lines, $end + 1)];
            }
        }

        return [[], $lines];
    }

    /**
     * The data of a YAML text; `null` when it is not YAML, or not a map or a list.
     *
     * @return array<array-key, mixed>|null
     */
    public static function yaml(string $yaml): ?array
    {
        try {
            $data = Yaml::parse($yaml);
        } catch (ParseException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }
}
