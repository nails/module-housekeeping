<?php

namespace Nails\Housekeeping\Service;

use Nails\Common\Exception\FactoryException;
use Nails\Common\Factory;
use Nails\Factory as NailsFactory;

class Logger
{
    private Factory\Logger $oLogger;
    private string $sSessionId;
    private string $sFile;

    /**
     * @throws FactoryException
     */
    public function __construct(?Factory\Logger $oLogger = null, ?string $sSessionId = null, ?string $sFile = null)
    {
        $this->sSessionId = $sSessionId ?? uniqid();

        if ($oLogger) {
            $this->oLogger = $oLogger;
            $this->sFile   = $sFile ?? $oLogger->getFile();
            return;
        }

        /** @var \DateTime $oNow */
        $oNow = NailsFactory::factory('DateTime');
        $this->sFile = 'housekeeping-' . $oNow->format('Y-m-d') . '.php';

        /** @var Factory\Logger $oLogger */
        $oLogger = NailsFactory::factory('Logger');
        $this->oLogger = $oLogger;
        $this->oLogger
            ->setFile($this->sFile)
            ->setFormat('%s - %s [' . $this->sSessionId . '] - %s ');
    }

    public function getSessionId(): string
    {
        return $this->sSessionId;
    }

    public function getFile(): string
    {
        return $this->sFile;
    }

    public function getDir(): string
    {
        return $this->oLogger->getDir();
    }

    public function getPath(): string
    {
        return $this->oLogger->getDir() . $this->sFile;
    }

    public function debug(string $sLine = ''): self
    {
        $this->oLogger->debug($sLine);
        return $this;
    }

    public function info(string $sLine = ''): self
    {
        $this->oLogger->info($sLine);
        return $this;
    }

    public function warning(string $sLine = ''): self
    {
        $this->oLogger->warning($sLine);
        return $this;
    }

    public function error(string $sLine = ''): self
    {
        $this->oLogger->error($sLine);
        return $this;
    }

    /**
     * Write a routine-scoped audit line: "{class} --> {message}"
     */
    public function routine(string $sClass, string $sMessage): self
    {
        return $this->info($sClass . ' --> ' . $sMessage);
    }

    /**
     * @return string[] Absolute paths of housekeeping log files, newest first
     */
    public function listLogFiles(): array
    {
        $sDir = $this->oLogger->getDir();
        if (!is_dir($sDir)) {
            return [];
        }

        $aFiles = array_merge(
            glob($sDir . 'housekeeping-*.php') ?: [],
            glob($sDir . 'housekeeping-*.php.gz') ?: []
        );
        rsort($aFiles);

        return $aFiles;
    }

    /**
     * Return the tail of a housekeeping log file. Refuses paths outside the log dir.
     */
    public function readLogTail(string $sFileName, int $iBytes = 102400): string
    {
        $sFileName = basename($sFileName);
        if (!preg_match('/^housekeeping-\d{4}-\d{2}-\d{2}\.php(\.gz)?$/', $sFileName)) {
            return '';
        }

        $sPath = $this->oLogger->getDir() . $sFileName;
        if (!is_file($sPath)) {
            return '';
        }

        if (str_ends_with($sFileName, '.gz')) {
            return $this->readGzippedLogTail($sPath, $iBytes);
        }

        $iSize = filesize($sPath);
        if ($iSize === false || $iSize === 0) {
            return '';
        }

        $iStart  = max(0, $iSize - $iBytes);
        $oHandle = fopen($sPath, 'rb');
        if ($oHandle === false) {
            return '';
        }

        fseek($oHandle, $iStart);
        $sContents = (string) stream_get_contents($oHandle);
        fclose($oHandle);

        return $this->formatLogTail($sContents, $iStart > 0);
    }

    private function readGzippedLogTail(string $sPath, int $iBytes): string
    {
        $sDecoded = gzdecode((string) file_get_contents($sPath));
        if ($sDecoded === false || $sDecoded === '') {
            return '';
        }

        $iLength    = strlen($sDecoded);
        $bTruncated = $iLength > $iBytes;
        $sContents  = $bTruncated ? substr($sDecoded, -$iBytes) : $sDecoded;

        return $this->formatLogTail($sContents, $bTruncated);
    }

    private function formatLogTail(string $sContents, bool $bTruncated): string
    {
        $sContents = preg_replace('/^<\?php die\(\'Unauthorised\'\); \?>\s*/', '', $sContents) ?? $sContents;

        if ($bTruncated) {
            $iFirstNewline = strpos($sContents, "\n");
            if ($iFirstNewline !== false) {
                $sContents = substr($sContents, $iFirstNewline + 1);
            }
            $sContents = "[... truncated ...]\n" . $sContents;
        }

        return $sContents;
    }
}
