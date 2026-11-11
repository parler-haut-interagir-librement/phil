# Reference

## Classes

| Class | Role |
|---|---|
| `Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle` | The bundle: the configuration, the services, the compiler pass; `DETECTOR_TAG`, `FORMATTER_TAG`, `SECURITY_MAP`, `CONTROLLER_CLASSES` |
| `Phil\RouteAccessCheckBundle\Infrastructure\DependencyInjection\SecurityMapPass` | Reads the firewalls, the `access_control` entries and the controller classes into parameters |
| `Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand` | `phil:route-access:check`; `DEFAULT_BASELINE` |

## Services

| Id | Class |
|---|---|
| `Phil\RouteAccessCheck\RouteAccessChecker` | The check |
| `Phil\RouteAccessCheck\RouteReader`, `Phil\RouteAccessCheck\ControllerLocator`, `Phil\RouteAccessCheck\IgnoreList` | What it reads routes with, and leaves aside |
| `Phil\RouteAccessCheck\Security\SecurityMap` | The security configuration |
| `Phil\RouteAccessCheck\Detector\SecurityAttributeDetector`, `PublicRouteDetector`, `SecurityConfigDetector` | The detectors, tagged |
| `Phil\RouteAccessCheck\Format\TextFormatter`, `JsonFormatter`, `GitHubFormatter`, `JunitFormatter` | The formats, tagged |
| `Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand` | The command, tagged `console.command` |

All of them exist only in the environments of `phil_route_access_check.environments`.

## Tags

| Tag | On | Autoconfigured from |
|---|---|---|
| `phil_route_access_check.detector` | the detectors the check asks | `Phil\RouteAccessCheck\Contract\AccessRuleDetector` |
| `phil_route_access_check.formatter` | the formats of `--format` | `Phil\RouteAccessCheck\Contract\ReportFormatter` |

## Parameters

| Parameter | Value |
|---|---|
| `phil_route_access_check.security_map` | The firewalls and `access_control` entries, as `SecurityMap::fromArray()` takes them |
| `phil_route_access_check.controller_classes` | The class of each controller service whose id is not its class |
