# Reactions

## The list

A site offers reactions in labelled sources, shown under their heading in the picker. The configuration gives two of
them: `unicode`, whose heading is the translation key `source.unicode`, and `site`, `source.site`. The order is the
option `order`, then the others. An application adds a source of its own, read from its storage, by implementing
`ReactionSource`; autoconfiguration tags it. Each source is read once per request; at most 128 reactions, and no two
with the same code.

A reaction is `quick` when it stands in the bar from the start. The others wait in the picker; once someone has chosen
one, it comes to the bar for everyone who sees its counter. An image declared as a medium that no longer exists is not
offered.

Removing a code from the list leaves its rows in the table, which are no longer counted or shown.

## Several reactions per member

By default a member gives one reaction to a content, and a new one replaces it. A site that wants more says how many:

```yaml
phil_reactions:
    per_member: 3
```

Each member then gives each code at most once, and at most three codes to one content. Once they reach the cap, the
reactions they gave stay pressed and the others are shown but cannot be pressed, the picker closed, with a sentence
saying to take one back first; a reaction sent anyway is refused, the component saying it in its live region and the
plain form answering `409`. Taking one back, by pressing it again, makes room for another. The cap goes from 1 to 20,
since every reaction a member gives is read on each page.

When two requests race past the cap, or past the single reaction, the store keeps the oldest reactions and removes the
newer ones, so both requests leave the same rows. Lowering the cap keeps the reactions already given; a member above
it takes some back before giving another.

## Private reactions

A site that wants readers to say something is not useful declares it private, and offers no negative reaction any
other way:

```yaml
phil_reactions:
    unicode:
        - { code: like, emoji: '👍', label: reaction.like }
        - { code: not_useful, emoji: '🫤', label: reaction.not_useful, private: true }
```

A private reaction never has a counter or names on a page, whoever is looking. The member who chose it sees it
pressed. It remains in the store, for the application's own tools. Like any reaction, it replaces the one the member
had on that content, or takes one more place under the cap.

## The visibility

| Option | Question | Default |
|---|---|---|
| `panel` | Who sees the reactions at all | `everyone` |
| `counts` | Who sees the counters | `everyone` |
| `names` | Who sees the names of those who reacted | `nobody` |

Each answers `everyone`, `members` or `nobody`. A `VisibilityPolicy` decides content by content. Names also need a
`MemberNames` service; a member it leaves out is not shown, and an identifier never stands in for a name. At most ten
names are shown for a reaction, followed by how many others when the viewer sees the counters, as one list written in
the language of the page by `format_list` of twig/intl-extra: "Ada, Linus and 3 others".

A visitor has no reaction of their own: they see what is counted, as plain text.

## Who may react

The attribute `Reactions::REACT`, `phil_reactions.react`, is asked about a `ReactionSubject`: the content, the member
and the definition of the reaction. With no voter answering, Symfony refuses, and without Security nobody may react. A
voter can reserve a reaction, the emojis of a site for its subscribers say:

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
{
    \assert($subject instanceof ReactionSubject);

    return 'gnome' !== $subject->reaction->code || \in_array('ROLE_SUBSCRIBER', $token->getRoleNames(), true);
}
```

A reaction the voter refuses is not offered. A member can always take back the reaction they have, even once it is
refused.

## The member

The member is an identifier, kept in the table and carried by the events. The site gives it, by the `CurrentMember`
service it names in `current_member`: a stable identifier that never changes and names nobody else after them, such
as the identifier of an account or the `sub` claim of an identity provider, and never an email address or a username.

With Symfony Security, `current_member` is required: the container is not built without it, and its message says so.
`TokenCurrentMember` gives the user identifier of the security token, for a site whose user identifiers are stable:

```yaml
phil_reactions:
    current_member: Phil\ReactionsBundle\Infrastructure\Security\TokenCurrentMember
```

An identifier that contains `@` is refused, whatever service gives it: `TokenCurrentMember` throws `InvalidReaction`
rather than give it, and no reaction is kept under it. The message leaves the identifier out, since it may reach a
log. Without Security, `NoCurrentMember` makes everyone a visitor.

## The rate limit

`react()` and `withdraw()` are held to the limiter per member, once the access has answered. The component says it in
its live region; the plain form answers `429` with `Retry-After`.
