# Privacy

The bundle keeps, of a person, the **stays** of their participant identifier: which room, when they arrived and left.
The rooms — a title, an owner, moments — and the recordings' metadata name nobody. The recordings themselves, kept by
the servers, hold the voices and faces of the participants: their retention is the servers'.

With phil/privacy-bundle enabled and `privacy.enabled` on, `ClassroomPrivacy` is the module `virtual_classroom` of
the access and erasure requests. The person is the participant, named by the stable identifier of their account.

- The export gives each stay: `room`, `joined_at`, `left_at`, in ATOM dates.
- The erasure deletes the stays, and reports the table `<prefix>_stay` as deleted. It runs again without harm.

The display name of a participant is never kept by the bundle: it goes to the server in the join link only. The logs
of the bundle and of phil/virtual-classroom hold room identifiers, never participants.
