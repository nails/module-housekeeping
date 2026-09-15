<?php

namespace Nails\Housekeeping\Console\Command\Routine;

use Exception;
use Nails\Common\Exception\NailsException;
use Nails\Console\Command\BaseMaker;
use Nails\Console\Exception\ConsoleException;
use Nails\Housekeeping\Exception\HousekeepingException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends BaseMaker
{
    const RESOURCE_PATH = NAILS_PATH . 'module-housekeeping/resources/console/';
    const ROUTINE_PATH  = NAILS_APP_PATH . 'src/Housekeeping/';

    protected function configure(): void
    {
        $this
            ->setName('make:housekeeping:routine')
            ->setDescription('Creates a new app housekeeping routine')
            ->addArgument(
                'className',
                InputArgument::OPTIONAL,
                'Define the name of the housekeeping routine'
            );
    }

    protected function execute(InputInterface $oInput, OutputInterface $oOutput): int
    {
        parent::execute($oInput, $oOutput);

        try {
            $this
                ->createPath(self::ROUTINE_PATH)
                ->createRoutine();
        } catch (Exception $e) {
            return $this->abort(
                self::EXIT_CODE_FAILURE,
                [$e->getMessage()]
            );
        }

        $oOutput->writeln('');
        $oOutput->writeln('<comment>Cleaning up</comment>...');
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');

        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * @throws ConsoleException
     * @throws NailsException
     */
    private function createRoutine(): self
    {
        $aFields  = $this->getArguments();
        $aCreated = [];

        try {
            $aToCreate = [];
            $aRoutines = $this->parseClassNames($aFields['CLASS_NAME']);

            foreach ($aRoutines as $sRoutine) {
                $aClassBits = explode('/', $sRoutine);
                $aClassBits = array_map('ucfirst', $aClassBits);

                $sNamespace     = $this->generateNamespace($aClassBits);
                $sClassName     = $this->generateClassName($aClassBits);
                $sClassNameFull = $sNamespace . '\\' . $sClassName;
                $sFilePath      = $this->generateFilePath($aClassBits);

                if (file_exists($sFilePath)) {
                    throw new HousekeepingException(
                        'A routine at "' . $sFilePath . '" already exists'
                    );
                }

                $aToCreate[] = [
                    'NAMESPACE'       => $sNamespace,
                    'CLASS_NAME'      => $sClassName,
                    'CLASS_NAME_FULL' => $sClassNameFull,
                    'FILE_PATH'       => $sFilePath,
                    'DIRECTORY'       => dirname($sFilePath) . DIRECTORY_SEPARATOR,
                ];
            }

            $this->oOutput->writeln('The following routine(s) will be created:');
            foreach ($aToCreate as $aConfig) {
                $this->oOutput->writeln('');
                $this->oOutput->writeln('Class: <info>' . $aConfig['CLASS_NAME_FULL'] . '</info>');
                $this->oOutput->writeln('Path:  <info>' . $aConfig['FILE_PATH'] . '</info>');
            }
            $this->oOutput->writeln('');

            if ($this->confirm('Continue?', true)) {
                $this->oOutput->writeln('');
                foreach ($aToCreate as $aConfig) {
                    $this->oOutput->write('Creating routine <comment>' . $aConfig['CLASS_NAME_FULL'] . '</comment>... ');
                    $this->createPath($aConfig['DIRECTORY']);
                    $this->createFile(
                        $aConfig['FILE_PATH'],
                        $this->getResource('template/routine.php', $aConfig)
                    );
                    $aCreated[] = $aConfig['FILE_PATH'];
                    $this->oOutput->writeln('<info>done</info>');
                }
            }

        } catch (ConsoleException $e) {
            $this->oOutput->writeln('<error>fail</error>');
            if (!empty($aCreated)) {
                $this->oOutput->writeln('<error>Cleaning up - removing newly created files</error>');
                foreach ($aCreated as $sPath) {
                    @unlink($sPath);
                }
            }
            throw new ConsoleException($e->getMessage());
        }

        return $this;
    }

    /**
     * @param string[] $aClassBits
     */
    protected function generateClassName(array $aClassBits): string
    {
        $sClassName = array_pop($aClassBits);
        if ($sClassName === null || $sClassName === '') {
            throw new HousekeepingException('Invalid routine class name');
        }

        return $sClassName;
    }

    /**
     * @param string[] $aClassBits
     */
    protected function generateNamespace(array $aClassBits): string
    {
        array_pop($aClassBits);
        return implode('\\', array_merge(['App', 'Housekeeping'], $aClassBits));
    }

    /**
     * @param string[] $aClassBits
     */
    protected function generateFilePath(array $aClassBits): string
    {
        $sClassName = array_pop($aClassBits);
        if ($sClassName === null || $sClassName === '') {
            throw new HousekeepingException('Invalid routine class name');
        }

        return implode(
            DIRECTORY_SEPARATOR,
            array_map(
                static function ($sItem) {
                    return rtrim($sItem, DIRECTORY_SEPARATOR);
                },
                array_merge(
                    [static::ROUTINE_PATH],
                    $aClassBits,
                    [$sClassName . '.php']
                )
            )
        );
    }
}
