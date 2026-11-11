<?php

declare(strict_types=1);

/** @var array $_ */
?>
<div id="app-content" class="oab-timesheet-app-content">
    <div id="oab-timesheet-app"
         data-user-uid="<?php p($_['userUid']); ?>"
         data-user-name="<?php p($_['userName']); ?>"
         data-user-email="<?php p($_['userEmail']); ?>"
         data-is-manager="<?php p($_['isManager'] ? 'true' : 'false'); ?>">
        <div class="ts-loading-placeholder">
            <div class="icon-loading"></div>
            <p>Carregando Timesheet...</p>
        </div>
    </div>
</div>
