<?php

use Nails\Housekeeping\Admin\Controller\Housekeeping;

/**
 * @var string[] $aFiles
 * @var string   $sSelected
 * @var string   $sContents
 */

?>
<div class="group-housekeeping logs">
    <p>
        <a href="<?=Housekeeping::url()?>">&larr; Back to routines</a>
    </p>
    <div class="row">
        <div class="col-md-3">
            <h4>Log files</h4>
            <?php if (empty($aFiles)) { ?>
                <p class="text-muted">No housekeeping log files yet.</p>
            <?php } else { ?>
                <ul class="list-unstyled">
                    <?php foreach ($aFiles as $sPath) {
                        $sName = basename($sPath);
                        ?>
                        <li>
                            <?php if ($sName === $sSelected) { ?>
                                <strong><?=htmlspecialchars($sName)?></strong>
                            <?php } else { ?>
                                <a href="<?=Housekeeping::url('logs?file=' . urlencode($sName))?>">
                                    <?=htmlspecialchars($sName)?>
                                </a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
        <div class="col-md-9">
            <h4><?=htmlspecialchars($sSelected !== '' ? $sSelected : 'Log')?></h4>
            <p class="text-muted">Showing the end of the file.</p>
            <pre style="max-height: 70vh; overflow: auto; background: #111; color: #eee; padding: 1em;"><?=htmlspecialchars($sContents !== '' ? $sContents : 'Nothing to display.')?></pre>
        </div>
    </div>
</div>
