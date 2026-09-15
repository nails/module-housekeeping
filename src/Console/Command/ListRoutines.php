<?php

namespace Nails\Housekeeping\Console\Command;

use Cron\CronExpression;
use Nails\Common\Exception\FactoryException;
use Nails\Components;
use Nails\Console\Command\Base;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Exception\RoutineMisconfiguredException;
use Nails\Housekeeping\Interfaces\Routine;
use Nails\Housekeeping\Service\Orchestrator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListRoutines extends Base
{
    protected function configure(): void
    {
        $this
            ->setName('housekeeping:list')
            ->setDescription('Lists discovered housekeeping routines')
            ->addArgument('component', InputArgument::OPTIONAL, 'Filter by component');
    }

    /**
     * @throws FactoryException
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput): int
    {
        parent::execute($oInput, $oOutput);

        $this->banner('Housekeeping: List Routines');

        /** @var Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', Constants::MODULE_SLUG);
        $aRoutines     = $oOrchestrator->discover();
        $sFilter       = (string) $this->oInput->getArgument('component');

        $oOutput->writeln(sprintf('Discovered <info>%d</info> routine(s)', count($aRoutines)));

        foreach ($aRoutines as $oRoutine) {
            $oComponent = Components::detectClassComponent($oRoutine);
            $sPattern   = '/' . str_replace('/', '\/', $sFilter) . '/';
            if (!empty($sFilter) && (empty($oComponent) || !preg_match($sPattern, $oComponent->slug))) {
                continue;
            }

            $this->writeRoutine($oOutput, $oRoutine, $oComponent);
        }

        $oOutput->writeln('');

        return static::EXIT_CODE_SUCCESS;
    }

    /**
     * @throws RoutineMisconfiguredException
     */
    private function writeRoutine(
        OutputInterface $oOutput,
        Routine $oRoutine,
        mixed $oComponent
    ): void {
        $sExpression = $oRoutine->getCronExpression();
        if ($sExpression === '') {
            throw new RoutineMisconfiguredException(sprintf(
                'Housekeeping routine "%s" is misconfigured; cron expression is empty',
                $oRoutine->getKey()
            ));
        }
        if (!CronExpression::isValidExpression($sExpression)) {
            throw new RoutineMisconfiguredException(sprintf(
                'Housekeeping routine "%s" is misconfigured; "%s" is not a valid cron expression',
                $oRoutine->getKey(),
                $sExpression
            ));
        }

        $oOutput->writeln('');
        $oOutput->writeln('Routine:     <info>' . $oRoutine->getKey() . '</info>');
        $oOutput->writeln('Label:       <info>' . $oRoutine->getLabel() . '</info>');
        $oOutput->writeln('Description: <info>' . $oRoutine->getDescription() . '</info>');
        $oOutput->writeln('Component:   <info>' . ($oComponent->name ?? 'Unknown') . '</info>');
        $oOutput->writeln('Expression:  <info>' . $sExpression . '</info>');

        if (!$oRoutine->isEnabled()) {
            $oOutput->writeln('<warning>This routine has been disabled and will not execute</warning>');
        }
    }
}
