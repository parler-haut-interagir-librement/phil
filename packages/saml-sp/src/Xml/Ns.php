<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

/**
 * The namespaces and the identifiers of SAML 2.0, XML Signature and XML Encryption the library reads and writes.
 */
final class Ns
{
    public const string PROTOCOL = 'urn:oasis:names:tc:SAML:2.0:protocol';

    public const string ASSERTION = 'urn:oasis:names:tc:SAML:2.0:assertion';

    public const string METADATA = 'urn:oasis:names:tc:SAML:2.0:metadata';

    public const string DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    public const string XENC = 'http://www.w3.org/2001/04/xmlenc#';

    public const string XENC11 = 'http://www.w3.org/2009/xmlenc11#';

    public const string XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const string EXC_C14N = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    public const string ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    public const string STATUS_SUCCESS = 'urn:oasis:names:tc:SAML:2.0:status:Success';

    public const string STATUS_REQUESTER = 'urn:oasis:names:tc:SAML:2.0:status:Requester';

    public const string BEARER = 'urn:oasis:names:tc:SAML:2.0:cm:bearer';

    public const string ENTITY = 'urn:oasis:names:tc:SAML:2.0:nameid-format:entity';

    private function __construct()
    {
    }
}
