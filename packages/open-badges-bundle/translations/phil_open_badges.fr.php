<?php

declare(strict_types=1);

return [
    'verify.title' => 'Vérifier un badge',
    'verify.refused' => 'Cette attestation ne se vérifie pas.',
    'verify.holds' => 'Cette attestation est valide.',
    'verify.does_not_hold' => 'Cette attestation n’est pas valide.',
    'verify.achievement' => 'Accomplissement',
    'verify.description' => 'Description',
    'verify.issuer' => 'Émise par',
    'verify.valid_from' => 'Valide à partir du',
    'verify.valid_until' => 'Valide jusqu’au',
    'verify.secured_as' => 'Signée par',
    'verify.secured.jwt' => 'un jeton JWT',
    'verify.secured.data-integrity' => 'une preuve Data Integrity',
    'verify.status' => 'Statut',
    'verify.status.active' => 'Ni révoquée ni suspendue',
    'verify.status.revoked' => 'Révoquée ou suspendue',
    'verify.status.unknown' => 'Non publié par ce site',
    'verify.recipient' => 'Destinataire',
    'verify.recipient.matches' => 'Émise à l’identité donnée',
    'verify.recipient.differs' => 'Pas émise à l’identité donnée',
    'verify.credential' => 'L’attestation : un jeton, ou le JSON d’un document signé',
    'verify.image' => 'Ou une image de badge (PNG ou SVG) où l’attestation est cuite',
    'verify.identity' => 'L’adresse courriel ou l’URL du destinataire, pour la vérifier (facultatif)',
    'verify.submit' => 'Vérifier',
];
