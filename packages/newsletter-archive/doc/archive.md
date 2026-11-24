# The archive

Nothing enters the archive on its own. A campaign is archived when an editor says so, and only once it is sent:
`Archive` refuses a draft, a scheduled campaign or a campaign being sent with `NotArchivable`.

## Publishing and unpublishing

```php
$issue = $archive->publish($campaignId, ['events', 'game-night']);
$archive->unpublish($campaignId);
```

`publish()` renders the web version of the campaign, freezes it, and lists the issue in the archive, at its slug.
Publishing an issue again renders it again — after a fix to a template, say —, keeps its slug and the instant it was
first published, and keeps its tags unless others are given.

`unpublish()` takes the issue out of the archive: it leaves the list, its page answers 404, and its link, if it had
one, is revoked. The row stays, so that publishing it again gives it the same slug.

## Sharing by a link

```php
$token = $archive->share($campaignId);
$url = $urls->link($token);   // IssueUrls: https://example.com/newsletter/archive/shared/<token>
$archive->revokeLink($campaignId);
```

`share()` gives an issue a link, whether it is published or not: an unpublished issue shared by a link is read by
whoever holds the link and by nobody else. The token is 32 random bytes, 43 characters of Base64 for addresses; the
archive keeps its SHA-256 alone and gives the token once. Sharing again replaces the link: the one before stops
working. `revokeLink()` revokes it.

## Tags

A tag is a slug of at most 64 characters: lowercase letters and digits, in words joined by single hyphens. An issue is
filed under 16 tags at most; they are kept distinct and sorted. `retag()` replaces them, `[]` removes them all.

## Slugs

The slug of an issue comes from its subject, through the slugger of the [configuration](configuration.md), and stays
once given. Two issues of a site with the same subject are set apart by `-2`, `-3`… up to `-20`; past that, an issue
takes the first twelve characters of the SHA-256 of its campaign's identifier. A subject without a letter or a digit
gives `issue`.

## From the command line

```sh
bin/console phil:newsletter-archive publish <campaign-id> --tag=events --tag=game-night
bin/console phil:newsletter-archive unpublish <campaign-id>
bin/console phil:newsletter-archive share <campaign-id>
bin/console phil:newsletter-archive revoke <campaign-id>
bin/console phil:newsletter-archive retag <campaign-id> --tag=news
```

`publish` prints the address of the issue, `share` the address of the new link, once. The command is registered when
`symfony/console` is installed; it ends with `1` when the archive refuses the campaign or a tag, `2` for an unknown
action.

## The latest issues, for a feed

`IssueFeed::latest($site, $limit, $listKey, $tag)` gives the latest published issues, newest sent first, as
`FeedItem`: identifier, title, absolute address, summary — the preheader —, web version, date sent and tags. The
archive builds no feed itself; a feed bundle reads it.
