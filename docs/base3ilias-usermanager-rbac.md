# Base3IliasUsermanager RBAC Adapter

## Purpose

`Base3IliasUsermanager` maps ILIAS user and RBAC data into the BASE3 usermanager contract.

The class implements:

```text
Base3\Usermanager\Api\IUsermanager
Base3\Api\ICheck
```

It keeps the existing no-argument constructor so the current Base3Ilias service registration remains unchanged:

```php
->set(IUsermanager::class, fn() => new Base3IliasUsermanager(), IContainer::SHARED)
```

## User lookup

### Current user

`getUser()` returns the current non-anonymous ILIAS user as `Base3\Usermanager\User`:

```text
id       ILIAS usr_id as string
userid   ILIAS login
name     ILIAS full display name
email    ILIAS email
lang     ILIAS language
role     BASE3 compatibility value "member"
roles    effective ILIAS roles mapped as BASE3 Role objects
```

The anonymous ILIAS user is excluded and returns `null`.

### User by technical ID

`getUserById()` resolves a specific ILIAS user by `usr_id`:

```php
$user = $usermanager->getUserById($userId);
```

The argument addresses `User::$id`, not the ILIAS login stored in `User::$userid`.

Unknown, non-numeric, non-positive, and anonymous user IDs return `null`.

Looking up another user does not replace or modify the cached current user, current roles, or current permissions.

## Roles

`getRoles()` returns the effective roles of the current user.

`getUserById()` resolves roles separately for the requested user.

Role assignments are read through:

```php
$rbacreview->assignedRoles($userId);
$rbacreview->assignedGlobalRoles($userId);
```

Each ILIAS role becomes a `Base3\Usermanager\Role`:

```text
id           ILIAS role obj_id
name         normalized technical role title
label        original ILIAS role title
info         global or local/linked role note
archive      0
permissions  empty array
```

Role checks compare the requested role by ID or by normalized technical name.

## Permissions

ILIAS permissions are object-specific. Without a target `ref_id`, there is no correct effective permission list for a user.

Therefore:

```php
$usermanager->getPermissions();
```

returns an empty array.

`getAllPermissions()` exposes the available ILIAS operation definitions returned by:

```php
$rbacreview->getOperations();
```

Each operation is represented as:

```text
scope       ilias
permission  ILIAS operation name
```

These entries describe available operations. They are not global effective grants.

## Object-specific ILIAS permission checks

Use the optional permission target for the ILIAS repository `ref_id`:

```php
$permission = Permission::for('ilias', 'read', $refId);

if (!$usermanager->can($permission)) {
	return 'Access denied';
}
```

The three values have fixed meanings:

```text
scope       "ilias"
permission  ILIAS operation name, for example "read" or "write"
target      positive ILIAS ref_id
```

The former encoded forms are not part of the current contract:

```text
ilias:<ref_id> as scope
<operation>:<ref_id> as permission
```

For the current user, the adapter delegates to:

```php
$rbacsystem->checkAccess($operation, $refId);
```

For another user ID, the adapter resolves:

```text
assigned roles of the requested user
parent roles of the target ref_id
role operations on that object
operation ID by operation name
```

This permits target-specific checks without replacing the current-user state.

## Groups

`getGroups()` and `getAllGroups()` return an empty array.

ILIAS repository groups are not mapped to BASE3 groups by this adapter.

## User and RBAC mutations

The following methods return `false` and do not mutate ILIAS data:

```text
registUser()
changePassword()
assignRoleToUser()
revokeRoleFromUser()
assignRoleToGroup()
revokeRoleFromGroup()
addPermissionToRole()
removePermissionFromRole()
```

The adapter is a read-side bridge. ILIAS user and RBAC administration remains in ILIAS-owned workflows.

## Dependencies

The adapter resolves these services from the shared BASE3 service locator:

```text
accesscontrol
ilAuthSession
ilUser
rbacreview or ilRbacReview
rbacsystem or ilRbacSystem
```

It also uses the ILIAS classes:

```text
ilObjUser
ilObject
ilRbacReview
```

`checkDependencies()` reports the availability of the access-control, current-user, RBAC review, and RBAC system services.
