<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The algorithm a PDF is encrypted with; AES-256 unless an old reader cannot open it.
 */
enum EncryptionAlgorithm: string
{
    case Aes256 = 'aes256';
    case Aes128 = 'aes128';
    case Rc4128 = 'rc4-128';
    case Rc440 = 'rc4-40';
}
