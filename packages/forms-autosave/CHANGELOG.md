# Changelog

All notable changes to `phil/forms-autosave` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `DraftKey`: the opaque name of a draft, 16 to 64 URL-safe characters; `DraftContent`: the version of a form's
  structure and the values of its fields by name, bounded, read from and written to JSON; `InvalidDraft`, and
  `AutosaveException` for every exception of the bundle.
- `Draft`: a draft kept on the server under its owner's keyed hash and its key, versioned by a revision so that a save
  replaces only the revision read, its content sealed, kept until it expires, mapped for Doctrine as the table
  `phil_form_draft`.
- `DraftKeys`: the keys of drafts and the hashes of their owners, derived with a secret of the application.
- `Drafts`: read, save by revision, discard and purge the drafts of a user; an expired draft counts as none, one that
  no longer opens is deleted; `DraftCopy`, `RevisionConflict` and `DraftTooLarge`.
- The ports `DraftStore`, `DraftOwner` and `DraftSealer`; `DoctrineDraftStore`, by plain queries and compare-and-set;
  `InMemoryDraftStore`, for tests; `TokenDraftOwner`, the user of Symfony Security; `KeyManagementSealer`, by the
  envelope encryption of Symfony's KeyManagement component under `server.key`, through the client `server.kms` names
  (`symfony/key-management` suggested); `PlainSealer`, when encryption is turned off.
- `AutosaveTypeExtension`: the option `autosave` of every form — on a root form, the controller, the key derived from
  the form, its subject and its user, the structure version from `FormStructure`, the settings and the labels of
  `AutosaveLabels` on its tag, and with the server store the draft's URL and its CSRF cookie; on a field, `false` keeps
  it out and `true` puts it in; the field of `phil/block-editor-bundle` put in, its editor mounted again on restore.
- `SubmittedDraft` and `DoneCookie`: a submitted form kept in the request, and, if it is still valid when the response
  leaves, its draft deleted on the server and its key set in the cookie `phil_forms_autosave_done` for the browser.
- `DraftController`, `DraftRequest` and `Routes`: the endpoints of the server's drafts, `GET`, `PUT` and `DELETE` on
  one path per key, each behind the controller's header and Symfony's stateless CSRF token, never cached.
- `PurgeCommand`: `phil:forms-autosave:purge`, the expired drafts of the server deleted.
- `PhilFormsAutosaveBundle`, configured under `phil_forms_autosave`: `secret`, `ttl`, `debounce`, `max_bytes`, and
  `server` with `enabled`, `store`, `connection`, `encrypt`, `key`, `kms`, `max_bytes` and `owner`; the draft table
  mapped when the server store is on, the CSRF token listed among the stateless ones, the controller and its
  stylesheet given to AssetMapper; `ServerStorePass`, which completes the owner and the sealer and says what the server
  store lacks.
- The Stimulus controller `autosave`: drafts saved in local storage once the typing stops and when the form is sent or
  the page left, restored with a notice that can discard them, kept as submitted after an invalid submission,
  forgotten when the server says the form was sent; with the server store, saved by revision and a newer draft told
  with a notice that can use it or keep this one; a polite live region; its modules `fields`, `storage`, `debounce`,
  `csrf`, `server` and `ui`.
- `templates/status.html.twig`, the stylesheet `forms-autosave.css`, and the translations of the labels in English and
  French, domain `phil_forms_autosave`.
- A fuzz target on the reader of stored drafts.
