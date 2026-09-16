# Privacy and Data Processing in Base3Ilias

> This document describes data processing that is implemented or directly enabled by the reviewed Base3Ilias component. It is a technical privacy document, not a legal privacy notice. The final privacy assessment depends on how the component is deployed and which administrative functions are exposed.

## 1. Scope

Base3Ilias integrates the BASE3 runtime into ILIAS. Its privacy-relevant role is mainly to bridge existing ILIAS runtime data and services into BASE3 contracts.

The component itself provides adapters for authentication, sessions, users and RBAC, database access, configuration, state, logging, request handling, assets, routing, and administration or diagnostic views.

This document is limited to Base3Ilias itself. It does not describe data processing performed by other BASE3 plugins merely because they can run in the same runtime.

## 2. General processing model

Base3Ilias does not maintain an independent user directory. The authoritative user, session, repository and RBAC data remain in ILIAS.

The component makes selected ILIAS services available to BASE3 and may therefore process or expose data that already exists in the host system.

Main technical paths are:

| Area | Base3Ilias behavior | Personal data possible? | Persistence by Base3Ilias |
|---|---|---:|---|
| Authentication | Reads the current ILIAS session user ID | Yes | No separate authentication store |
| Session adapter | Reads and writes `base3_`-prefixed values in the active ILIAS session | Yes | Through the ILIAS session backend |
| Usermanager | Reads user identity, role and permission data from ILIAS | Yes | No separate user directory |
| Request runtime | Exposes request data through BASE3 `IRequest` | Yes | Normally request-local only |
| Standalone request preservation | Temporarily snapshots the incoming request across ILIAS bootstrap | Yes | In process memory only |
| Configuration | Uses BASE3 database-backed configuration on the ILIAS database | Possibly | Yes |
| State store | Stores arbitrary JSON-compatible runtime state in `base3_statestore` | Possibly | Yes |
| Logging | Forwards BASE3 log lines and context to the ILIAS logger or PHP error log | Yes | According to the active logging backend |
| Admin and debug displays | Reads and displays system, request, user, RBAC, object and log information | Yes | Display itself does not add a separate store |
| Artifacts and cache directories | Creates client-local BASE3 runtime directories | Possibly | Files depend on runtime use |
| Asset deployment | Publishes static plugin assets | Normally no | Static assets only |

## 3. Authentication data

`Base3IliasAuth` reads the current user ID from the active ILIAS authentication session.

The anonymous ILIAS account is treated as unauthenticated. The component does not implement a separate password store or independent login database.

The authentication adapter therefore processes the ILIAS user identifier required to establish the current BASE3 identity.

## 4. Session data

`Base3IliasSession` uses the active ILIAS PHP session as the BASE3 session backend.

BASE3-specific values are stored with the prefix:

```text
base3_
```

The adapter can:

- read the current PHP session ID
- read BASE3-prefixed session values
- write BASE3-prefixed session values
- test for their existence
- remove individual BASE3-prefixed values
- clear all BASE3-prefixed values through `destroy()`

It does not destroy the complete ILIAS session and does not create a separate Base3Ilias session cookie.

The privacy impact of individual `base3_` session values depends on what consuming code stores under those keys.

## 5. User and RBAC data

`Base3IliasUsermanager` exposes ILIAS identity and authorization data through the BASE3 usermanager contract.

For the current user or a specifically requested user, the adapter can process:

- numeric ILIAS user ID
- login name
- title, first name and last name used to construct the display name
- email address
- language
- effective role IDs and role titles
- whether the user holds the ILIAS system administrator role

The adapter also exposes ILIAS operation definitions and can evaluate object-specific permissions against an ILIAS repository `ref_id`.

### 5.1 Derived administrator role

For users holding the native ILIAS system administrator role, the adapter derives BASE3 administrator semantics such as:

```text
role: admin
permission: system/admin
permission: entry/admin
```

This does not create a second authorization database. It is a runtime mapping of the ILIAS RBAC state.

### 5.2 Read-only behavior

