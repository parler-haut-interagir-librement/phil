# Changelog

All notable changes to `phil/route-access-check-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `PhilRouteAccessCheckBundle` and the `phil_route_access_check` configuration: the environments in which the check
  is registered (`dev` and `test` by default, nothing in the others), the baseline file, the public route option, the
  attribute classes read as checks, and the ignored route names and paths.
- `SecurityMapPass`: the firewalls and `access_control` entries read from the firewall map, the firewall
  configurations and the access map SecurityBundle builds, their request matchers read back into plain values and any
  other matcher kept as its service id; the classes of the controller services whose id is not their class.
- `CheckRouteAccessCommand`, `phil:route-access:check`: the formats `text`, `json`, `github` and `junit`, `--baseline`,
  `--generate-baseline`, `--only-problems` and `--strict`; exit code 1 on an untolerated failure, 2 on an unknown
  format or an unreadable baseline.
- The services of the check, the detectors tagged `phil_route_access_check.detector` and the formats tagged
  `phil_route_access_check.formatter`, both by autoconfiguration.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
