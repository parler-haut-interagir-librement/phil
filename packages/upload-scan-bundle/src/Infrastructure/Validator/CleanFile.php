<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Validator;

use Attribute;
use Override;
use Symfony\Component\Validator\Constraint;

/**
 * A file free of known threats: an `UploadedFile`, a `File` or a local path, scanned by the antivirus. An infected file
 * is refused; a file that could not be scanned is refused, or accepted and flagged, as `on_error` says. `null` and `''`
 * pass, as with every constraint but `NotNull`; an upload that did not arrive whole is left to the `File` constraint.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class CleanFile extends Constraint
{
    public const string INFECTED_ERROR = 'b0c5a2b6-5f37-4f0a-9d0e-2b7e8f1c6a41';

    public const string NOT_SCANNED_ERROR = '5d0f9f43-0a7e-4b8e-8f61-7c2d3e9a1b52';

    protected const array ERROR_NAMES = [
        self::INFECTED_ERROR => 'INFECTED_ERROR',
        self::NOT_SCANNED_ERROR => 'NOT_SCANNED_ERROR',
    ];

    public string $infectedMessage = 'This file contains a threat and was refused.';

    public string $notScannedMessage = 'This file could not be checked for threats. Please try again later.';

    /**
     * @param string            $context where the file comes from, given to the events: `upload` by default
     * @param list<string>|null $groups
     */
    public function __construct(
        public string $context = 'upload',
        ?string $infectedMessage = null,
        ?string $notScannedMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->infectedMessage = $infectedMessage ?? $this->infectedMessage;
        $this->notScannedMessage = $notScannedMessage ?? $this->notScannedMessage;
    }

    #[Override]
    public function validatedBy(): string
    {
        return CleanFileValidator::class;
    }
}