The reviewed adapter does not write user or RBAC changes back to ILIAS. Its methods for user registration, password changes, role assignments, role revocations and permission mutations return `false`.

### 5.3 Groups

ILIAS group membership is not mapped through this adapter. `getGroups()` and `getAllGroups()` return empty arrays.

## 6. Request data

For normal embedded execution, Base3Ilias creates the BASE3 request service from the active PHP request globals.

Depending on the request, this can include:

- GET parameters
- POST parameters
- cookies
- session values
- server and HTTP metadata
- uploaded-file metadata
- JSON request data

Request content can contain personal data, credentials, access tokens, free text or uploaded-file metadata depending on the endpoint being called.

## 7. Standalone request preservation

Some public standalone BASE3 endpoints need the original request to survive ILIAS initialization. For that case, `Base3IliasRuntime::bootStandaloneAndDispatch(true)` captures a request snapshot before ILIAS is booted.

The snapshot contains:

```text
GET
POST
REQUEST
COOKIE
SESSION
SERVER
FILES
raw php://input body
```

After ILIAS initialization, the snapshot is restored and used to construct an `IRequest` implementation that can still expose the original JSON body and request metadata.

The reviewed code keeps this snapshot in static process memory for the request. It is cleared from the runtime's snapshot reference in a `finally` block after standalone dispatch. Base3Ilias does not write the snapshot to a database or file.

Because the snapshot can contain credentials, cookies, session data or raw request bodies, it should be treated as sensitive while the request is executing.

## 8. HTTP and server metadata

Request and diagnostic processing can expose technical metadata such as:

- request method
- request URI
- query string
- host name
- HTTPS status
- remote IP address
- user agent
- referrer
- accepted languages and content types
- document and script paths
- controller and call-history information

Some of these values can be personal data or can contain identifiers supplied by users.

## 9. Request debug display and secret masking

`IliasRequestDebugDisplay` shows controller state, request metadata, GET parameters, POST parameters and selected server variables.

For GET and POST parameter tables, it recursively masks values when the parameter key contains one of these strings:

```text
pass
pwd
token
secret
csrf
```

The replacement value is:

```text
************
```

This is a useful display-level safeguard but not a complete sanitization boundary.

In particular, the same display also renders `REQUEST_URI` and `QUERY_STRING` as raw values. A secret placed directly into the URL may therefore still be visible even when the parsed GET table masks the corresponding parameter.

The controller call history and other server values are also not passed through the GET or POST secret-key filter.

## 10. ILIAS configuration display

`IliasConfigAdminDisplay` reads selected values from the ILIAS configuration.

These values can include operational information such as:

- HTTP and filesystem paths
- client data directories
- logging paths
- mail and tool configuration
- HTTPS detection settings
- distribution-specific paths

The ILIAS setup password is explicitly marked as sensitive and is rendered as a fixed mask rather than in clear text.

This does not mean every other configuration field is non-sensitive. Paths, hostnames and operational settings can still be security-relevant and should only be visible to authorized administrators.

## 11. User debug display

`IliasUserDebugDisplay` can inspect the current user or another user selected by ID or login.

The reviewed implementation can display:

- user ID and login
- title, first name, last name and full name
- email address
- language
- active status
- authentication mode
- external account identifier
- first and last login timestamps
- creation and update dates
- approval and agreement dates
- inactivation date
- login-attempt count
- password-policy-reset status
- profile-completeness status
- self-registration status
- time-limit information
- selected user preferences
- assigned roles and global roles

This is clearly personal and security-relevant information.

## 12. Permission debug display

`IliasPermissionDebugDisplay` can evaluate a selected user and repository object. It processes user identity data, assigned and global role IDs, parent roles, operations granted on the target object, operation definitions and access-check results.

It can also display the selected user's login, first name, last name and email address.

## 13. Object debug display

`IliasObjectDebugDisplay` can inspect an ILIAS repository `ref_id` and display:

- repository reference ID
- object ID
- object type
- title
- repository path
- up to 100 child objects
- child titles and descriptions

