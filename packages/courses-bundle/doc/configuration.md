# Configuration

The bundle's configuration lives under `phil_courses`. Every option has a default; an application names the content
origin and the home page of the cmi5 accounts.

```yaml
# config/packages/phil_courses.yaml
phil_courses:
    connection: default
    timezone: America/Toronto
    current_learner: null
    layout: '@PhilCourses/layout.html.twig'
    kit_subdirectory: null
    self_enrollment: false
    locked_activities: show
    storage:
        directory: '%kernel.project_dir%/var/phil_courses/content'
        service: null
    content:
        base_url: 'https://content.example.org'
        application_origin: null
        allow_localhost: false
    import:
        language: null
        xapi_reader: null
        limits:
            archive_bytes: 536870912
            entries: 10000
            entry_bytes: 268435456
            total_bytes: 2147483648
            ratio: 200
            manifest_bytes: 4194304
            depth: 16
            items: 5000
            manifest_nodes: 500000
            manifest_depth: 64
    cmi5:
        actor_home_page: 'https://www.example.org'
        session_ttl: 14400
        secret: '%kernel.secret%'
        allowed_origins: []
        lrs: null
        remote_lrs:
            enabled: false
            endpoint: null
            username: null
            password: null
            version: 1.0.3
            timeout: 5.0
            max_duration: 10.0
    scorm:
        enabled: true
        token_ttl: 14400
        secret: '%kernel.secret%'
    training:
        enabled: true
        reminder_days: 7
        renewal_grace_days: 30
        max_members: 10000
        run_at: '06:00'
        schedule: true
        manager_role: ROLE_COURSES_MANAGER
        notifier: null
        keep_transcripts: false
    admin:
        role: ROLE_COURSES_TEACHER
    audit:
        enabled: true
        segment: courses
```

