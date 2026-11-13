# JavaScript

## The controller

`assets/src/checkin_desk_controller.js` is a Stimulus controller, registered as
`phil--checkin-bundle--checkin-desk`. Its stylesheet, `phil-checkin/checkin-desk.css`, colours the result by outcome.
The controller is declared `"fetch": "lazy"`: StimulusBundle loads it, with its libraries, only on a page where an
element uses it, so that the other pages of the site do not download it.

```twig
<form data-controller="phil--checkin-bundle--checkin-desk"
      data-phil--checkin-bundle--checkin-desk-config-value="{{ desk|json_encode }}"
      data-action="phil--checkin-bundle--checkin-desk#submit">
    <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus>
    <select data-phil--checkin-bundle--checkin-desk-target="direction">
        <option value="in">Entry</option>
        <option value="out">Exit</option>
    </select>
    <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
    <p data-phil--checkin-bundle--checkin-desk-target="state"></p>
</form>
```

```php
// in the page's controller
$desk = $deskConfig->for(CheckpointId::of('main-door'));
```

| Target | Role |
|---|---|
| `code` | The field a scanner in keyboard mode, or the staff, types into; emptied after each scan. |
| `direction` | Optional: `out` sends exits; entries otherwise. |
| `result` | The outcome, the credential's label, and whether it was decided offline. |
| `state` | Optional: online or offline, and how many scans wait to be sent. |

The `config` value is what `DeskConfig::for()` gives, plus optional keys:

| Key | Meaning |
|---|---|
| `checkpoint` | The checkpoint of the desk. |
| `urls` | The endpoints; `manifest` and `sync` are null without a catalog. |
| `offline` | Whether the desk downloads a manifest and keeps a journal. |
| `csrfCookie` | The name of the cookie and header of the double-submitted CSRF token. |
| `device` | Optional: the device's identifier; otherwise one is drawn once and kept in the device's store. |
| `locale`, `messages` | The labels' language — the page's `lang` by default — and labels replaced by key. |

## Offline

When the desk is offline-capable, it downloads the manifest of its checkpoint on connection and every minute, and
keeps it on the device. A scan that cannot reach the server — no network, or a server error other than 503 — is
decided from the manifest: the HMAC-SHA256 of the code, computed with WebCrypto under the manifest's key, finds its
credential; the same policy as the server's decides; the device's copy of the attendance is updated; and the scan
joins the journal under the identifier it was first sent with. A code the manifest does not hold is offered to the
page's listeners of `phil-checkin:lookup`, and is otherwise unknown; an expired manifest decides nothing.

A scan answered online also updates the device's copy of the credential's attendance, so that a holder admitted
online a minute ago is not admitted again offline before the next manifest arrives.

When the network is back — the `online` event, or the next minute —, the journal is sent, at most 500 scans at a
time, and the scans the server names are forgotten. A fresh manifest then replaces the old one, and the scans still in
the journal — those the server has not recorded yet — are applied to it again. WebCrypto needs a secure context:
serve the desk over HTTPS.

Each device checks in offline on its own, from what it keeps: no server on the site, no relay between the devices.
Two devices offline at the same time do not see each other's scans, so that a ticket may be admitted at two doors
during an outage; the server decides every scan again when the journals arrive, records the decision the holder met at
the door, and flags the scan as a conflict when its own decision differs, for the staff to follow up. The shorter the
outage, the fewer such conflicts: each device sends its journal as soon as it reaches the server again.

## Where the device keeps it

The controller keeps its identifier, the manifest, the attendance it changed and the journal in the first store the
browser grants, which the element names in `data-store`:

| Store | `data-store` | When |
|---|---|---|
| IndexedDB, database `phil-checkin` | `indexeddb` | Whenever the browser opens it: most browsers, most of the time. |
| `localStorage` | `local` | IndexedDB is missing or refuses to open. |
| The memory of the page | `memory` | Neither is granted, as in some private windows: lost on reload. |

IndexedDB comes first because a manifest is large: 10 000 credentials, the default limit, weigh about 1.5 MB as
JSON, and 100 000, the highest, about 15 MB — more than the 5 MB or so a browser grants `localStorage` for a site,
while IndexedDB is granted a share of the disk. The manifest is written once, when it is downloaded; each scan decided
offline writes only the entry it changed and the journal, each in one transaction, so that two tabs on the same
checkpoint never lose each other's scan.

