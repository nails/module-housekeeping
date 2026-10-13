<?php

namespace Nails\Housekeeping\Log;

use Nails\Config;

class Thresholds
{
    public const CATCH_ALL = '*';

    private const FILENAME = '/^(?<channel>.+?)-\d{4}-\d{2}-\d{2}(?:-\d{2}-\d{2}-\d{2})?\.php(?:\.gz)?$/';

    public function forFile(string $sFilename, string $sCatchAllKey, int $iDefault): Threshold
    {
        $sChannel = $this->channel($sFilename);

        if ($sChannel !== null) {
            $sKey = $sCatchAllKey . '_' . $this->normalise($sChannel);
            if (Config::isSet($sKey)) {
                return new Threshold($sChannel, (int) Config::get($sKey));
            }
        }

        return new Threshold(
            $sChannel ?? self::CATCH_ALL,
            (int) Config::get($sCatchAllKey, $iDefault)
        );
    }

    public function channel(string $sFilename): ?string
    {
        if (preg_match(self::FILENAME, $sFilename, $aMatches) !== 1) {
            return null;
        }

        return $aMatches['channel'];
    }

    public function normalise(string $sChannel): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '_', $sChannel));
    }
}
