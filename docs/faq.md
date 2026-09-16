# Base3Ilias FAQ

## What is Base3Ilias?

Base3Ilias is the integration component that runs BASE3 inside ILIAS. It connects the BASE3 runtime model to native ILIAS services such as the dependency injection container, database, authentication session, user and RBAC services, logging, language handling, public assets, routing, and administration facilities.

The component does not replace ILIAS. ILIAS remains the host system, while BASE3 is exposed as an embedded runtime for BASE3 plugins and services.

## Which versions are required?

The current README declares these minimum requirements:

- ILIAS 10.0 or newer
- PHP 8.2 or newer
- BASE3 Framework

The runtime contains separate initialization paths for ILIAS 10 and ILIAS 11.

## How does Base3Ilias identify the host and embedded systems?

`Base3IliasSystemService` reports:

- host system: `ILIAS`
- embedded system: `BASE3`

The ILIAS version is read from the active ILIAS runtime or its version file. The BASE3 version is read from the BASE3 Framework `VERSION` file.

## How is the BASE3 runtime started inside ILIAS?

`Base3IliasRuntime` prepares the BASE3 directory constants, connects the BASE3 service locator to the ILIAS container, discovers BASE3 plugins and hook listeners, initializes plugins, runs the configured migration runner, and then exposes request dispatch through the BASE3 service selector.

For standalone public endpoint execution, the runtime can initialize ILIAS first and then dispatch the BASE3 request.

## Which BASE3 services are adapted to ILIAS?

Base3Ilias registers ILIAS-specific implementations or bridges for several BASE3 service slots, including:

- `IDatabase`
- `IConfiguration`
- `IStateStore`
- `ILogger`
- `ISession`
- `IAccesscontrol`
- `IUsermanager`
- `ILanguage`
- `ITranslation`
- `IMvcView`
- `IAssetResolver`
- `ILinkTargetService`
- `IMigrationRunner`

It also exposes the ILIAS dependency injection container to the BASE3 service locator.

## Does Base3Ilias create a separate database connection?

No. `Base3IliasDatabase` adapts the active ILIAS database service to the BASE3 `IDatabase` contract. ILIAS owns the connection lifecycle. Calling `disconnect()` on the adapter therefore does not close the shared ILIAS connection.

## Where is BASE3 configuration stored in this integration?

`Base3IliasConfiguration` uses the BASE3 database-backed configuration implementation with the ILIAS database adapter. It adds Base3Ilias defaults for the BASE3 endpoint and base URL when those values are not already configured.

## How is runtime state stored?

`Base3IliasStateStore` stores state in the ILIAS database table `base3_statestore`.

Each entry contains:

- a string key
- a JSON-encoded value
- an update timestamp
- an optional expiration timestamp

Entries with an expiration timestamp are treated as absent after expiration. Entries written without a TTL remain until explicitly deleted.

## Is `setIfNotExists()` a fully atomic distributed lock?

No. The current ILIAS-backed state store checks for an existing active row and then performs an insert or update. The implementation documents that ILIAS does not expose reliable affected-row or insert-id information for this purpose. Code that requires strict cross-process locking should not assume stronger atomicity than this implementation provides.

## How are sessions handled?

`Base3IliasSession` uses the already active ILIAS PHP session. It does not start or terminate the ILIAS session lifecycle.

BASE3-owned session entries are namespaced with the `base3_` prefix. Calling `destroy()` on this adapter clears only session keys with that prefix rather than destroying the complete ILIAS session.

## How is authentication mapped?

`Base3IliasAuth` reads the current user ID from the ILIAS authentication session. The anonymous ILIAS user is treated as unauthenticated.

BASE3 access control is then built around that ILIAS-backed authentication source.

## How are users and permissions exposed to BASE3?

`Base3IliasUsermanager` is a read-side adapter over ILIAS user and RBAC data.

For a user it can expose data such as:

- ILIAS user ID
- login
- display name
- email address
- language
- effective roles

The adapter derives the BASE3 `admin` role from the ILIAS system administrator role.

## Does the usermanager modify ILIAS users or RBAC assignments?

No. The reviewed implementation returns `false` for user registration, password changes, role assignment and revocation, and permission mutation methods.

ILIAS user and RBAC administration remains outside this adapter.

## Are ILIAS groups mapped to BASE3 groups?

No. `getGroups()` and `getAllGroups()` currently return empty arrays. ILIAS object permissions are handled through roles and target-specific permission checks instead.

