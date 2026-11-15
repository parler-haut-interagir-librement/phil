# Names and Role Provisioning

The Names and Role Provisioning Service lists the members of a context — their identifier, roles, status, and what the
platform shares of their names — so that a tool can prepare groups, dashboards or rosters before each member launches.

## On the tool

```php
use Phil\Lti\Service\Nrps\NrpsClient;

$nrps = new NrpsClient(new ServiceCall($client, $tokens));

foreach ($nrps->members($platform, $launch->message->nrps, role: 'Learner', limit: 100) as $member) {
    $member->userId;        // the `sub` of the member's launches
    $member->roles->isLearner();
    $member->status;        // Active, Inactive or Deleted
}
```

`members()` reads page after page, the next found in the `Link` header of each answer, and stops with a `ServiceFailed`
past 100 pages; `page()` reads one page and gives its `next` and its `differences`, for a tool that reads larger contexts
in steps or keeps up with the changes. Each page holds 1,000 members at most. The token asks for the scope
`MEMBERSHIP_READ_ONLY`.

## On the platform

```php
use Phil\Lti\Platform\Nrps\NrpsService;

$page = new NrpsService($roster)->page($token, $contextId, role: $role, limit: $limit, offset: $offset);
```

`page()` checks the scope, asks the `Roster` whether the tool is placed in the context, and gives the context, up to
`limit` members — 100 by default, 1,000 at most — and the offset of the next page, null on the last. The application
answers the members as JSON of the media type `application/vnd.ims.lti-nrps.v2.membershipcontainer+json`, with a
`Link` header of relation `next` built from the offset.

The `Roster` is the application's: it knows its contexts, which tools are placed in each, and their members. It shares
with a tool only what the tool needs: the identifier and the roles always, the names and the email when the tool is
trusted with them.
