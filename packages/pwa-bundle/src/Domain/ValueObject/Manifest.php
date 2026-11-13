<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\Orientation;

/**
 * The web application manifest of the site: its names, its identity and the paths it starts at and stays within, how
 * it is shown, its colours, language, icons, shortcuts and categories. Every member is checked when it is built; the
 * start and the shortcuts are within the scope.
 */
final readonly class Manifest
{
    public const int MAX_ICONS = 30;

    public const int MAX_SHORTCUTS = 10;

    /** @var non-empty-string */
    public string $name;

    public ?string $shortName;

    public ?string $description;

    /** @var non-empty-string */
    public string $id;

    /** @var non-empty-string */
    public string $startUrl;

    /** @var non-empty-string */
    public string $scope;

    public ?string $themeColor;

    public ?string $backgroundColor;

    /**
     * @param list<DisplayMode> $displayOverride
     * @param list<Icon>        $icons
     * @param list<Shortcut>    $shortcuts
     * @param list<string>      $categories
     */
    public function __construct(
        string $name,
        ?string $shortName = null,
        ?string $description = null,
        string $id = '/',
        string $startUrl = '/',
        string $scope = '/',
        public DisplayMode $display = DisplayMode::Standalone,
        public array $displayOverride = [],
        public ?Orientation $orientation = null,
        ?string $themeColor = null,
        ?string $backgroundColor = null,
        public ?string $lang = null,
        public ?string $dir = null,
        public array $icons = [],
        public array $shortcuts = [],
        public array $categories = [],
    ) {
        $this->name = Rule::line($name, 'name', 120);
        $this->shortName = null === $shortName ? null : Rule::line($shortName, 'short name', 30);
        $this->description = null === $description ? null : Rule::line($description, 'description', 300);
        $this->id = Rule::localPath($id, 'id');
        $this->startUrl = Rule::localPath($startUrl, 'start address');
        $this->scope = Rule::localPath($scope, 'scope');
        $this->themeColor = null === $themeColor ? null : Rule::colour($themeColor, 'theme colour');
        $this->backgroundColor = null === $backgroundColor ? null : Rule::colour($backgroundColor, 'background colour');

        if (!Rule::isWithin($this->startUrl, $this->scope)) {
            throw InvalidManifest::because('The start address is within the scope.');
        }

        if (!$display->isFallback()) {
            throw InvalidManifest::because('The display is "fullscreen", "standalone", "minimal-ui" or "browser"; other modes go in the display override.');
        }

        if (\count($displayOverride) !== \count(array_unique(array_map(static fn (DisplayMode $mode): string => $mode->value, $displayOverride)))) {
            throw InvalidManifest::because('Each mode of the display override is given once.');
        }

        if (null !== $lang && 1 !== preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{1,8}){0,4}$/D', $lang)) {
            throw InvalidManifest::because('The language is a language tag, such as fr-CA.');
        }

        if (null !== $dir && !\in_array($dir, ['ltr', 'rtl', 'auto'], true)) {
            throw InvalidManifest::because('The direction is "ltr", "rtl" or "auto".');
        }

        if (\count($icons) > self::MAX_ICONS) {
            throw InvalidManifest::because(\sprintf('A manifest has %d icons at most.', self::MAX_ICONS));
        }

        if (\count($shortcuts) > self::MAX_SHORTCUTS) {
            throw InvalidManifest::because(\sprintf('A manifest has %d shortcuts at most.', self::MAX_SHORTCUTS));
        }

        foreach ($shortcuts as $shortcut) {
            if (!Rule::isWithin($shortcut->url, $this->scope)) {
                throw InvalidManifest::because('The address of a shortcut is within the scope.');
            }
        }

        if (\count($categories) > 10 || \count($categories) !== \count(array_unique($categories))) {
            throw InvalidManifest::because('A manifest has 10 categories at most, each once.');
        }

        foreach ($categories as $category) {
            if (1 !== preg_match('/^[a-z][a-z -]{0,31}$/D', $category)) {
                throw InvalidManifest::because('A category is 1 to 32 lower-case letters, spaces or "-", such as "books".');
            }
        }
    }
}