Repository titles or descriptions may themselves contain personal or confidential information depending on the installation.

## 14. Dashboard and system health information

The Base3Ilias administration dashboard and health views expose operational information about the active ILIAS installation and BASE3 integration.

The dashboard also reads the current user's login, name, language, user ID and role information for diagnostic presentation.

Operational diagnostics can disclose system structure even when they do not contain direct personal data.

## 15. Logging

`Base3IliasLogger` forwards BASE3 log events to the native ILIAS logger when available.

The generated line contains:

- BASE3 marker
- log level
- scope
- message
- JSON-encoded context for structured log calls

The logger does not remove personal data, credentials or tokens from the message or context before writing them.

Application code using this logger must therefore avoid placing unnecessary sensitive data in log messages or context.

If the native ILIAS logger is unavailable, Base3Ilias falls back to PHP `error_log()`.

### 15.1 Native ILIAS log viewer

`IliasLogAdminDisplay` can display lines from the configured ILIAS log. Log lines are presented as diagnostic data and are not privacy-filtered by Base3Ilias.

### 15.2 ILIAS error-log viewer

`IliasErrorLogAdminDisplay` can list files from the configured ILIAS error-log directory and return the final part of a selected file.

The implementation:

- limits the list to at most 500 files
- limits one read to at most 1 MiB from the end of the file
- restricts file selection to files inside the configured error-log directory
- does not redact the returned log content

Error logs may contain usernames, IP addresses, request data, stack traces, filesystem paths, object identifiers or other personal and operational information.

## 16. BASE3 log cleanup job

The component contains `Base3LogCleanupJob` for an optional database table named `base3_log`.

When the job is enabled and the table exists, it deletes rows older than the configured retention window.

Current defaults are:

```text
retention: 48 hours
delete batch: 100000 rows
execution window: 02:00 to 04:00
```

Retention and batch size are read from Base3Ilias state keys. The job must be enabled through the job configuration before it runs.

This cleanup job does not control retention of the native ILIAS log or ILIAS error-log files.

## 17. Persistent state

`Base3IliasStateStore` creates and uses the table:

```text
base3_statestore
```

The table stores:

```text
key
JSON-encoded value
updated_at
expires_at
```

The state store accepts arbitrary JSON-compatible values. Base3Ilias does not apply field-level personal-data filtering, encryption or pseudonymization before values are stored.

A value written with no TTL receives no expiration timestamp and remains until it is explicitly overwritten or deleted.

Expired rows are deleted lazily when they are accessed through `get()` or `has()`.

The concrete retention policy therefore depends on how consuming code uses the state store.

## 18. Database-backed configuration

`Base3IliasConfiguration` uses the BASE3 database-backed configuration implementation through the ILIAS database adapter.

It adds defaults for:

- Base3Ilias dispatch endpoint
- internal target value
- resolved base URL

Configuration records can contain personal or sensitive data if administrators or consuming components place such values in the configuration system. Base3Ilias does not automatically classify or redact arbitrary configuration values at the storage boundary.

## 19. Database access

`Base3IliasDatabase` delegates SQL execution to the active ILIAS database service.

It does not copy the complete ILIAS database into a separate store. Queries run against the same database connection managed by ILIAS.

The adapter supports transactions, reads, writes, escaping, affected-row information and insert-ID tracking according to the BASE3 database contract.

Any personal data accessed through this adapter depends on the SQL issued by the consuming service.

## 20. Client-local BASE3 directories

Base3Ilias resolves the active ILIAS client data directory and creates:

```text
<client-data>/base3/
<client-data>/base3/artifacts/
<client-data>/base3/cache/
```

It maps the BASE3 temporary directory to `base3/artifacts` and the BASE3 local-data directory to the client-specific `base3` directory.

Base3Ilias itself does not define the contents written there by other runtime components.

### 20.1 Artifact cleanup

During ILIAS component initialization in CLI mode, Base3Ilias deletes the contents of the `base3/artifacts` directory once per process.

