<?php

namespace Tests\Housekeeping\Routine;

use Nails\Environment;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use PHPUnit\Framework\TestCase;

class StubRoutine extends Base
{
    const LABEL           = 'Stub';
    const DESCRIPTION     = 'A stub routine';
    const CRON_EXPRESSION = '@daily';
    const ENVIRONMENT     = [Environment::ENV_PROD];
    const ENABLED         = false;

    public function execute(Context $oContext): Result
    {
        return Result::ok();
    }
}

/**
 * @covers \Nails\Housekeeping\Routine\Base
 */
class BaseTest extends TestCase
{
    public function test_getters_read_constants(): void
    {
        $oRoutine = new StubRoutine();

        self::assertSame(StubRoutine::class, $oRoutine->getKey());
        self::assertSame('Stub', $oRoutine->getLabel());
        self::assertSame('A stub routine', $oRoutine->getDescription());
        self::assertSame('@daily', $oRoutine->getCronExpression());
        self::assertSame([Environment::ENV_PROD], $oRoutine->getEnvironments());
        self::assertFalse($oRoutine->isEnabled());
    }
}
