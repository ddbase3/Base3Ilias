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

The adapter exposes ILIAS operation definitions; the separate `Base3IliasAuthorizationService` delegates authorization decisions for native operations and repository `ref_id` values directly to ILIAS `ilRbacSystem`. A new `Base3IliasAuthorizationAdministrationService` allows a correctly authorized operator to inspect and modify native ILIAS role grants through `ilRbacReview` and `ilRbacAdmin` using host database transactions.

### 5.1 Derived administrator role

For users holding the native ILIAS system administrator role, the adapter derives BASE3 administrator semantics such as:

```text
role: admin
permission: system/admin
permission: entry/admin
```

This does not create a second authorization database. It is a runtime mapping of the ILIAS RBAC state.

### 5.2 Read-only behavior

The usermanager adapter does not write native ILIAS users or role assignments: its registration, password and role-mutation methods return `false`. The separate administration service can replace **direct object role grants** after server-side native `edit_permission` validation; it does not edit group memberships or create user accounts.

### 5.3 Groups

Discoverable `IGroup` and `IUserCriterion` implementations add BASE3-only groups and optional role/user/email-domain membership criteria. These groups do not change native ILIAS user roles or RBAC. Alongside code groups, administrators may create, describe and delete custom BASE3 group DTOs. The ILIAS-backed `ISettingsStore` dataset `base3ilias/groups` contains custom group names/descriptions, user/role IDs and configured membership criterion expressions. Legacy `assignments` were converted by the versioned `Migration001ConvertGroupAssignments`; the runtime does not rewrite old settings.

Feature audiences are stored by `Base3IliasFeatureConfiguration` in the single `base3_authorization_features/catalog` dataset. The new immutable `Migration001ConvertFeatureGrants` converts previous group-name `grants` lists to `rules` expressions. Rules may contain BASE3 group names, ILIAS user IDs, role-based criteria, configured domains such as `qualitus.de`, or other plugin-specific criterion configuration. Feature and group settings may thereby identify individuals or groups of people. The host stores the rules, not a separate BASE3 permission database. Removing a custom group deletes its active settings record only when there is no feature/default reference; historic backups, logs, and native ILIAS users/roles are unaffected. Deletion and corrections of user IDs or domain rules are performed by authorized administrators through the two admin displays. Settings have no source-verified automatic retention period; ILIAS operators control access, retention, backups, deletion and privacy notices.

The group/feature forms use session-backed CSRF tokens, removed when the host session expires. The browser sends these forms directly to the ILIAS-hosted BASE3 endpoint over the existing authenticated connection, and no external recipient is introduced. The shared criterion editor renders labelled controls and AJAX feedback; host deployments remain responsible for testing assistive technologies and for their security/privacy obligations.

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

### 15.3 PHP runtime fatal-error logging

`Base3IliasRuntime::configureDebug()` enables PHP `log_errors` and keeps fatal
PHP error categories enabled when debug output is disabled. This can record
uncaught exception messages, source paths and stack traces to the PHP error
log configured by the host. It does not add a Base3Ilias-managed log file, and
Base3Ilias does not set retention or redact these PHP fatal-error entries.
Administrators must restrict log access and configure retention at the host
level. Normal successful requests do not emit new PHP error-log entries from
this change.

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

Insert IDs are read directly through the existing ILIAS PDO database service after write statements. The adapter does not open a second database connection or issue separate SQL queries to reset or retrieve `LAST_INSERT_ID()`. The SQL issued by consumers and their applicable data processing remain unchanged.

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

On ILIAS 10+ with a native ILIAS component bootstrap, Base3Ilias publishes
static `assets` directories from BASE3 modules through ILIAS Component Revision
`PublicAsset` contributions. Loading the generic BASE3 class map does not
instantiate or register this native asset publisher.

In the temporary ILIAS 9 deployment, the asset resolver references the static
files directly under the adapter's local `lib/<ModuleName>/assets/` tree; there
is no native ILIAS Component Revision asset publishing in that deployment. The
assets are served by the host web server if its configuration permits access.

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
| ILIAS IRSS resources opened through `IFileStorageFactory` | Controlled by the owning RID/RCID lifecycle and IRSS stakeholder relationships; Base3Ilias defines no global retention period |

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
- RID and RCID values are only exposed to code authorized to access the corresponding ILIAS-backed storage