This provides cleanup for that temporary artifact area. It does not clear the `base3/cache` directory and does not establish retention rules for files stored elsewhere.

## 21. Static assets

Base3Ilias publishes static `assets` directories from BASE3 components through the ILIAS public asset mechanism.

The asset resolver can add a short MD5-derived content hash to the public asset URL for cache busting. This hashes the asset file content and is not a user identifier.

## 22. External transfers

The reviewed Base3Ilias source does not implement an external AI provider, analytics service, telemetry collector or generic outbound HTTP client.

Its normal data paths are between the BASE3 runtime and services of the local ILIAS installation.

If another installed component sends data to an external system, that processing belongs to that component and its deployment configuration rather than to Base3Ilias itself.

## 23. Access control for diagnostic views

The diagnostic display classes reviewed here do not contain their own explicit authorization checks before rendering user, request, RBAC, configuration or log information.

They are therefore sensitive endpoints and must only be exposed through surrounding ILIAS or BASE3 administration routing that enforces appropriate authorization.

This is especially important for:

- request debug information
- user debug information
- permission debug information
- ILIAS configuration
- native ILIAS log output
- ILIAS error-log content
- repository object diagnostics

Access control at the routing or administration boundary should be verified as part of deployment testing.

## 24. Retention and deletion summary

Base3Ilias does not define one global retention policy. Retention depends on the data area:

| Data area | Retention behavior in reviewed code |
|---|---|
| Request snapshot | Request-local process memory; snapshot reference cleared after standalone dispatch |
| `base3_` session values | Follows ILIAS session lifetime or explicit removal |
| User and RBAC data | Remains authoritative in ILIAS; Base3Ilias does not maintain a separate user copy |
| `base3_statestore` values | Optional TTL; otherwise no automatic expiration |
| `base3_log` rows | Optional cleanup job, default 48 hours when enabled |
| Native ILIAS logs | Controlled by ILIAS logging configuration, not Base3Ilias cleanup |
| ILIAS error logs | Controlled by ILIAS or system log retention, not Base3Ilias cleanup |
| `base3/artifacts` | Cleared during CLI component initialization |
| `base3/cache` | No cleanup policy implemented by this component |
| Database-backed BASE3 configuration | Persists until changed or removed by the configuration layer |

## 25. Data minimization considerations

The reviewed component already contains several narrow safeguards:

- anonymous users are not returned as authenticated BASE3 users
- usermanager mutation methods are disabled
- the ILIAS setup password is masked in the configuration display
- GET and POST keys containing common secret terms are masked in the request-debug parameter tables
- error-log file selection is constrained to the configured log directory
- error-log reads are size-limited

These safeguards do not replace deployment controls. In particular:

- raw request URI and query-string fields can still expose URL secrets
- log content is not redacted
- user and RBAC debug views intentionally expose personal and authorization data
- state values are stored without generic field-level redaction
- structured log context is written without generic secret filtering

## 26. Production checklist

Before exposing Base3Ilias administration functions in production, verify at least the following:

- diagnostic displays are restricted to the intended administrator population
- direct routing to diagnostic displays cannot bypass authorization
- sensitive values are not placed in URL query strings
- logging configuration avoids unnecessary personal data and credentials
- ILIAS native log and error-log retention is defined
- the optional `base3_log` cleanup job is configured if that table is used
- state keys containing personal or sensitive data have an appropriate TTL or deletion process
- file permissions for the client-specific `base3` data directory are appropriate
- temporary artifacts are not treated as durable storage
- the request-preservation endpoint is exposed only where required

## 27. Relationship to ILIAS privacy controls

Base3Ilias relies on ILIAS as the host system for authentication, sessions, users, RBAC, database connectivity, logging and several operational settings.

Deleting or changing authoritative user data in ILIAS therefore changes what Base3Ilias can read from the host system. Base3Ilias does not implement an independent user-data deletion workflow because it does not own the primary user records.

Independent BASE3 state, configuration, session values, logs or files must still be considered separately where they contain personal data.
