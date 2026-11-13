<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application;

use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\Licence\LicenceRegister;
use Phil\FontsBundle\Application\Exception\FontUnreadable;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The licence register kept beside the fonts, as JSON, and the catalogue compared with it: the files added, removed or
 * changed since their licence was last checked.
 */
final readonly class Licences
{
    /**
     * @param string $register the path of the JSON register
     */
    public function __construct(
        private FontCatalogue $catalogue,
        private string $register,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function path(): string
    {
        return $this->register;
    }

    /**
     * The register as it was last written; empty when there is none yet.
     */
    public function kept(): LicenceRegister
    {
        if (!is_file($this->register)) {
            return new LicenceRegister();
        }

        try {
            return LicenceRegister::fromJson($this->filesystem->readFile($this->register));
        } catch (InvalidLicenceRegister $invalidLicenceRegister) {
            throw FontUnreadable::file(basename($this->register), $invalidLicenceRegister);
        }
    }

    /**
     * The register of the catalogue as it is now.
     */
    public function current(): LicenceRegister
    {
        return $this->catalogue->licences($this->kept());
    }

    /**
     * The files whose licence must be checked again, sorted.
     *
     * @return list<string>
     */
    public function changedFiles(): array
    {
        return $this->kept()->changedFiles($this->current());
    }

    /**
     * Writes the register of the catalogue as it is now, once its files are checked; returns it.
     */
    public function write(): LicenceRegister
    {
        $current = $this->current();
        $this->filesystem->dumpFile($this->register, $current->toJson());

        return $current;
    }
}
