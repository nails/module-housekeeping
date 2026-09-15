<?php

namespace Tests\Housekeeping\Service;

use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Service\Orchestrator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Service\Orchestrator
 */
class OrchestratorTest extends TestCase
{
    public function test_last_run_key_uses_dotted_class_name(): void
    {
        $oOrchestrator = new Orchestrator();

        self::assertSame(
            'last_run.Nails.Housekeeping.Housekeeping.LogFiles',
            $oOrchestrator->lastRunKey('Nails\\Housekeeping\\Housekeeping\\LogFiles')
        );
        self::assertSame(Constants::MODULE_SLUG, 'nails/module-housekeeping');
    }
}
