# BASE3 audiences in the ILIAS integration

## Ownership and scope

ILIAS RBAC remains the native authority for ILIAS object actions. The BASE3 group and
feature controls described here only configure BASE3-defined membership and feature
availability; they do **not** assign native ILIAS roles, write object permissions or
replace `ilRbacSystem::checkAccessOfUser()`.

The BASE3 framework contains interfaces, DTOs, and the reusable read-only
`UserCriterionEvaluator`. It does not require any authorization database. `Base3Ilias`
provides the implementation on top of ILIAS's active `ISettingsStore`.

## Groups: code and user-created

Code-owned groups implement `IGroup` and are discovered with `IClassMap`. The shipped
`administration`, `anonymous`, `authenticated`, `presentation` and
`testing` groups are separate classes. The `development` group in the ILIAS 11
project is owned by the optional `QualitusIlias` project plugin; it must not
be defined a second time by `Base3Ilias`. `anonymous` and `authenticated` are immutable
code definitions; the other built-ins expose editable criteria. Any criteria supplied
by an `IGroup` implementation are **fixed**; host-managed criteria add members with OR
and never replace the code-owned membership. The group UI displays fixed criteria
read-only, separately from editable additions. A project plugin can
contribute more code groups or predicates without a settings record.

The Groups administration display also creates ordinary `Base3\Usermanager\Group`
objects stored in the existing `base3ilias/groups` settings dataset, under `groups`.
These are **data definitions**, not dynamically created implementations of `IGroup`.
Their names cannot collide with code-discovered groups. A managed group can be removed
only if neither the default group nor any feature audience references it.

Every editable group has a single membership expression. A form can add any number
of ILIAS users (positive ILIAS user IDs), ILIAS role object IDs, or discoverable
`IUserCriterion`s. Each row is OR-connected with the others. Native ILIAS role
membership is obtained from the existing usermanager/`ilRbacReview` path; it is not
recomputed in BASE3. Global roles are offered as suggestions; existing local role
object IDs can be entered directly. A criterion can be defined entirely by plugin
code (no database record) or configured using the schema returned by `getSchema()`.
For example choose `base3emaildomaincriterion` and set `domain = qualitus.de`,
or choose `base3useridentitycriterion` with exact `usernames` or complete
`emails` lists. Domain-wide matching does not prove native admin status.

Advanced expressions retain the framework's `any`, `all` and `not` operators and
round-trip through the JSON editor. `[]` means no membership. Group membership
rules must not reference BASE3 groups, which prevents cycles. A legacy fallback
`default_group` remains supported for nonprivileged groups; `development` and
`administration` can **never** be selected or applied as an implicit fallback.

## Features

Discoverable `IFeature` components identify actual `Permission`s and declare a
`getDefaultAudience()` expression. The Features tab may add BASE3 groups, individual
ILIAS users, native ILIAS roles or any discoverable criteria, using the same evaluator
and editor. Role rows use `Base3IliasRoleCriterion` and the ILIAS usermanager's
effective native role IDs, not a second rights engine.
A persisted `[]` denies a feature deliberately; lack of a saved override uses
`IFeature::getDefaultAudience()`. Native host permissions can impose further rules.

The ILIAS adapter stores all overrides together in one
`base3_authorization_features/catalog` dataset under `rules` and commits it in one
settings write. **No second group/permission store is created.** Feature audience
configuration is server-side restricted to ILIAS system administrators in the BASE3
`development` group and protected
by an ILIAS session CSRF token. The framework routes global feature checks through
`IAuthorizationService` and uses the trusted `IUsermanager` user and effective BASE3
group names for the single `UserCriterionEvaluator` call.

## Migrations and rollback

Existing `base3ilias/groups` legacy `assignments` are converted by the previously
introduced `Migration001ConvertGroupAssignments`. Existing feature `grants` lists
are converted to audience `rules` by the new
`Base3IliasFeatureAudienceMigrationProvider` / `Migration001ConvertFeatureGrants`.
These are immutable, one-time migration steps during bootstrap, not hidden runtime
conversions. Always back up the affected ILIAS settings and migration tables before
applying this patch. Do not manually roll back application files against converted
settings without first restoring compatible settings or completing a forward migration.

## Administration navigation

`Base3IliasLabSettings` provides the reduced navigation to ILIAS administrators
without requiring any BASE3 group membership. That view retains only BASE3 → Permissions; Groups and Features are restricted
to administrators in `development`, on both navigation and HTTP endpoints. Only a current user
whose effective BASE3 groups contain `development` receives the complete tab catalog.
A native ILIAS system administrator is **not automatically a BASE3 developer**.
Deployments must provision an initial exact trusted account using a project-owned code
group before disabling setup. Use the ILIAS login name or a verified, protected
complete email address; numeric IDs are not portable between ILIAS installations. For the ILIAS 11 project, set the bootstrap account
in `QualitusIlias/src/Group/QualitusDevelopmentGroup.php`. It cannot be
overridden by the Groups UI. This is not a bypass of `system/admin`: an ILIAS
administrator role is additionally required for editing Groups or Features.
Never use an email-domain criterion as a bootstrap administrator grant.
The reusable `Base3UserIdentityCriterion` matches exact logins (`User::userid`) or
complete addresses (`User::email`) and never interprets a domain as a wildcard.
Navigation is a presentation decision and does not replace native administrative
permission checks at the corresponding endpoints.

## Privacy, accessibility and performance

Settings may contain ILIAS user IDs, ILIAS role IDs, exact login names, full email addresses, configured email domains,
custom group names and free-text descriptions. The authoritative ILIAS settings
backend, deployment backups and host administrators govern storage, retention,
correction and deletion. The technical lifecycle is described in `../PRIVACY.md`;
this document is not a deployment privacy notice.

The editor provides keyboard-operable add/remove actions, labelled fields, an AJAX
status/error region, and generated inputs for simple criterion schema properties.
Complex schema definitions can use a JSON field; invalid submissions are rejected
before persistence. Server-side admin authorization and CSRF protection remain in
place even if an alternative client calls the endpoint directly.

The evaluator reuses effective ILIAS roles and a request-local resolved BASE3 group
list. It neither traverses the ILIAS repository tree nor caches cross-user permission
decisions. For large native resource lists, continue using the native RBAC/query
filtering APIs described in `Base3Framework/docs/authorization-adapters.md`.
