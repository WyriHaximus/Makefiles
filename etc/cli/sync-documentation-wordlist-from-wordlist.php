<?php

declare(strict_types=1);

use WyriHaximus\Makefiles\Documentation\WordlistDocumentationConfigSync;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

WordlistDocumentationConfigSync::sync(getcwd());
