# phil/shifts-bundle

**Volunteer shifts in Symfony: rosters and a swap board stored with Doctrine, volunteer and coordinator pages, a shift form, Twig components, Messenger.**

phil/shifts-bundle brings phil/shifts to a Symfony application. It stores the **shifts**, their **rosters** and the
offers of the **swap board** through Doctrine DBAL, its tables added to the schema the ORM generates, so that
`doctrine:migrations:diff` writes their migration. A write is made over the revision read: two requests that change the
same roster at once never overwrite each other, and a place passed through the swap board is written with its offer in
one transaction.

The **volunteers' pages** list the shifts to come, critical ones lacking volunteers first, and let a volunteer sign up,
join the reserve, say they are interested, withdraw, offer their place and take one offered. A volunteer **never holds
two fixed shifts that overlap**, with the rest the configuration asks between them. The **coordinators' pages** plan
and correct a shift through a **Symfony form** in the time zone of the site, assign and take off volunteers in every
mode, allocate the places among the interested — through **Messenger** when the application has a bus — and approve or
refuse the swaps that wait for them. Every page is a plain Twig form that works without JavaScript, with a CSRF token
and the classes of the PHIL UX kit.

The **Twig components** `<twig:PhilShifts:ShiftCard>` and `<twig:PhilShifts:MyShifts>` show a shift with the buttons of
the person signed in, and that person's shifts with the hours they served, on any page of the application. **Voters**
decide who volunteers and who coordinates. The events of each change, which carry identifiers only, go to the
application's event dispatcher once stored. `phil:shifts:import` imports a plan in the format `phil-shifts/1`.

Two optional rules guard who joins a shift. A volunteer who is a **minor** on the day of a shift, by the birth date the
application gives, joins once the contact of a parent or guardian is recorded with their consent. A coordinator records
the **unannounced absences**; past a configured threshold, a volunteer no longer signs up by themselves until an
organiser lifts the block.

A volunteer's shifts, offers, guardian's contact and absences are **exported and erased** through the contracts of
phil/privacy-bundle. A Flex recipe
registers the bundle, its configuration and its routes.

## Status

| Part | State |
|---|---|
| The tables, the schema listener, the repositories with their revisions, the transaction of a swap | Implemented and tested |
| The volunteers' pages, the conflicts between fixed shifts, the swap board | Implemented and tested |
| The coordinators' pages, the form of a shift, the allocation through Messenger | Implemented and tested |
| The components `PhilShifts:ShiftCard` and `PhilShifts:MyShifts` | Implemented and tested |
| The voters, the CSRF tokens, the events, the command `phil:shifts:import` | Implemented and tested |
| The guardians of volunteers who are minors, the unannounced absences and their threshold | Implemented and tested |
| The export and the erasure of phil/privacy-bundle | Implemented and tested |
| The Flex recipe | Prepared in `recipe/0.1` |
| Reminders before a shift, a personal iCalendar feed | Not written |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the recipe, Security, the migration and a first shift. |
| [Configuration](doc/configuration.md) | Every option, the roles, and what the bundle registers. |
| [Pages and components](doc/pages-and-components.md) | The volunteers' and coordinators' pages, the form, the components, accessibility. |
| [Minors and absences](doc/minors-and-absences.md) | The consent of a guardian for a volunteer who is a minor, the unannounced absences and their threshold. |
| [Storage and privacy](doc/storage-and-privacy.md) | The tables, the revisions, the migration, the events, Messenger, and a volunteer's data. |
| [Extension points](doc/extension-points.md) | The services an application replaces, and what is stable. |
| [Testing](doc/testing.md) | The tests and the application they run in. |
| [Reference](doc/reference.md) | Every public type and route, with its role. |

## Quick start

```sh
composer require phil/shifts-bundle
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console phil:shifts:import plan.json
```

```twig
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
<twig:PhilShifts:MyShifts limit="5" />
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, Symfony 8.2 with FrameworkBundle, Security, Forms and Twig, and Doctrine DBAL 4.
The ORM, Messenger, Console, the Twig components and phil/privacy-bundle are used when they are installed.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
