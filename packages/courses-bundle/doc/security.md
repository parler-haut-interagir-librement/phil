# Security

A package comes from another platform, and its files hold scripts nobody on this site wrote. The import reads it as
untrusted input, and its content runs on an origin of its own.

## The archive

The bundle reads packages through phil/elearning-packages, within the limits of `import.limits`; the documentation of
that library explains every check in its limits and security. Its `ZipPackage` checks the whole archive before any file
is read:

| Check | Refused |
|---|---|
| Size of the archive | Past `import.limits.archive_bytes` |
| Number of entries | Past `import.limits.entries` |
| Each entry's name | Empty, longer than 1 024 bytes, not UTF-8, absolute (`/`, `C:`), with a backslash, a colon or a control character, a `.` or `..` segment, an empty segment, or deeper than 32 directories |
| Names that differ only by case | The second one: on a case-insensitive disk, it would overwrite the first |
| Symbolic links | Any entry whose Unix mode is a link |
| Encrypted entries | Any |
| Size announced by each entry, and their sum | Past `import.limits.entry_bytes`, or `import.limits.total_bytes` |
| Compression ratio of an entry larger than 1 MiB | Past `import.limits.ratio`: a compression bomb |

A file is then read through a stream that stops one byte past what its entry announced: an entry whose header lies, one
whose data ends early or does not match its CRC-32, is refused, and the bytes read across the package count against the
same total. An import that fails half-way removes the course and the files it wrote.

A path in a manifest is resolved against its `xml:base` values, its escapes decoded and its `.` segments dropped, then
checked the same way: a reference that would leave the package, such as `../../etc/passwd` or `%2e%2e/`, refuses the
package.

## The manifest

`imsmanifest.xml` and `cmi5.xml` are read by PHP's DOM extension:

- a document that declares a document type or an entity is refused, so that no entity — internal, external, or nested
  to expand — is ever resolved;
- the parser never reaches the network;
- the document, the depth of its items or blocks, and their number are bounded by `import.limits`;
- each identifier, title and URL is bounded in length, and a large sequencing element is not kept.

The fuzz targets of phil/elearning-packages feed the readers of the manifests and of the archives with inputs nobody
wrote, and the fuzz target of the bundle feeds the checks of the statements; see [Testing](testing.md).

## The content origin

The files are stored outside the public directory, in `storage.directory` or the application's `ContentStorage`, and
served from the content origin of `content.base_url`. That origin must not be the application's: the activity page
refuses to frame content whose origin is the site itself. Best is a registrable domain of its own, such as
`example-content.org`; a subdomain of the site's domain shares its site, and any cookie the application sets for the
whole domain.

`ContentController` answers on the content origin only — any other host is not found — and sends each file with:

| Header | Value |
|---|---|
| `Content-Type` | The type of its extension among HTML, scripts, styles, JSON, XML, text, captions, images, audio, video, fonts and PDF; `application/octet-stream` with `Content-Disposition: attachment` for any other |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `no-referrer` |
| `Content-Security-Policy` | `default-src 'self'`; scripts and styles of the content's own origin, inline included, as SCORM content needs; images, media and fonts of its origin and `data:` or `blob:`; `connect-src` its origin and the application's, for the xAPI endpoint; `object-src 'none'`; `base-uri` and `form-action` its origin; `frame-ancestors` the application's origin |

A web server that serves the storage directory itself sends the same headers, on a host that serves nothing else.

## The frame

The activity page frames content with `sandbox="allow-scripts allow-same-origin allow-forms allow-popups
allow-downloads"`. The content keeps its own origin — the content origin, never the application's — which its calls to
the xAPI endpoint need, and it cannot reach the page that frames it.

## The SCORM run time

The frame of the run time, `_phil/scorm/frame.html` and its scripts, is served on the content origin only, with
`default-src 'none'`, scripts of its own origin only, `frame-src 'self'` and `frame-ancestors` the application's origin:
only the application frames it, and it frames only the content origin. The SCOs it frames are served with
`frame-ancestors 'self'` and the application's origin, so that the frame on the content origin can hold them.

The frame and the activity page talk by `postMessage()`, each to the other's origin alone: the frame accepts the
state of a session from its parent window and the application's origin only, the activity page accepts writes from its
own frame and the content origin only. The state holds the learner's identifier, never a name or an e-mail address.

