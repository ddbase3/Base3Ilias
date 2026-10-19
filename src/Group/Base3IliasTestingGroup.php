<?php declare(strict_types=1);

namespace Base3Ilias\Group;

/** Discoverable code-owned group; only editable groups accept host-configured criteria. */
final class Base3IliasTestingGroup extends AbstractIliasGroup {
	protected const GROUP_NAME = 'testing';
	protected const DESCRIPTION = 'Users assigned to BASE3 testing features.';
	protected const EDITABLE = true;
	protected const CRITERIA = [];
}
