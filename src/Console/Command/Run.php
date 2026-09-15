<?php

namespace Nails\Housekeeping\Console\Command;

use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Console\Command\Base;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Service\Orchestrator;
use ReflectionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Base
{
    protected function configure(): void
    {
        $this
            ->setName('housekeeping:run')
            ->setDescription('Executes due housekeeping routines')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Log what would be removed without making changes'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Run routines even if they are not due'
            )
            ->addOption(
                'routine',
                'r',
                InputOption::VALUE_REQUIRED,
                'Run a single routine (FQCN or class name)'
            );
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput): int
    {
        parent::execute($oInput, $oOutput);

        $this->banner('Housekeeping: Run');

        /** @var Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', Constants::MODULE_SLUG);

        $aResults = $oOrchestrator->run(
            $oOutput,
            (bool) $oInput->getOption('dry-run'),
            (bool) $oInput->getOption('force'),
            $oInput->getOption('routine') ? (string) $oInput->getOption('routine') : null
        );

        $oOutput->writeln('');
        if (empty($aResults)) {
            $oOutput->writeln('No routines were executed');
        } else {
            $bFailed = false;
            foreach ($aResults as $oResult) {
                if (!$oResult->isSuccess()) {
                    $bFailed = true;
                    break;
                }
            }
            $oOutput->writeln(sprintf(
                'Finished <info>%d</info> routine(s)',
                count($aResults)
            ));
            if ($bFailed) {
                return static::EXIT_CODE_FAILURE;
            }
        }

        $oOutput->writeln('');

        return static::EXIT_CODE_SUCCESS;
    }
}