A commit carries an attempt token — the attempt, the session and an expiry, signed by an HMAC under
`phil_courses.scorm.secret`, nothing secret kept in the database — and the stateless CSRF token of the forms: the
browser's `Sec-Fetch-Site`, `Origin` or `Referer` must say it comes from the site. A token of another learner signed in,
of an earlier session, or expired is refused, and so is any commit to an ended attempt: an attempt opened for review
is given no token, and what its content writes is never sent. The body is bounded at 2 MiB, a commit at 4 000 values,
the data of an attempt at 1 MiB, each value by the data model of its element.

A time limit is held by the server's clock, never by the browser's nor by the session time the content reports: a page
that stops its timer, or a content that lies about its time, still sees its attempt end at the deadline, and a commit
later than 30 seconds past it is not kept.

## The cmi5 endpoint

The xAPI endpoint answers a request carrying the token of an open session only, and only for that session's actor,
registration and activity. The token is fetched once. Cross-origin requests are answered for the content origin and
`cmi5.allowed_origins`, never for any origin. A statement breaking a rule of cmi5 is refused before it reaches the
learning record store, and the learners are named by account, never by e-mail address.

## The xAPI activities

An xAPI activity's token is handed to the content in the launch URL, as xAPI packages expect, rather than through a
fetch URL: it opens its own session only, until the session is abandoned by the next launch or expires after
`cmi5.session_ttl` seconds, and the content origin answers with `Referrer-Policy: no-referrer`, so that the URL does
not leave in a `Referer`. A statement is kept only for the learner of the session, named by the same account, with the
session's registration; a statement that voids another is refused, so that a recorded outcome stays. The import of an
xAPI package reads `tincan.xml` through the configured reader only, which bounds its reading; without one, the
package is refused.

## The remote learning record store

The statements go to a remote store through phil/safe-http only: an `https` endpoint of a public host, its address and
the address it resolves to checked against the private networks, no proxy, no redirect followed, the silence and the
duration of each request bounded. The password is read from an environment variable and sent as HTTP Basic
credentials to the endpoint's origin only. Neither the log nor the exceptions name a statement, a registration, a
learner, the credentials or what the store answered: they name the statement's identifier and the status. The state
documents of the units stay in the application's database and are never sent to the remote store. A store on a private
network is refused, as [cmi5](cmi5.md#the-limits-of-the-remote-store) explains.

## The training and the transcripts

The training and transcript pages show the learner signed in their own records only; the managers' pages ask for the
role of `phil_courses.training.manager_role`, and are refused without an authorization checker or without a person
signed in. Every answer is `no-store`. The CSV export prefixes a cell a spreadsheet would read as a formula with a
quote, and is sent as an attachment with `nosniff`. The notifications of the training carry identifiers only. An
assignment reaches a bounded number of members, and a run reads the obligations a batch at a time.

## The administration page

The page of the second chances asks for the role of `phil_courses.admin.role`, through Symfony Security's
authorization checker; without a checker, or without a teacher signed in, it is refused. Each decision carries the
stateless CSRF token of the forms, and a request the browser says came from another site is refused. The reason a
learner gave and the note of a teacher are kept in the tables, escaped on the page, and never written to the audit
trail, which keeps identifiers only.

## The catalogues

A catalogue of a unit is shown to the learners whose `LearnerUnits` name that unit, and to nobody else: to anyone
else, visitors signed out included, it answers 404, as a catalogue that does not exist, and its courses are never
listed. The enrollment from a catalogue asks for a learner signed in and the stateless CSRF token of the forms, and
enrolls them only in a free course of a catalogue shown to them that is open to self-enrollment; a course with a price
is enrolled in only once the store says its order is paid, for the customer of that order. The address a `CourseCheckout`
answers is followed only when it is a path or an address of the request's origin, so that a checkout cannot send a
learner to another site. The query string of a catalogue page is read within bounds — 100 characters of words, a tag of
the shape an entry keeps, a page up to 1 000 — and what it does not say reads as the default; it is fuzzed. A listing
reads one query for the courses of the catalogue, a thousand at most, and a few for each of the 20 courses of the page;
the units of a learner are read once, 500 at most.
