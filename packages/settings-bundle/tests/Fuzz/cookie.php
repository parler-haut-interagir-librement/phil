<?php

declare(strict_types=1);

/*
 * The reader of the preference cookie, on text nobody wrote: whatever it reads, it never throws, returns only values
 * its settings accept, and what it returns writes and reads back to the same values.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SettingsBundle\Application\BuiltInSettings;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$catalog = new SettingCatalog(
    [...BuiltInSettings::sections(), new SectionDefinition('author')],
    [...BuiltInSettings::display(), new SettingDefinition('author.public', SettingType::Boolean, section: 'author', anonymous: true)],
);
$format = new PreferenceCookieFormat($catalog);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($format, $catalog): void {
    $values = $format->parse($input);

    foreach ($values as $key => $value) {
        if ($catalog->get($key)->normalize($value) !== $value) {
            throw new LogicException(sprintf('The value read for "%s" is not one its setting accepts.', $key));
        }
    }

    $written = $format->format($values);
    $read = $format->parse($written);
    $expected = array_filter($values, static fn (bool|string $value, string $key): bool => $value !== $catalog->get($key)->default, \ARRAY_FILTER_USE_BOTH);
    ksort($expected);

    if ($read !== $expected) {
        throw new LogicException('What is read does not write and read back to the same values.');
    }
});
$config->setMaxLen(PreferenceCookieFormat::MAX_LENGTH + 64);
