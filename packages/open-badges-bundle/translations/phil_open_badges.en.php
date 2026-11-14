<?php

declare(strict_types=1);

return [
    'verify.title' => 'Verify a badge',
    'verify.refused' => 'This credential does not verify.',
    'verify.holds' => 'This credential is valid.',
    'verify.does_not_hold' => 'This credential is not valid.',
    'verify.achievement' => 'Achievement',
    'verify.description' => 'Description',
    'verify.issuer' => 'Issued by',
    'verify.valid_from' => 'Valid from',
    'verify.valid_until' => 'Valid until',
    'verify.secured_as' => 'Signed as',
    'verify.secured.jwt' => 'a JSON Web Token',
    'verify.secured.data-integrity' => 'a Data Integrity proof',
    'verify.status' => 'Status',
    'verify.status.active' => 'Neither revoked nor suspended',
    'verify.status.revoked' => 'Revoked or suspended',
    'verify.status.unknown' => 'Not published by this site',
    'verify.recipient' => 'Recipient',
    'verify.recipient.matches' => 'Issued to the identity given',
    'verify.recipient.differs' => 'Not issued to the identity given',
    'verify.credential' => 'The credential: a token, or the JSON of a signed document',
    'verify.image' => 'Or a badge image (PNG or SVG) with the credential baked in',
    'verify.identity' => 'The e-mail address or URL of the recipient, to check it (optional)',
    'verify.submit' => 'Verify',
];