## 27. Relationship to ILIAS privacy controls

Base3Ilias relies on ILIAS as the host system for authentication, sessions, users, RBAC, database connectivity, logging and several operational settings.

Deleting or changing authoritative user data in ILIAS therefore changes what Base3Ilias can read from the host system. Base3Ilias does not implement an independent user-data deletion workflow because it does not own the primary user records.

Independent BASE3 state, configuration, session values, logs or files must still be considered separately where they contain personal data.

## 28. ILIAS Resource Storage adapter

Base3Ilias can expose existing ILIAS Resource Storage Service areas through `ResourceFoundation\Api\IFileStorageFactory` and `IFileStorage`. The factory accepts an externally managed RID or RCID plus a storage mode and does not persist that identifier on behalf of the calling domain object.

The adapter can process arbitrary uploaded or generated file content and the corresponding ILIAS resource metadata. Depending on the selected mode, this includes:

- a single IRSS file resource and its current revision
- the member resources of an IRSS resource collection
- files and directory names contained in an IRSS container resource

ILIAS remains the authoritative storage system. Base3Ilias does not derive or directly access physical `storage/fsv2` paths and does not write IRSS database tables directly. Reads and writes use the ILIAS Resource Storage Service API.

For resources created by collection `write()` or `copy()` operations and for single-file or collection-member resources updated through the adapter, Base3Ilias uses an IRSS stakeholder named `base3ilias_file_storage`. Container mutations use the existing container resource and do not add a stakeholder through the container mutation API. The stakeholder owner defaults to the current ILIAS user ID when available and otherwise to the ILIAS system user ID (`SYSTEM_USER_ID`, or `6` if that constant is unavailable). This is initialized by Base3Ilias even on ILIAS 9, where the abstract IRSS stakeholder does not initialize an owner.

The adapter does not define a general retention period. The calling component remains responsible for persisting and removing its RID or RCID reference. Copying a collection member can create an additional IRSS member resource with the same file content, while copying a container file creates another path inside the same container. Moving a collection or container file writes the target first and removes the source only after the target operation succeeded. Moving a single-file storage changes the current file name while keeping the same RID. Deleting a collection member removes it from that logical collection and asks IRSS to release the Base3Ilias stakeholder. Deleting a single-file resource removes the Base3Ilias stakeholder and only removes the physical IRSS resource when no other stakeholder keeps it in use. Container file and directory deletion changes the content of the existing container resource rather than deleting the container RID itself.

File contents, file names and directory names can contain personal or confidential information. Access to a factory and to the externally managed storage identifier must therefore be restricted to code that is authorized to access the corresponding ILIAS-backed storage area.

### BASE3 feature authorization configuration

The ILIAS adapter stores feature audience expressions in one `base3_authorization_features/catalog` dataset in the active `ISettingsStore`, under `rules`. Group names, feature IDs, native ILIAS user IDs and configured criterion attributes (for example a qualifying email domain, exact user login names or complete email addresses) may be stored together in one administrative update. Arbitrary plugin-specific criteria can contain other fields according to their schemas; assess their privacy impact before enabling them. Passwords are not part of this dataset. The operator determines retention, backups and deletion. A saved empty expression denies the feature; missing settings use the code-defined audience.


### Code-defined initial developer identities

The `QualitusIlias` project plugin may store exact ILIAS login names or
complete email addresses in source-defined `development` group criteria. Host-managed additional group
criteria use the existing ILIAS settings dataset. The code-owned baseline is
not overwritten by UI edits, and the two sources are evaluated by the same
`UserCriterionEvaluator`; they do not create a second persistence backend.
Affected repositories and deployment backups containing configured identities require
appropriate access control. Revoking a fixed bootstrap member requires updating
and redeploying the project source. See the separate `QualitusIlias/PRIVACY.md` project-plugin source
for the exact bootstrap implementation and its retention limitations.

## Authorization administration UI refactor

The shared ClientStack screens do not create another persistence layer. ILIAS group identifiers, user and role criterion IDs, and feature audience expressions continue to be processed and stored solely by the configured ILIAS host services. Access is checked server-side; POST mutations require a session CSRF token. The new UiFoundation interface provides no storage. Retention and erasure continue to follow the host settings backend as previously documented.