A store that refuses a write — a full quota — loses only that write: the scan is still decided and shown, with
`not kept on this device` added to the result and `kept: false` in the `checked` event, so that the staff knows the
journal will not carry it. The store is per site and per browser profile.

### What `localStorage` kept

What a device kept in `localStorage` — its identifier, a manifest, a journal not yet sent — moves to IndexedDB the
first time IndexedDB opens for the site: on a browser that refused IndexedDB before and grants it now, or on a device
that ran a copy of the controller that kept everything in `localStorage`. Each value is removed from `localStorage`
once IndexedDB keeps it, so that a write IndexedDB refuses leaves the value where it was for the next opening. A
journal is joined to the one already in IndexedDB, its scans first and none twice, so that no scan waiting to be sent
is lost; any other value moves only when IndexedDB holds none under its key. A value that is not JSON is dropped.
Only the keys that start with `phil-checkin:` move: the page's own values stay in `localStorage`.

### Limits

| Limit | Value | What it means for the desk |
|---|---|---|
| Credentials in a manifest | `offline.max_credentials`, 10 000 by default, 100 000 at most | About 150 bytes each as JSON: 1.5 MB by default, 15 MB at most. |
| `localStorage` | About 5 MB per site, shared with the page | Holds a manifest of some 30 000 credentials at most; a larger one is decided from the page's memory, and lost on reload. |
| IndexedDB | A share of the disk the browser sets, hundreds of megabytes at least | Holds the largest manifest and its journal on any device a desk runs on. |
| Scans sent at once | 500 by the controller, `offline.batch_limit` on the server | A longer journal goes in several requests, one a minute or at each `online` event. |
| Age of a scan sent | `offline.max_age_seconds`, two days by default | A scan older than that is refused by the server: send the journal before. |
| Use of a manifest | `offline.manifest_seconds`, twelve hours by default | An expired manifest decides nothing: the desk needs the network once in that time. |

The browser may clear a site's storage when the disk runs short, and Safari clears it after seven days of use without
a visit to the site. A desk opened every day keeps its store; an application that wants it kept whatever happens asks
the browser with `navigator.storage.persist()` from its own page, which some browsers grant only to an installed site.
A journal is sent as soon as the network is back, so that what a clearing can lose is only the scans of an outage.

## Events

| Event | Direction | Detail | Role |
|---|---|---|---|
| `phil-checkin:code` | to the controller's element | `{code}` | A reader — a camera scanner, an NFC reader — hands over a code it read. |
| `phil-checkin:lookup` | from the controller's element, offline | `{code, checkpoint, respond(entry)}` | A package that recognises codes the manifest cannot hold — codes that carry their credential signed inside them — answers with the entry the code stands for. |
| `phil--checkin-bundle--checkin-desk:checked` | from the controller | `{outcome, offline, kept, credential}` | The page, or a package, reacts to a scan: a sound, a counter, a reward. |

The element carries the last outcome in `data-outcome`, the number of scans waiting in the journal in `data-pending`,
and its store in `data-store`.

### Answering `phil-checkin:lookup`

The listener calls `event.detail.respond()` synchronously, with an entry or the promise of one — a signature checked
with WebCrypto is asynchronous. The first answer counts; a promise that rejects, or an answer that is not an entry, is
no answer. An entry has the shape of the manifest's:

| Key | Type | Default |
|---|---|---|
| `id` | the credential's identifier, a non-empty string | required |
| `label` | string or null | null |
| `validFrom`, `validUntil` | Unix timestamps in seconds, or null | null |
| `maxEntries` | integer or null for no limit | 1 |
| `revoked` | boolean | false |

The controller keeps the attendance of such a credential under its identifier, across manifests, and journals its
scans like the others: the application's catalog must find it by identifier, for the synchronisation to record it.

```js
form.addEventListener('phil-checkin:lookup', (event) => {
    event.detail.respond(verifySignedCode(event.detail.code)); // a promise of an entry, or of null
});
```
