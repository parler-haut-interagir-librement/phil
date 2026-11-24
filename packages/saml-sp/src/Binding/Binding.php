<?php

declare(strict_types=1);

namespace Phil\SamlSp\Binding;

/**
 * The bindings the service provider sends and receives on: HTTP-Redirect, the message deflated in the query and signed
 * there, and HTTP-POST, the message in a form the browser posts, signed inside.
 */
enum Binding: string
{
    case Redirect = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
    case Post = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
}
