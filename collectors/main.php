<?php
include(APPROOT . '/collectors/vendor/autoload.php');
// require_once(APPROOT. 'collectors/FleetdmHostCollector.class.inc.php');

require_once(APPROOT . 'collectors/src/FleetDMCollectionPlan.class.inc.php');

$oFLTCollector = new FleetDMCollectionPlan();
$oFLTCollector->Init();

$oFLTCollector->AddCollectorsToOrchestrator();
// Orchestrator::AddCollector(1, 'FleetdmHostCollector');
