<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

/**
 * Where the signing certificate comes from: files, a secret the application holds, a vault. Asked each time a PDF is
 * signed, so a renewed certificate is taken without a restart.
 */
interface CertificateSource
{
    public function certificate(): Certificate;
}
