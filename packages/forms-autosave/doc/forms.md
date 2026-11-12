# Forms

## The option `autosave`

The bundle adds the option `autosave` to every form type.

On a root form, `true` or an array turns drafts on:

```php
$builder = $this->createForm(PostType::class, $post, ['autosave' => true]);
$builder = $this->createForm(PostType::class, $post, ['autosave' => ['subject' => $chapter->slug, 'server' => false]]);
```

| Key | Default | |
|---|---|---|
| `subject` | `null` | The record the form edits, part of the key: `null` reads `getId()` of the form's data, `false` takes none, a text, a number, a `Stringable` or a closure that receives the form's initial data and returns one of them |
| `server` | `true` | `false` keeps this form's drafts in the browser even with the server store on |

The form's tag then carries the controller `phil--forms-autosave--autosave` and its values: the key, the version of
the structure, `ttl`, `debounce`, `max_bytes`, the labels, `invalid` after an invalid submission, and, with the server
store and a signed-in user, the URL of the draft and the name of its CSRF cookie. A `data-controller` the form already
has is kept.

On a field, `false` keeps it out of drafts and `true` puts it in, even when it is hidden:

```php
$builder
    ->add('notes', TextareaType::class)
    ->add('bankAccount', TextType::class, ['autosave' => false])   // never kept
    ->add('position', HiddenType::class, ['autosave' => true]);    // kept, though hidden
```

The field gets `data-phil-autosave="off"` or `data-phil-autosave="on"`. In a template, the same attribute on a field
or on an element around it decides for the fields inside, the nearest one winning.

## Keys

A key is 32 URL-safe characters of a SHA-256 hash keyed with the configured secret, of the form's type and name, its
subject and its user. Two records edited with the same form have two drafts; two users on one browser have two drafts;
and the key tells nothing of any of them. The user is the identifier `DraftOwner` gives, null for a visitor.

## The structure version

A draft is put back only into the structure it was taken from: the version is a hash of the path and type of each
field. Adding, removing, renaming or retyping a field gives a new version, and older drafts are dropped. A collection
counts by the type of its entries, so adding an entry keeps the version.

## The fields kept

The controller keeps every named, enabled field of the form except:

- passwords, files and buttons;
- CSRF tokens: a name `_token`, ending in `[_token]`, or `_csrf_token`;
- fields whose `autocomplete` names a payment card (`cc-…`), a password or a one-time code;
- hidden fields, unless marked `on`;
- fields marked `off`, on themselves or around them.

The field of `phil/block-editor-bundle`, a hidden input holding a UJO (Universal JSON Output) document, is marked `on`
and given `data-phil-autosave-remount`: when a draft is restored, its editor is mounted again so that it reads the
restored document.

## Submissions

A submitted form is kept in the request. When the response leaves, if the form is still valid — after the errors the
application added itself, if any —, its key is set in the cookie `phil_forms_autosave_done` and its server copy is
deleted. The controller reads the cookie on the next page it runs on, forgets those drafts, and deletes the cookie.
An invalid submission keeps the draft, and the page shows the submitted values rather than the draft.