| Option | Default | What it does |
|---|---|---|
| `connection` | `default` | The DBAL connection of the tables. |
| `timezone` | `UTC` | The site's time zone: a release a number of days after the enrollment opens at midnight there. |
| `current_learner` | `null` | The `CurrentLearner` service; Symfony Security's user identifier when null, refused when it holds an `@`. |
| `layout` | `@PhilCourses/layout.html.twig` | The template the pages extend, with the blocks `title` and `main`. |
| `kit_subdirectory` | `null` | Where the recipes of the kit are under the components directory; the subdirectory of `ux_toolkit` when null. |
| `self_enrollment` | `false` | Whether a learner signed in enrolls from the course's page; when false, the application enrolls them. |
| `locked_activities` | `show` | `show` lists the activities a learner cannot take yet with what is left to do; `hide` leaves them out of the outline. |
| `storage.directory` | `%kernel.project_dir%/var/phil_courses/content` | The directory of the imported files, outside the public directory. |
| `storage.service` | `null` | A `ContentStorage` service of the application, instead of the directory. |
| `content.base_url` | `null` | The base URL of the content origin; null leaves the imported content without a place to launch from. |
| `content.application_origin` | `null` | The origin of the site that frames the content, such as `https://www.example.org`: the only origin that may frame the content and the frame of the SCORM run time, and the only one the frame talks to. The origin of `cmi5.actor_home_page` when null; with neither, the content may be framed by nobody. |
| `content.allow_localhost` | `false` | Whether the content origin and the home page may be on `localhost`, in development. |
| `import.language` | `null` | The language of the titles read from a cmi5 course structure, such as `fr`; its first title when null. |
| `import.xapi_reader` | `null` | An `XapiPackageReader` service that reads the `tincan.xml` of xAPI packages; such packages are refused when null. See [xAPI activities](xapi-activities.md#importing-an-xapi-package). |
| `import.limits.archive_bytes` | 512 MiB | The size of the archive. |
| `import.limits.entries` | 10 000 | How many entries the archive holds. |
| `import.limits.entry_bytes` | 256 MiB | The size of one file, uncompressed. |
| `import.limits.total_bytes` | 2 GiB | The size of every file, uncompressed. |
| `import.limits.ratio` | 200 | How many times a file larger than a megabyte may be compressed. |
| `import.limits.manifest_bytes` | 4 MiB | The size of `imsmanifest.xml` or `cmi5.xml`. |
| `import.limits.depth` | 16 | How deep the items or blocks nest, 64 at most. |
| `import.limits.items` | 5 000 | How many items, resources, blocks or units a manifest describes. |
| `import.limits.manifest_nodes` | 500 000 | How many XML nodes a manifest holds, counted before it is loaded. |
| `import.limits.manifest_depth` | 64 | How deep the XML elements of a manifest nest, checked before it is loaded. |
| `cmi5.actor_home_page` | `null` | The site's address: the home page of the accounts that name the learners, and the root of the activities' IRIs. Null leaves cmi5 launches refused. |
| `cmi5.session_ttl` | 14 400 | How many seconds the token of a session holds, from 60 to 86 400. |
| `cmi5.secret` | `%kernel.secret%` | The secret the tokens are derived from. |
| `cmi5.allowed_origins` | `[]` | The origins, besides the content origin, whose units may call the xAPI endpoint: those of units on absolute URLs. |
| `cmi5.lrs` | `null` | A `LearningRecordStore` service of the application that keeps the statements; the store inside the bundle, or the remote store, when null. Refused with `remote_lrs` enabled. |
| `cmi5.remote_lrs.enabled` | `false` | Sends the statements to a remote xAPI learning record store through phil/safe-http, which must be installed; enabled as soon as another key of `remote_lrs` is given. |
| `cmi5.remote_lrs.endpoint` | `null` | The xAPI endpoint of the store, an `https` address of a public host without a query, such as `https://lrs.example.org/xapi/`; required when enabled. |
| `cmi5.remote_lrs.username` | `null` | The user name of HTTP Basic authentication — the key the store gives —, without `:`; none when null. |
| `cmi5.remote_lrs.password` | `null` | The password of HTTP Basic authentication — the secret the store gives —, read from an environment variable; given with the user name, or not at all. |
| `cmi5.remote_lrs.version` | `1.0.3` | The xAPI version the store speaks, `1.0.3` or `2.0.0`, sent as `X-Experience-API-Version`. |
| `cmi5.remote_lrs.timeout` | 5.0 | How many seconds the store may stay silent, from 0.1 to 60. |
| `cmi5.remote_lrs.max_duration` | 10.0 | How many seconds one statement may take to send, in all, from 0.1 to 120. |
| `scorm.enabled` | `true` | Runs SCORM content with the run-time API, its attempts and tracking data, as [SCORM run time](scorm-runtime.md) explains; when false, the learner marks a SCORM activity done. |
| `scorm.token_ttl` | 14 400 | How many seconds an attempt token holds after the last commit, from 300 to 86 400. |
| `scorm.secret` | `%kernel.secret%` | The secret the attempt tokens are signed with. |
| `training.enabled` | `true` | The mandatory training — assignments, reminders, recertifications, credits and transcripts —, its pages, commands, schedule and privacy module. |
| `training.reminder_days` | 7 | How many days before the due moment a course is due soon and its first reminder sent, 1 to 90. |
| `training.renewal_grace_days` | 30 | How many days after its renewal a recertification is due again, 1 to 365. |
| `training.max_members` | 10 000 | How many members of a group or unit an assignment reaches. |
| `training.run_at` | `06:00` | The time of the daily run of the schedule, `HH:MM` in the site's time zone. |
| `training.schedule` | `true` | Whether the schedule `phil_courses_training` runs every day, with Symfony Scheduler and Messenger installed. |
| `training.manager_role` | `ROLE_COURSES_MANAGER` | The role a manager holds to see the training of every learner and their transcripts. |
| `training.notifier` | `null` | A `TrainingNotifier` service; the notifications of phil/notifications-bundle when null and that bundle is registered, none otherwise. |
| `training.keep_transcripts` | `false` | Whether the transcripts survive an erasure request, under a legal obligation to keep them. |
| `admin.role` | `ROLE_COURSES_TEACHER` | The role a teacher holds to see and decide the second chances on the administration page. |
| `audit.enabled` | `true` | Writes the second chances — asked for, granted, refused, waived — to the trail of phil/audit-bundle, when that bundle is registered. |
| `audit.segment` | `courses` | The segment of the trail. |

The bundle declares the CSRF token `phil_courses` stateless to FrameworkBundle: the forms of the pages and the commits
of the SCORM sessions work without a session. With phil/notifications-bundle registered, it declares the notification
types of the mandatory training, `courses.training.*`, which an application changes by declaring a type of the same
key.
