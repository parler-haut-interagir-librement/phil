# Privacy

## Hidden followers

Each member chooses whether the list of their followers is shown: `Followers::hideFollowers($member, true)`, or the form
of `@PhilFollow/visibility.html.twig`. A member who never chose follows `followers.hidden_by_default`.

The choice applies to the member subject, `member_subject_type`. When it is hidden, `Followers::followersOf()` returns
an empty list marked hidden, and `Followers::count()` null, to everyone but the member: neither the names nor the count
leave the bundle, through its services or its templates. The other subjects — a thread, a listing — always show their
followers.

## The export and the erasure

With phil/privacy-bundle, `FollowPrivacy` takes part in a member's requests as the module `follows`, through the
contracts `PersonalDataExporter` and `PersonalDataEraser`.

The export holds what belongs to the member:

| Key | What it lists |
|---|---|
| `follows` | Each subject they follow, its level, its preferences by event and since when |
| `requests_sent` | The requests they sent that still wait |
| `requests_received` | The requests waiting for their answer |
| `mutual_relationships` | The other member of each relationship, and since when |
| `followers_hidden` | Their choice, when they made one |

Who follows the member belongs to each follower, and is left out.

The erasure deletes the member's follows and the follows of their member subject, the requests they sent or have to
answer, their relationships, the activities they did, the claims and marks of their deliveries and their choice, and
takes them out of the mentions of the other activities. Every store is reported `deleted`. Run twice, the erasure
reports the same.

## What travels

A `FanOutActivity` message carries the identifier of an activity and of a member, nothing else; the worker reads the
rest again. The notifications carry identifiers only, as phil/notifications-bundle requires.