## How are object-specific ILIAS permissions checked?

For target-specific ILIAS access, the BASE3 permission uses:

- scope: `ilias`
- permission: the ILIAS operation name, such as `read` or `write`
- target: a positive ILIAS repository `ref_id`

For the current user, the adapter delegates to the native ILIAS RBAC access check. It can also evaluate another user's assigned roles against a target object without replacing the current session user.

## Does Base3Ilias preserve incoming requests for standalone endpoints?

It can. When `bootStandaloneAndDispatch(true)` is used, Base3Ilias captures the incoming request before ILIAS initialization so that ILIAS bootstrap processing cannot destroy information needed by the BASE3 endpoint.

The snapshot can contain GET, POST, REQUEST, COOKIE, SESSION, SERVER and FILES data plus the raw request body. It is kept in process memory for the request and is used to rebuild `IRequest`. Base3Ilias does not persist this snapshot to a database or file by itself.

## How are BASE3 links generated inside ILIAS?

`Base3IliasLinkTargetService` builds query-based links to the configured Base3Ilias dispatch endpoint. The default endpoint is the ILIAS plugin router command used to dispatch a BASE3 output.

## How are plugin assets published?

During ILIAS component initialization, Base3Ilias scans the BASE3 component directory for plugin `assets` directories and contributes those assets as ILIAS public assets.

`Base3IliasAssetResolver` then converts logical paths such as `plugin/Foo/assets/js/app.js` into the public ILIAS component path and adds a short file-content hash as a cache-busting query parameter.

## What directories does Base3Ilias create?

The runtime resolves the active ILIAS client data directory and creates a client-specific BASE3 area with at least:

- `base3/`
- `base3/artifacts/`
- `base3/cache/`

The BASE3 temporary directory is mapped to the artifacts directory. The BASE3 local-data directory is mapped to the client-specific BASE3 data directory.

## What happens to the artifacts directory?

During ILIAS component initialization in CLI mode, Base3Ilias clears the contents of the client-specific `base3/artifacts` directory once per process.

This cleanup does not imply that the `base3/cache` directory is cleared.

## How does logging work?

`Base3IliasLogger` forwards BASE3 log calls to the native ILIAS logger when available. The log line includes the BASE3 marker, level, scope and message. For structured logging calls, the supplied context is JSON-encoded and appended to the log line.

If the ILIAS logger is unavailable, the adapter falls back to PHP `error_log()`.

## Does Base3Ilias provide log retention?

It contains `Base3LogCleanupJob`, which can delete old rows from a `base3_log` table when that table exists and the job is enabled.

The default retention value used by the job is 48 hours, and the default delete batch is 100,000 rows. These values can be overridden through Base3Ilias state keys. The job does not manage the native ILIAS log files or the ILIAS error-log directory.

## What administration and diagnostic views are included?

The component provides several ILIAS-oriented administration and diagnostic displays, including views for:

- system overview and health
- ILIAS configuration
- ILIAS log output
- ILIAS error-log files
- request and controller data
- users and user preferences
- RBAC roles and permissions
- repository objects, paths and children

These views are intended for administration and diagnostics and can expose operational or personal data.

## Does the request debug view hide secrets?

It masks GET and POST parameter keys whose names contain `pass`, `pwd`, `token`, `secret`, or `csrf`, including nested arrays.

This is not a complete sanitization boundary. The same display also shows fields such as the raw request URI and query string, which can still contain sensitive values if callers place them in URLs. Administrative access to this display should therefore be restricted accordingly.

## Does the configuration view display the ILIAS setup password?

No. The reviewed configuration display explicitly masks the setup password with a fixed placeholder instead of rendering it in clear text.

Other displayed configuration values can still reveal operational information such as file paths, host configuration and tool locations.

## What does the error-log viewer expose?

The error-log administration display reads files from the ILIAS error-log directory configured in `ilias.ini.php`. It can list up to 500 files and return up to the final 1 MiB of a selected file.

It validates that the selected file remains inside the configured error-log directory, but it does not redact the file contents.

## Does Base3Ilias send data to external services?

The reviewed Base3Ilias component does not contain its own external AI provider, analytics service, telemetry client, or generic outbound HTTP integration. Its primary role is to bridge BASE3 to services already available inside ILIAS.

Data processing performed by separately installed components is outside the scope of this component's documentation.

## Where can I find privacy and data-processing information?

See [PRIVACY.md](../PRIVACY.md).
