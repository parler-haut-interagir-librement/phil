# Incidents and maintenances

## The format

`RecordsReader` reads the same data from the configuration and from a JSON file:

```json
{
    "incidents": [
        {
            "id": "database-slow",
            "title": "Slow pages",
            "impact": "degraded",
            "components": ["website"],
            "updates": [
                { "at": "2026-11-12T10:00:00+00:00", "state": "investigating", "message": "Pages load slowly." },
                { "at": "2026-11-12T10:40:00Z", "state": "resolved", "message": "An index was rebuilt." }
            ]
        }
    ],
    "maintenances": [
        {
            "id": "upgrade",
            "title": "Database upgrade",
            "description": "The API answers in read-only mode.",
            "components": ["api"],
            "starts_at": "2026-11-20T02:00:00-05:00",
            "ends_at": "2026-11-20T03:00:00-05:00"
        }
    ]
}
```

| Field | Rule |
|---|---|
| `id` | 1 to 64 lower-case letters, digits, `-` or `_`, starting with a letter or a digit; once per list. |
| `title` | One line of 1 to 160 characters, without control characters. |
| `impact` | `degraded`, `partial_outage` or `major_outage`. |
| `components` | Component ids, each once; an id the page does not know is shown as it is. |
| `updates` | 1 to 100; their order does not matter, the page sorts them. |
| `at`, `starts_at`, `ends_at` | ISO 8601 with seconds and an offset, `Z` or `±hh:mm`; a date the calendar does not have is refused. |
| `state` | `investigating`, `identified`, `monitoring` or `resolved`. |
| `message`, `description` | 1 to 4,000 characters; line breaks kept, other control characters refused. `description` is optional. |

An incident starts with its oldest update and stands where its newest one says: it is going on until an update says
`resolved`, and is then in the history for `history_days` days. A maintenance is upcoming before `starts_at`, in
progress until `ends_at`, which comes after `starts_at`. A list holds 500 records at most, and a key that is not in the
table is refused. A refusal is an `InvalidStatusRecord` that names the record and the field, never the value.

## Where they are kept

| Option | Store | Read |
|---|---|---|
| `records.service` | A `StatusRecords` of the application: a table, a back office. | Each page. |
| `records.file` | `JsonFileRecords`: a JSON file of 1 MiB at most, written by the operators, a deployment or another machine; no file holds nothing. | Each page. |
| `records.incidents`, `records.maintenances` | `ConfiguredRecords`: the configuration, checked when the container is built. | Once. |

With a file, a new incident shows on the next page and the next snapshot, without a deployment.
