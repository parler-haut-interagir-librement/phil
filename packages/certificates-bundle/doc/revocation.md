# Revocation

```php
$revocation->revoke('7KQ2-M9XA-04TZ', 'The assessment was taken by someone else.');
```

`Phil\CertificatesBundle\Application\Revocation` revokes a certificate by its code, as printed or as typed, with a
reason of 1 to 500 characters. A certificate is revoked once: revoking it again, or an unknown code, throws
`CertificateRefused`. Revocation is final; a learner who earns the certificate again is awarded a new one.

Once revoked, the certificate's page says so with the reason, its PDF and credential answer `410 Gone`, and its bit is
set in the status list the issuer publishes. An expired certificate is not revoked: its bit stays clear, and its page
and files are still served ([Expiry and recertification](recertification.md)). A revoked certificate is not renewed.

## The status lists

Each certificate takes the next status index when it is awarded. `StatusListLayout` splits the indexes into lists of
`status_lists.size`: index `i` is bit `i mod size` of the list `<prefix>-<i div size>`, so the register never runs out
of indexes. The credential's `credentialStatus` points at that list, served by `phil/open-badges-bundle` at
`/status/<list>` under its prefix.

`CertificateStatusLists` implements the port `StatusLists` of `phil/open-badges-bundle`: for a list of the layout, it
reads the revoked certificates' indexes in its range from `CertificateRepository::revokedIndexes()`. A list of another
name is asked of the service `status_lists.others`, when the application has lists of its own, and is otherwise
unknown.

The size is the one of `phil_open_badges.status_lists.size`, or less: a list holds that many bits. The default,
131 072, is the smallest the specification allows, which hides which certificate a verifier checks among many.
