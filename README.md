# Base3Ilias

**Base3Ilias** is a component that integrates the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) into the [ILIAS Learning Management System](https://www.ilias.de). It serves as a bridge between the two systems, enabling seamless interoperability and leveraging the strengths of both platforms.

## Overview

This component merges the capabilities of the BASE3 framework with the extensibility of the ILIAS LMS. It allows developers to build advanced, modular, and service-oriented extensions for ILIAS using the structure and features provided by BASE3.

## Installation

Base3Ilias has two roles:

- it is a reusable BASE3 module identified by `base3.json`
- it can provide the native ILIAS component wrapper for private BASE3 installations

The BASE3 module identity is independent from the physical installation path.

At runtime, module discovery uses the physical location of the installed `Base3Ilias` package. Modules under ILIAS `components/` are discovered across that complete directory tree; packages embedded outside it are discovered from their shared module directory. This works even when a UIHook starts the runtime before the adapter plugin initializes. The runtime sets `DIR_BASE3` and `DIR_PLUGIN` from the discovered installation, without requiring a host-specific discovery override.

### Private BASE3 ILIAS installation

Clone this repository directly as the ILIAS component:

```text
components/Base3/Base3Ilias/
├── Base3Ilias.php
├── base3.json
├── src/
└── ...
```

The `Base3Ilias.php` file is only the `Base3` vendor component wrapper. The reusable integration logic stays inside the Base3Ilias module.

### Qualitus product installation

Qualitus installations use `Qualitus/Base3Core` as the native ILIAS component and include this repository as a module below `lib/`:

```text
components/Qualitus/Base3Core/
├── Base3Core.php
├── resources/
└── lib/
    ├── Base3Framework/
    ├── Base3Ilias/
    └── ...
```

In this layout the nested `Base3Ilias.php` wrapper is not an ILIAS component because it is not a direct child of an ILIAS vendor directory. `Qualitus\Base3Core` delegates to the same Base3Ilias component bootstrap.

Only one native wrapper must be installed in a concrete ILIAS installation. Do not install both `components/Base3/Base3Ilias` and `components/Qualitus/Base3Core` with another copy of Base3Ilias at the same time.

## Transitional ILIAS 9 module deployment

ILIAS 9 temporarily loads BASE3 modules from
`Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Base3IliasAdapter/lib/`.
It does not provide the ILIAS Component Revision `ILIAS\Component\Resource\PublicAsset`
interface. The module class map must be able to include `src/Ilias/ComponentBootstrap.php`
without requiring that interface; the component's asset contributions are loaded
only when the native component bootstrap actually runs on ILIAS 10+.

The native component bootstrap now retains asset contributions when given
an ordinary array as well as when given an `ArrayAccess` object.

The ILIAS 9 `IAssetResolver` returns direct module asset URLs instead of using
ILIAS 10+ asset publishing. For a module installed under the adapter, the
modular chatbot stylesheet is expected at:

`./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/Base3IliasAdapter/lib/ClientStack/assets/modularchatbot/styles/chatbot.css`

When the chatbot HTML is present but unstyled, inspect the CSS and JS requests
in the browser network panel first. A missing stylesheet is not evidence of a
Component Revision deployment failure on ILIAS 9, because native component
asset deployment is not used there.

This ILIAS 9 path is a transitional deployment and not a long-term supported
installation mode. No additional ILIAS 9 asset publisher or alternate class map
has been introduced.

## Requirements

- ILIAS version >= 10.0
- PHP >= 8.2
- BASE3 Framework
- ResourceFoundation

## Purpose

The main goal of **Base3Ilias** is to allow developers to:

- Use modern, modular PHP architecture inside ILIAS plugins
- Share core logic between ILIAS and non-ILIAS projects using BASE3
- Speed up development and maintain consistency across services
- Usage of already developed BASE3 Plugins like AI tools, chatbot, agent system, reporting, crm, different api connectors and more
- Expose ILIAS Resource Storage Service resources through the ResourceFoundation file-storage contracts

## ILIAS database adapter

`Base3IliasDatabase` uses the existing ILIAS database connection. It requires the ILIAS
`ilDBPdoInterface`, which provides `getLastInsertId()` without an additional SQL query.
After each `nonQuery()` the adapter stores the affected-row count and the native PDO insert ID.
On MySQL/MariaDB, statements without an insert ID report `0`. This avoids the former
`SELECT LAST_INSERT_ID(0)` and `SELECT LAST_INSERT_ID()` statements and does not change
the shared transaction connection.

## Production PHP error reporting

`Base3IliasRuntime` keeps `display_errors` disabled when `DEBUG=0`, but leaves fatal
PHP errors enabled in `error_reporting()` and enables `log_errors`. This ensures
uncaught exceptions remain available in the web server / PHP-FPM error log while
the response does not expose stack traces to clients. `DEBUG=1` retains full
PHP error reporting. The log destination and retention are controlled by the
hosting PHP configuration, not by Base3Ilias.

## ILIAS Resource Storage adapter

Base3Ilias binds `ResourceFoundation\Api\IFileStorageFactory` to an ILIAS-specific factory. The factory opens an existing ILIAS Resource Storage Service area and returns it as a normal `IFileStorage`.

The adapter supports three modes:

- `single_file`: one IRSS resource identified by a RID
- `collection`: one IRSS resource collection identified by an RCID, exposed as a flat file storage
- `container`: one IRSS container resource identified by a RID, exposed with directory structure

The identifier lifecycle stays with the calling domain object. Base3Ilias does not persist RID or RCID values for consumers. Collection member resources created through `write()` or `copy()` are managed internally by the collection storage implementation. The adapter also implements the `IFileStorage` `copy()` and `move()` operations according to the capabilities of each IRSS mode.

The concrete `Base3IliasFileStorageStakeholder` also supports the temporary
ILIAS 9 IRSS stakeholder contract. ILIAS 9 requires a parameterless constructor
but its abstract stakeholder does not provide one or initialize the owner fields.
Base3Ilias initializes these fields to the current ILIAS user ID if available,
otherwise to `SYSTEM_USER_ID` (or `6` when that constant is unavailable). This
change is limited to the stakeholder; the remaining IRSS storage operations have
not been certified against ILIAS 9.

See [ILIAS Resource Storage adapter](docs/ilias-resource-storage.md) for the operation mapping, ownership rules, and compatibility notes.

## License

This project is open-source and available under the GPL 3.0 License.

## Author

Developed and maintained by **Daniel Dahme**  
GitHub: [@ddbase3](https://github.com/ddbase3)

---

Feel free to contribute or report issues via GitHub!

## Documentation

- [Frequently Asked Questions](docs/faq.md)
- [Privacy and data processing](PRIVACY.md)
- [ILIAS Resource Storage adapter](docs/ilias-resource-storage.md)


## BASE3 groups and feature audiences

The ILIAS-hosted Groups and Features administration surfaces use repeatable
user/role/criterion or group/user/criterion assignments. Discoverable code groups
and host-created groups share one effective group view and one criterion evaluator.
All persistable changes remain in ILIAS-backed settings (not the BASE3 core).
See [authorization-audiences.md](docs/authorization-audiences.md) for storage,
access restrictions, versioned migrations, and the `development` navigation rule.


### BASE3 group, feature and developer audiences

ILIAS user and role assignments plus discoverable `IUserCriterion`s share one
audience evaluator for groups and features. A feature can grant access to BASE3
groups, exact ILIAS users, native ILIAS roles and criteria (OR-composed). Native
ILIAS permissions remain authoritative for host resources.

Code-owned group criteria are immutable membership; administrators may add
criteria but cannot remove code-owned members from the UI. Group and Feature
administration requires both native `system/admin` and BASE3 `development`
membership, including for direct HTTP requests. The optional `QualitusIlias`
project plugin owns this project's bootstrap `development` group. Configure a
exact initial ILIAS login name (or a verified, protected complete email address)
in that plugin **before** restricting the UI.

## Shared Groups and Features interfaces

The ILIAS admin navigation now points to the reusable `ClientStack` displays `authorizationgroupsdisplay` and `authorizationfeaturesdisplay`. `Base3IliasAudienceAdministrationAdapter` keeps the native `system/admin` and BASE3 `development` safety boundaries and delegates unchanged to the existing `Base3IliasGroupConfiguration`, `Base3IliasFeatureConfiguration` and `Base3IliasAudienceEditor`. All stored group and feature keys are unchanged. Native ILIAS RBAC is not modified.
