<?php
require_once __DIR__ . '/includes/layout.php';
require_role(['school_admin','super_admin','principal']);
admin_header('Settings'); ?><div class="content"><div class="panel"><h2>Workspace settings</h2><p class="empty">Settings are scoped to the authenticated school. Use the existing API and migration layer for additional configuration fields.</p></div></div><?php admin_footer();
