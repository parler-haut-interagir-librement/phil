# The model

## Subjects

A `Subject` is who a relation is given to:

| Subject | Written | Means |
|---|---|---|
| `Subject::of($reference)` | `member:ann` | One object. |
| `Subject::every('member')` | `member:*` | Every object of a kind: one tuple for all members. |
| `Subject::set($reference, 'member')` | `group:staff#member` | Every subject holding `member` on `group:staff`. |

`Subject::fromString()` reads what `toString()` writes. A subject's identifier holds no `#`, and is never `*` alone,
which names every object of a kind. A `ResourceReference` of `phil/resource` is accepted wherever a subject is, and
stands for that one object.

## Tuples

A `RelationTuple` is the one stored fact: `$subject` holds `$relation` on `$object`, until `$expiresAt` when it is
given. It is known by its object, relation and subject — `key()` writes `object#relation@subject` —; writing it again
replaces its expiry. Relations are 1 to 64 lowercase letters, digits, `_`, `.` or `-`, starting with a letter, the
rule of a resource kind; identifiers are UTF-8 text.

## Kinds and relations

A `Model` holds `TypeDefinition`s: a kind and its relations, each with a `Rewrite` that says how it is computed. A kind
that is only ever a subject, such as `member`, is declared without relations.

| Rewrite | Holds |
|---|---|
| `Rewrite::direct('member', 'member:*', 'group#member')` | The tuples written on the relation, given to subjects of the types named. |
| `Rewrite::computed('editor')` | Every subject holding `editor` on the same object. |
| `Rewrite::fromRelated('parent', 'viewer')` | Every subject holding `viewer` on an object that `parent` points at. |
| `Rewrite::union($a, $b, …)` | The subjects of any of them. |
| `Rewrite::intersection($a, $b, …)` | The subjects of every one of them. |
| `Rewrite::exclusion($base, $subtract)` | The subjects of `$base` that `$subtract` does not hold. |

A relation inherited down a tree is a `fromRelated()` over a `parent` relation; a group inside a group is a direct
type `group#member` on the group's own `member` relation; a role that includes another is a `computed()`.

```php
TypeDefinition::of('doc', [
    'owner'   => Rewrite::direct('member'),
    'parent'  => Rewrite::direct('folder'),
    'blocked' => Rewrite::direct('member'),
    'editor'  => Rewrite::union(Rewrite::direct('member', 'group#member'), Rewrite::computed('owner')),
    'viewer'  => Rewrite::exclusion(
        Rewrite::union(Rewrite::direct('member', 'member:*', 'group#member'), Rewrite::computed('editor'), Rewrite::fromRelated('parent', 'viewer')),
        Rewrite::computed('blocked'),
    ),
])
```

`TypeDefinition::with()` gives the same kind with more relations, refusing one already declared: an application adds
its own relations to a kind a bundle declares.

## What the model checks when built

The constructor refuses, with `InvalidRelation`:

- a kind declared twice;
- a direct type naming a kind the model does not declare, or a subject set on a relation that kind does not declare;
- a `computed()` relation the same kind does not declare;
- a `fromRelated()` whose followed relation is not a direct relation of the kind, or whose objects — the direct types
  that are one object — include no kind declaring the relation read.

So a check, a listing and a write only ever meet what the model declares. `accepts()` says whether a tuple may be given
to a subject: one of the relation's direct rewrites accepts the subject's type. A relation computed only from others
takes no tuple.

## Writing tuples

`RelationWriter` writes what the model accepts:

```php
$writer->grant($doc, 'viewer', $member, new DateTimeImmutable('+30 days')); // until that second
$writer->revoke($doc, 'viewer', $member);
$writer->update(revoke: [$oldTuple], grant: [$newTuple]);                   // all of it or nothing
$writer->forget($member);                                                   // every tuple on it or given to it
```

An update touches at most 1,000 tuples. Each granted tuple is checked first: one the model does not accept refuses the
whole update, and nothing is written.
