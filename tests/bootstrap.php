<?php
if (!($omekaDir = getenv('OMEKA_DIR'))) {
    $omekaDir = dirname(dirname(dirname(dirname(__FILE__))));
}

define('ARCHIVE_REPERTORY_DIR', dirname(dirname(__FILE__)));

require_once $omekaDir . '/application/tests/bootstrap.php';
require_once 'ArchiveRepertory_Test_AppTestCase.php';
