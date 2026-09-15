<?php

namespace Tests\Housekeeping\Routine;

use Nails\Housekeeping\Routine\Result;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Routine\Result
 */
class ResultTest extends TestCase
{
    public function test_ok_is_successful(): void
    {
        $oResult = Result::ok(12, 'done');

        self::assertTrue($oResult->isSuccess());
        self::assertSame(12, $oResult->getProcessed());
        self::assertSame(0, $oResult->getFailed());
        self::assertSame('done', $oResult->getMessage());
    }

    public function test_fail_is_not_successful(): void
    {
        $oResult = Result::fail('nope', 3, 2);

        self::assertFalse($oResult->isSuccess());
        self::assertSame(3, $oResult->getProcessed());
        self::assertSame(2, $oResult->getFailed());
        self::assertSame('nope', $oResult->getMessage());
    }
}
