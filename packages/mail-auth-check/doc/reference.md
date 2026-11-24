# Reference

Every public type of the package, with its role. The namespace is `Phil\MailAuthCheck`.

## Checking a profile

| Type | Role |
|---|---|
| `SenderProfile` | What a sender expects: sending domain, return path, DKIM selectors, SPF includes, minimum DMARC policy. Names in lowercase ASCII, duplicates dropped, at most 16 selectors and 16 includes. |
| `MailAuthCheck` | Runs the checks of a profile in order and gathers their findings; `defaultChecks()` gives SPF, DKIM, DMARC and return path. |
| `Report` | The findings of one profile: `canSend()`, `failures()`, `warnings()`, `forCheck()`. |
| `Finding` | One finding: its check, `Status`, stable code and message. |
| `Status` | `Pass`, `Warning` or `Fail`; a failure blocks sending. |
| `DmarcPolicy` | `None`, `Quarantine`, `Reject`; `isAtLeast()` compares their strictness. |
| `Alignment` | `Relaxed` or `Strict`, for SPF and DKIM alignment. |

## Checks

| Type | Role |
|---|---|
| `Contract\Check` | The port of a check: `run(SenderProfile, TxtResolver): list<Finding>`. |
| `Check\SpfCheck` | The SPF record of the sending domain (`spf`). |
| `Check\DkimCheck` | The DKIM key of each expected selector (`dkim`). |
| `Check\DmarcCheck` | The DMARC record that applies to the sending domain, its policy and the alignment of the return path (`dmarc`). |
| `Check\ReturnPathCheck` | The SPF record of the return path when it differs from the sending domain (`return-path`). |

## DNS

| Type | Role |
|---|---|
| `Contract\TxtResolver` | The port that reads the TXT records of a name. |
| `Dns\DnsGetRecordResolver` | Asks the system's resolver through `dns_get_record()`; at most 32 records of a name. |
| `Dns\StaticTxtResolver` | Answers from a map of names, and fails for the names it is told to. |

## Records

| Type | Role |
|---|---|
| `Spf\SpfParser` | Reads an SPF record: at most 4096 bytes and 64 terms; `isSpf()` tells an SPF record from other TXT records. |
| `Spf\SpfRecord`, `Spf\SpfMechanism`, `Spf\Qualifier` | An SPF record read: its mechanisms in order, `redirect` and `exp`. |
| `Spf\SpfAnalyser` | Follows the includes and the redirect of a record and counts its DNS lookups, stopping at the eleventh. |
| `Spf\SpfAnalysis`, `Spf\SpfState` | What the analysis found: state, lookups, includes reached, loops, broken includes, macros, the `all` result. |
| `Dkim\DkimKeyParser` | Reads a DKIM key record and measures its key. |
| `Dkim\DkimKey`, `Dkim\DkimKeyType` | A DKIM key read: RSA or Ed25519, its size, revoked, testing. |
| `Dmarc\DmarcParser` | Reads a DMARC record; `isDmarc()` tells a DMARC record from other TXT records. |
| `Dmarc\DmarcRecord` | A DMARC record read: `p`, `sp`, `np`, `aspf`, `adkim`, `t`, `psd`, `rua`; `policyFor()` gives the policy of the domain or of its subdomains. |
| `Dmarc\DmarcTreeWalk` | The DNS tree walk of RFC 9989: the record that applies to a domain, and its organisational domain, in at most eight queries. |
| `Dmarc\DmarcDiscovery` | What the walk found: the record, where, the records skipped on the way, a failed lookup. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\MailAuthCheckException` | The interface every exception of the package implements. |
| `Exception\InvalidDomain` | A name of a profile that is not a valid host name, or an internationalised name without ext-intl. |
| `Exception\InvalidProfile` | A profile of more than 16 selectors or includes. |
| `Exception\InvalidRecord` | A record the parsers cannot read; the message says why. |
| `Exception\DnsLookupFailed` | A resolver that could not answer. |

## Finding codes

The codes of the return path are those of SPF with `return-path.` in place of `spf.`, plus `return-path.same-domain`.

| Code | Status | Meaning |
|---|---|---|
| `spf.lookup-failed` | Fail | The SPF record could not be read. |
| `spf.missing` | Fail | No TXT record starts with `v=spf1`. |
| `spf.multiple` | Fail | More than one SPF record. |
| `spf.invalid` | Fail | The record cannot be read. |
| `spf.too-many-lookups` | Fail | More than ten DNS lookups. |
| `spf.lookups-near-limit` | Warning | Nine or ten lookups. |
| `spf.lookups` | Pass | The lookups the record needs, eight or fewer. |
| `spf.include-loop` | Fail | A domain is included again inside itself. |
| `spf.include-broken` | Fail | An included or redirected domain has no usable record. |
| `spf.macro` | Warning | A macro domain, counted but not followed. |
| `spf.include-missing` | Fail | An expected include is reached nowhere in the record. |
| `spf.include` | Pass | An expected include is reached. |
| `spf.all-pass` | Fail | The record ends with `+all`. |
| `spf.all-neutral` | Warning | The record ends with `?all`. |
| `spf.no-all` | Warning | The record has no `all` and no redirect giving one. |
| `spf.all-softfail` | Pass | The record ends with `~all`. |
| `spf.all-fail` | Pass | The record ends with `-all`. |
| `return-path.same-domain` | Pass | The return path is the sending domain. |
| `dkim.no-selector` | Warning | The profile expects no DKIM selector. |
| `dkim.lookup-failed` | Fail | The key could not be read. |
| `dkim.missing` | Fail | No record at the selector. |
| `dkim.multiple` | Fail | More than one TXT record at the selector. |
| `dkim.invalid` | Fail | The key record cannot be read. |
| `dkim.revoked` | Fail | The key is empty. |
| `dkim.testing` | Warning | The key says `t=y`. |
| `dkim.key-too-short` | Fail | RSA under 1024 bits. |
| `dkim.key-short` | Warning | RSA under 2048 bits. |
| `dkim.ok` | Pass | RSA of 2048 bits or more, or Ed25519. |
| `dkim.ed25519-only` | Warning | Every expected key is Ed25519. |
| `dmarc.lookup-failed` | Fail | A lookup of the walk failed. |
| `dmarc.invalid` | Fail | The record of the sending domain is skipped. |
| `dmarc.parent-invalid` | Warning | The record of a parent is skipped. |
| `dmarc.missing` | Fail | No record applies. |
| `dmarc.inherited` | Pass | The record of a parent applies. |
| `dmarc.no-policy` | Warning | The record has no `p`. |
| `dmarc.policy-too-weak` | Fail | The policy is weaker than the profile expects. |
| `dmarc.policy-none` | Warning | The policy is `none`, which the profile accepts. |
| `dmarc.policy` | Pass | The policy is `quarantine` or `reject`, as strict as expected. |
| `dmarc.testing` | Warning | The record says `t=y`. |
| `dmarc.no-reports` | Warning | The record asks for no aggregate report. |
| `dmarc.return-path-aligned` | Pass | The return path aligns under the record's SPF alignment. |
| `dmarc.return-path-unaligned` | Warning, or Fail without DKIM selector | The return path does not align. |
| `dmarc.dkim-alignment` | Pass | The DKIM alignment the record asks for. |
