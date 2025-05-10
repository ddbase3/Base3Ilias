<?php declare(strict_types=1);

namespace Base3;

use ILIAS\Component\Component;
use ILIAS\Component\Resource\PublicAsset;
use ILIAS\Component\Resource\Endpoint;

class Base3Ilias implements Component
{
    public function init(
        array | \ArrayAccess &$define,
        array | \ArrayAccess &$implement,
        array | \ArrayAccess &$use,
        array | \ArrayAccess &$contribute,
        array | \ArrayAccess &$seek,
        array | \ArrayAccess &$provide,
        array | \ArrayAccess &$pull,
        array | \ArrayAccess &$internal,
    ): void {
        $contribute[PublicAsset::class] = fn() => new Endpoint($this, 'base3.php');
    }
}
