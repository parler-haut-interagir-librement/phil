# The web version

The archive keeps of each issue a web version: HTML for a page, rendered and purified once, when the issue is
published or shared, and kept as it is until it is published again. A page of the archive never renders a campaign.

## Rendered with neutral data

`NeutralWebVersion` renders a campaign as `phil/newsletter-bundle` would send it, without a subscriber:

- a campaign laid out by the house layout gives its body alone — its Markdown compiled by `phil/markup` —, without the
  footer of an email;
- a campaign laid out by a template of the site is rendered by the newsletter's sandbox, with neutral values: the name
  of its list and its site, and, for `unsubscribe_url` and `preferences_url`, a placeholder on the reserved domain
  `subscriber.invalid`; a template whose key no longer exists gives the body alone.

Then, from the body of what was rendered:

- every link to the placeholder is taken out, and the paragraph or item it leaves without text or element with it;
- every element an inline style hides — `display: none`, the preheader of an email — is taken out;
- every image of one pixel or less, by its `width` or `height`, and every image of the placeholder is taken out;
- the placeholder written anywhere else is erased;
- the HTML is purified by `phil/markup` at its widest level, which drops scripts, frames, forms, styles and event
  handlers.

No address is rewritten for click tracking, and no signed link is made: the web version holds no token.

## Privacy

The archive keeps no personal data: the web version is rendered without a subscriber, and an issue names its
campaign, its list and its site, never a person. Erasing a subscriber — `Subscriptions::forget()` of
`phil/newsletter-bundle`, or an erasure request through `phil/privacy-bundle` — leaves the archive as it is, and the
archive has nothing to answer an access request with.

What the campaign itself says is the editor's: a campaign that names a person in its body names them in the archive
too. Unpublishing it takes it out.
