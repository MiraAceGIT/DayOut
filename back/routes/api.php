<?php
declare(strict_types=1);

// docs.php a part : charge par public/index.php seulement si DOCS_ENABLED.
foreach (glob(__DIR__ . '/*.php') ?: [] as $file) {
    if ($file !== __FILE__ && basename($file) !== 'docs.php') {
        require_once $file;
    }
}
