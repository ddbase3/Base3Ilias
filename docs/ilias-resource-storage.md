# ILIAS Resource Storage adapter

## Purpose

Base3Ilias exposes the ILIAS Resource Storage Service (IRSS) through the storage contracts from ResourceFoundation. BASE3 plugins can therefore work with `IFileStorage` and `IFileStorageFactory` without importing ILIAS classes.

The adapter is intended for context-specific storages, for example one logical file storage per ILIAS object. The owning object keeps the storage identifier. Base3Ilias only opens and operates the referenced storage.

## Service binding

Base3Ilias registers:

```text
ResourceFoundation\Api\IFileStorageFactory
    -> Base3Ilias\Base3\Base3IliasFileStorageFactory
```

The binding is registered as a replaceable default. A project can provide another final factory implementation before Base3Ilias initialization.

Typical BASE3 usage:

```php
$storage = $fileStorageFactory->openStorage($rid, 'collection');
$storage->write('document.pdf', $content);
```

The factory is the construction boundary. Runtime code should not instantiate `Base3IliasFileStorage` directly.

## Identifier ownership

`openStorage()` accepts the parameters in this order:

```text
id, mode
```

For the ILIAS implementation:

| Mode | `id` meaning |
|---|---|
| `single_file` | IRSS Resource Identification, RID |
| `collection` | IRSS Resource Collection Identification, RCID |
| `container` | IRSS Resource Identification, RID |

The factory only opens an existing storage. It does not create or persist the RID or RCID.

The calling domain object owns that lifecycle. This keeps repository-object persistence, deletion rules and migration behavior outside the generic storage adapter.

A collection is the only mode that creates additional IRSS resource identifiers during normal `IFileStorage::write()` calls. Those member RIDs are internal implementation details of the collection and are managed by the adapter through the collection membership.

## Modes

### Single file

A `single_file` storage wraps one IRSS resource of type `SINGLE_FILE`.

Logical view:

```text
/
└── document.pdf
```

Supported operations:

| Operation | Behavior |
|---|---|
| `list('')` | returns the current resource file |
| `read(file)` | reads the current published revision |
| `write(file, content)` | replaces the current resource content and file name |
| `delete(file)` | releases the Base3Ilias stakeholder and removes the resource when no other stakeholder remains |
| `exists(file)` | checks the current file name |
| `stat(file)` | returns file metadata |
| `mkdir()` | unsupported |
| `rmdir()` | unsupported |

The RID itself is not changed by `write()`. RID persistence is external to the adapter.

### Collection

A `collection` storage wraps one existing IRSS resource collection and exposes its member resources as a flat directory.

Logical view:

```text
/
├── a.pdf
├── b.txt
└── image.png
```

Each member must be an IRSS resource of type `SINGLE_FILE`.

Supported operations:

| Operation | Behavior |
|---|---|
| `list('')` | lists collection members |
| `read(file)` | reads the matching member resource |
| `write(file, content)` | replaces an existing member or creates a new member RID and adds it to the collection |
| `delete(file)` | removes the member from the collection and releases the Base3Ilias stakeholder |
| `exists(file)` | resolves a member by file name |
| `stat(file)` | returns member metadata |
| `mkdir()` | unsupported |
| `rmdir()` | unsupported |

Collections are deliberately flat. Paths such as `docs/file.pdf` are rejected.

`IFileStorage` addresses files by path, so duplicate member file names would be ambiguous. The adapter fails explicitly when an opened collection contains duplicate current file names instead of selecting an arbitrary member.

### Container

A `container` storage wraps one IRSS resource of type `CONTAINER`. ILIAS stores the structured content as a ZIP-backed resource.

Logical view:

```text
/
├── index.html
├── css/
│   └── app.css
└── images/
    └── logo.png
```

Supported operations:

| Operation | Behavior |
|---|---|
| `list(path)` | lists direct children of a directory |
| `read(file)` | reads one file from the container |
| `write(file, content)` | adds or replaces a file through IRSS container management |
| `delete(file)` | removes a file through IRSS container management |
| `mkdir(path)` | creates a directory through IRSS container management |
| `rmdir(path)` | removes a directory through IRSS container management |
| `exists(path)` | checks the logical ZIP structure |
| `stat(path)` | returns file or directory metadata |

Read-side ZIP inspection starts from the stream supplied by the IRSS consumer. The adapter does not derive a physical `storage/fsv2` path.

Write-side mutations use `manageContainer()` and therefore remain inside the IRSS resource boundary.

ILIAS 10 and 11 implement container path removal with prefix matching. Directory removal therefore passes a trailing `/` so only that directory subtree is addressed. File deletion refuses an ambiguous prefix collision such as `foo.txt` together with `foo.txt.bak`, because the public IRSS removal call cannot delete only the first entry safely in that situation.

## Path rules

All paths are relative to the opened logical storage.

The adapter normalizes `/` and `\\` separators and removes redundant `.` or empty path segments. Parent traversal with `..` is rejected.

Mode-specific rules are:

```text
single_file
    one file in the root

collection
    multiple files in the root

container
    files and nested directories
```

The empty path represents the logical storage root. `stat('')` therefore returns a directory entry and `exists('')` is true for a successfully opened storage.

## ILIAS service boundary

The implementation uses the public IRSS service entry points:

```text
Services::manage()
Services::manageContainer()
Services::consume()
Services::collection()
```

It does not:

- derive `storage/fsv2` paths
- manipulate IRSS database tables directly
- duplicate RID or RCID persistence in Base3Ilias
- introduce an ILIAS-version-specific storage implementation

## ILIAS 10 and 11 compatibility

The adapter is written against the common IRSS API available in the supplied ILIAS 10 and ILIAS 11 source trees. The required manager, consumer, collection and container entry points have compatible signatures for the operations used here.

No version branch is currently necessary. If a later ILIAS version changes one of these public boundaries, compatibility handling belongs at the Base3Ilias adapter boundary and can use `ISystemService` to select the required host-specific call.

## Stakeholder behavior

New collection members and single-file or collection-member resources written through manager operations use `Base3IliasFileStorageStakeholder` with the technical stakeholder ID:

```text
base3ilias_file_storage
```

The stakeholder can be instantiated without dependencies as required by IRSS. It treats referenced resources as in use because the authoritative RID or RCID reference is held outside the stakeholder and cannot be reconstructed by Base3Ilias itself. Container mutations operate on the already existing container resource and do not add this stakeholder because the public container mutation API has no stakeholder parameter.

Explicit `IFileStorage` deletion is still possible where the mode exposes file deletion. IRSS decides whether a single-file or collection-member resource can be removed physically after the Base3Ilias stakeholder is released.

## Factory scope

`IFileStorageFactory` intentionally contains only:

```php
openStorage(string $id, string $mode): IFileStorage
```

Generic `createStorage()` semantics are not part of the contract. Storage provisioning differs substantially between IRSS resources, IRSS collections, local directories, FTP roots and other backends. Creation should only be moved into the shared foundation after multiple implementations expose the same stable lifecycle.
