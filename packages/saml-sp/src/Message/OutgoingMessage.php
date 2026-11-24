<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DOMElement;
use Phil\SamlSp\Binding\Binding;
use Phil\Url\Url;

/**
 * A message the service provider sends: its ID, its document, where and on which binding it goes, under which
 * parameter (`SAMLRequest` or `SAMLResponse`), and whether it must be signed.
 */
final readonly class OutgoingMessage
{
    public function __construct(
        public string $id,
        public DOMElement $root,
        public Url $destination,
        public Binding $binding,
        public string $parameter,
        public bool $signed,
    ) {
    }
}
