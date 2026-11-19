<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis;

use InvalidArgumentException;
use Phil\BrailleExport\Contraction;

/**
 * Which liblouis table translates each language with each contraction.
 *
 * A language is a BCP 47 tag: `fr-CA` takes the tables of `fr-ca` when there are some, else those of `fr`. A table is
 * the file name of a liblouis table, or several separated by commas, never a path: the tables are found in the table
 * directory of liblouis, or in `$tablePath` of {@see LiblouisTranslator}.
 */
final readonly class LiblouisTables
{
    private const string TABLE = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}(,[A-Za-z0-9][A-Za-z0-9._-]{0,127}){0,7}$/D';

    private const string LANGUAGE = '/^[a-z]{2,3}(-[a-z0-9]{1,8})*$/D';

    /** @var array<string, array<string, string>> each language in lower case, to each contraction, to its tables */
    private array $tables;

    /**
     * @param array<string, array<string, string>> $tables each language, to `uncontracted` or `contracted`, to its tables
     */
    public function __construct(array $tables)
    {
        $checked = [];

        foreach ($tables as $language => $contractions) {
            $language = $this->language($language);

            if (1 !== preg_match(self::LANGUAGE, $language)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a language tag.', $language));
            }

            foreach ($contractions as $contraction => $table) {
                if (null === Contraction::tryFrom($contraction)) {
                    throw new InvalidArgumentException(\sprintf('"%s" is neither "uncontracted" nor "contracted".', $contraction));
                }

                if (1 !== preg_match(self::TABLE, $table)) {
                    throw new InvalidArgumentException(\sprintf('"%s" is not a list of liblouis table names.', $table));
                }

                $checked[$language][$contraction] = $table;
            }
        }

        $this->tables = $checked;
    }

    /**
     * The tables of Unified English Braille and of the Code braille français uniformisé, as liblouis names them.
     */
    public static function defaults(): self
    {
        return new self([
            'en' => ['uncontracted' => 'en-ueb-g1.ctb', 'contracted' => 'en-ueb-g2.ctb'],
            'fr' => ['uncontracted' => 'fr-bfu-comp6.utb', 'contracted' => 'fr-bfu-g2.ctb'],
        ]);
    }

    /**
     * A copy with the tables of one language and contraction set.
     */
    public function with(string $language, Contraction $contraction, string $table): self
    {
        $tables = $this->tables;
        $tables[$this->language($language)][$contraction->value] = $table;

        return new self($tables);
    }

    /**
     * The tables of a language and a contraction; null when none is set.
     */
    public function table(string $language, Contraction $contraction): ?string
    {
        $language = $this->language($language);

        return $this->tables[$language][$contraction->value]
            ?? $this->tables[explode('-', $language)[0]][$contraction->value]
            ?? null;
    }

    private function language(string $language): string
    {
        return strtolower(str_replace('_', '-', $language));
    }
}
