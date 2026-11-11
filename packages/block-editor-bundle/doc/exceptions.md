# Exceptions

Every exception implements `Phil\BlockEditorBundle\Application\Exception\BlockEditorException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidToolset` | `InvalidArgumentException` | A toolset names a block or inline tool the editor does not have, a preset other than `full` and `reduced`, or leaves out `paragraph` — from the configuration, when the container is built, or from a field's options, when the form is built |
| `InvalidSuggestion` | `InvalidArgumentException` | A `Suggestion` with a reference not of the form `kind:identifier`, or a label empty or longer than 200 characters |
| `UploadRefused` | `RuntimeException` | Thrown by an `ImageUploader` to refuse a file; its message reaches the writer |

Each message names what is wrong, for example `The block editor has no block tool named "statblock"; the block tools
are "paragraph", "header", …`.

A submitted document that `phil/ujo` refuses is not an exception of the bundle: the field fails its transformation
with the library's message ([forms.md](forms.md)).

## The endpoints' answers

| Endpoint | Status | Body |
|---|---|---|
| Image upload | 200 | `{"success": 1, "file": {…}}` |
| | 400 | `{"success": 0, "message": "No image was received."}` — no `image` field, or a failed upload |
| | 403 | `… "You may not use the editor."` — the `access` attribute not granted |
| | 403 | `… "The request does not come from the editor."` — without the editor's header, or from another site |
| | 404 | `… "Images cannot be uploaded here."` — no uploader configured |
| | 413 | `… "The image is too large."` — above `max_upload_bytes`, or of unknown size |
| | 422 | the `UploadRefused` message |
| Search | 200 | `{"items": [...]}` |
| | 403, 404 | `{"items": []}` — the `access` attribute not granted, from elsewhere, or no search configured |

The editor shows its own translated message for every failure but 422, whose message comes from the application.
