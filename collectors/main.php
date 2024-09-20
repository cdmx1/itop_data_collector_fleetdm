<?php
require_once(APPROOT. 'collectors/FleetdmHostCollector.class.inc.php');
$oFLTCollector = new FleetdmHostCollector();
$oFLTCollector->Run();
Orchestrator::AddCollector(1, 'FleetdmHostCollector');
