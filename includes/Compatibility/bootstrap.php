<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/WordPressReportSerializer.php';
require_once __DIR__ . '/WordPressHttpTransport.php';
require_once __DIR__ . '/ProviderSignals.php';
require_once __DIR__ . '/DoctorReportStore.php';
require_once __DIR__ . '/CompatibilityAdminController.php';

(new \TCT\Compatibility\WordPress\CompatibilityAdminController())->register();
