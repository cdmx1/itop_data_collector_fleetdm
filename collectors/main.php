<?php
// Include necessary files for the collector and configuration
require_once(__DIR__ . '/conf/params.local.xml'); // Load the collector configuration
require_once(__DIR__ . '/collectors/fleetdmhostcollector.class.inc.php'); // Load the FleetDM host collector class

// Initialize the configuration object
$oConfig = new CollectorConfig(__DIR__ . '/conf/params.local.xml');

// Create a new instance of the FleetDM host collector
$fleetDMCollector = new fleetdmhostcollector($oConfig);

// Fetch the data to synchronize
$dataToSync = []; // Fetch your FleetDM host data here, for example through an API call or JSON parser

// Run the collector to synchronize the fetched data with iTop
try {
    $fleetDMCollector->Run($dataToSync);
    echo "FleetDM Host synchronization completed successfully.\n";
} catch (Exception $e) {
    echo "Error during FleetDM Host synchronization: " . $e->getMessage() . "\n";
}