<?php
require_once(APPROOT.'collectors/fleetdmhostcollector.class.inc.php');
$oFLTCollector = new fleetdmhostcollector();
$oFLTCollector->Run();
Orchestrator::AddCollector(1, 'fleetdmhostcollector');