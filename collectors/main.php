<?php
require_once(APPROOT. 'collectors/FleetdmHostCollector.class.inc.php');
include(APPROOT . '/collectors/vendor/autoload.php');

$oFLTCollector = new FleetdmHostCollector();
$oFLTCollector->Run();
Orchestrator::AddCollector(1, 'FleetdmHostCollector');
