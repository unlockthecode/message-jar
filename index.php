<?php
declare(strict_types=1);

// Root-level shim: InfinityFree's Apache looks for htdocs/index.php.
// The real entry point lives in public/. This file loads it.
require __DIR__ . '/public/index.php';