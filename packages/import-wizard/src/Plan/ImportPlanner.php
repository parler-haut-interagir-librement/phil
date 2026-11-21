<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

use Phil\ImportWizard\Contract\KeyLookup;
use Phil\ImportWizard\Contract\RowSource;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\ImportProfile;

/**
 * The dry run: reads every row of a file with a profile and decides, row by row, whether it creates, updates or is
 * refused, without writing anything. It keeps no state between calls.
 */
final readonly class ImportPlanner
{
    /**
     * Plans the file with the profile's mapping, completed by a guess for the columns it leaves unmapped, or with the
     * mapping given.
     *
     * @throws SourceRefused     when the file has no header
     * @throws MappingIncomplete when a required column has no header
     */
    public function plan(RowSource $source, ImportProfile $profile, KeyLookup $lookup, ?ColumnMapping $mapping = null): ImportPlan
    {
        $headers = $source->headers();
        $mapping = ($mapping ?? $profile->mapping ?? new ColumnMapping())->completedFor($headers, $profile);
        $resolved = $mapping->resolve($headers);
        $missing = [];

        foreach ($profile->columns as $column) {
            if ($column->required && !\in_array($column->name, $resolved, true)) {
                $missing[] = $column->name;
            }
        }

        if ([] !== $missing) {
            throw MappingIncomplete::missing($missing);
        }

        $planning = new Planning($profile, $resolved, \count($headers));

        foreach ($source->rows() as $row) {
            $planning->read($row);
        }

        [$rows, $rejections] = $planning->decide($lookup);

        return new ImportPlan($profile, $headers, $mapping, $rows, $rejections);
    }
}
