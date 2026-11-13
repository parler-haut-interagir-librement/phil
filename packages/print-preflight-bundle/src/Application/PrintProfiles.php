<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application;

use Phil\PrintPreflight\ColourWorkflow;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflightBundle\Application\Exception\UnknownProfile;

/**
 * The printer profiles of the configuration, by name, and the one used when none is named.
 */
final readonly class PrintProfiles
{
    /**
     * @param non-empty-array<array-key, PrintProfile> $profiles by name
     */
    public function __construct(
        private array $profiles,
        private string $default,
    ) {
        if (!isset($profiles[$default])) {
            throw UnknownProfile::named($default, array_keys($profiles));
        }
    }

    /**
     * The profiles of the bundle's configuration; one named `default`, with the library's defaults, when there is none.
     *
     * @param array<string, array{trim_sizes: list<string>, uniform_trim_size: bool, bleed: string, safety_margin: string, minimum_resolution: float, target_resolution: float, maximum_resolution: ?float, minimum_pages: int, maximum_pages: ?int, page_multiple: int, colour_workflow: string, allow_spot_colours: bool, severities: array<string, string>, ignore: list<string>, maximum_ink_coverage: float, minimum_line_width: string, allow_overprint: bool, allow_transparency: bool}> $profiles
     */
    public static function fromConfiguration(array $profiles, ?string $default = null): self
    {
        $built = [];

        foreach ($profiles as $name => $profile) {
            $built[$name] = new PrintProfile(
                trimSizes: array_map(Lengths::size(...), $profile['trim_sizes']),
                uniformTrimSize: $profile['uniform_trim_size'],
                bleed: Lengths::length($profile['bleed']),
                safetyMargin: Lengths::length($profile['safety_margin']),
                minimumResolution: $profile['minimum_resolution'],
                targetResolution: $profile['target_resolution'],
                maximumResolution: $profile['maximum_resolution'],
                minimumPages: $profile['minimum_pages'],
                maximumPages: $profile['maximum_pages'],
                pageMultiple: $profile['page_multiple'],
                colourWorkflow: ColourWorkflow::from($profile['colour_workflow']),
                allowSpotColours: $profile['allow_spot_colours'],
                severities: array_map(Severity::from(...), $profile['severities']),
                ignore: $profile['ignore'],
                maximumInkCoverage: $profile['maximum_ink_coverage'],
                minimumLineWidth: Lengths::length($profile['minimum_line_width']),
                allowOverprint: $profile['allow_overprint'],
                allowTransparency: $profile['allow_transparency'],
            );
        }

        if ([] === $built) {
            $built = ['default' => new PrintProfile()];
        }

        return new self($built, $default ?? array_key_first($built));
    }

    /**
     * A profile by name; the default one when the name is null.
     */
    public function get(?string $name = null): PrintProfile
    {
        $name ??= $this->default;

        return $this->profiles[$name] ?? throw UnknownProfile::named($name, $this->names());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(strval(...), array_keys($this->profiles));
    }

    public function defaultName(): string
    {
        return $this->default;
    }
}
