<?php

namespace Tests\Housekeeping\Cron\Task;

use Nails\Housekeeping\Cron\Task\Run;
use PHPUnit\Framework\TestCase;

class RunTest extends TestCase
{
    public function test_successful_output_is_not_copied_into_the_cron_log(): void
    {
        $oConstant = (new \ReflectionClass(Run::class))->getReflectionConstant('LOG_OUTPUT');

        self::assertNotFalse($oConstant);
        self::assertFalse($oConstant->getValue());
    }
}
