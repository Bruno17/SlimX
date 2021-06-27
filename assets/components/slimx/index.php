<?php
require_once dirname(__FILE__,4) . '/config.core.php';

// Should be set to 0 in production
error_reporting(E_ALL);
// Should be set to '0' in production
ini_set('display_errors', '1');

//$base_path = '/assets/components/slimtest';
$base_path = '/api';
$_GET['test'] = 'Test';
require_once MODX_CORE_PATH . '/components/slimx/app/index.php';


