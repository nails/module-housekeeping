<?php

namespace Tests\Housekeeping;

use Nails\Housekeeping\Constants;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Constants
 */
class ConstantsTest extends TestCase
{
    public function test_module_slug_is_correct(): void
    {
        self::assertSame('nails/module-housekeeping', Constants::MODULE_SLUG);
    }
}
