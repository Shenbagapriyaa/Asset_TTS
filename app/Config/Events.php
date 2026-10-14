<?php

namespace Config;

use CodeIgniter\Events\Events;

Events::on('pre_system', static function (): void {
    // Keep this file for application lifecycle hooks.
});
