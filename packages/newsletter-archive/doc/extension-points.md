# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Render the web version another way | Implement `Application\Contract\WebVersionRenderer` and alias the interface to your service. What it returns is kept as it is and written unescaped: purify it. |
| Keep the issues elsewhere | Implement `Domain\Contract\ArchiveRepository` and alias the interface to your service. |
| Give the issues other addresses | Implement `Application\Contract\IssueUrls`, used by the command and by `IssueFeed`. |
| Make the slugs another way | Name the service of a `Phil\Slug\Slugger` in `slugger`. |
| Serve several sites | Replace the `CurrentSite` service of `phil/newsletter-bundle`: the archive reads the site of a request from it. |
| Change the pages | Override the templates in `templates/bundles/PhilNewsletterArchiveBundle/archive/`, and the texts of the domain `phil_newsletter_archive`. |
| Add a sharing image | Fill the block `meta` of the layout, from `phil/social-cards` for one. |
| Build a feed of the issues | Read `IssueFeed::latest()`. |
