# Deployment

A policy that blocks what the site needs breaks it. The safe path runs the policy as report-only first, reads the
reports, and enforces it once they are quiet.

## Report-only first

```yaml
phil_browser_security:
    enforce: ~
    report_only: default
```

The browsers apply nothing and report every violation to the [endpoint](reports.md). The reports say which source
each page needs.

## Enforced, with a stricter policy beside it

```yaml
phil_browser_security:
    enforce: default
    report_only: strict
    policies:
        default:
            directives: { default-src: ["'self'"], img-src: ["'self'", 'https:'] }
        strict:
            extends: default
            directives: { img-src: ["'self'", 'https://images.example.com'] }
            strict_dynamic: true
```

The current policy keeps protecting while the stricter one is tried on real traffic. Each header carries its own
reporting directives, and the reports say which policy they come from by their disposition: `enforce` or `report`.

## One route at a time

A route or a controller sends its policy as report-only while the others enforce it:

```php
#[ContentSecurityPolicy(reportOnly: true)]
public function checkout(): Response
```

When it does, the report-only policy of the configuration is not sent on that response: one
`Content-Security-Policy-Report-Only` header at a time.
