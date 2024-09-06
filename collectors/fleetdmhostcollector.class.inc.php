<?php

// Make sure the base Collector class is included
require_once(__DIR__ . '/../lib/collector.class.inc.php');

class fleetdmhostcollector extends Collector
{
    /**
     * Constructor method.
     *
     * @param CollectorConfig $oConfig The collector configuration
     */
    public function __construct(CollectorConfig $oConfig)
    {
        parent::__construct($oConfig); // Call the parent constructor
    }

    /**
     * Main logic for running the collector and syncing data.
     *
     * @param array $syncData Data to be synchronized with iTop
     */
    public function Run(array $syncData)
    {
        // Log start of data processing
        CollectorLog::Info('FleetDM Host Collector: Starting data synchronization');

        // Iterate over each record and prepare it for synchronization
        foreach ($syncData as $data) {
            try {
                // Prepare the data for iTop synchronization
                $this->PrepareForSync($data);

                // Send data to iTop
                $this->SendToItop($data);

                // Log successful processing
                CollectorLog::Info('FleetDM Host Collector: Successfully synchronized data for ' . $data['name']);
            } catch (Exception $e) {
                // Log any errors encountered
                CollectorLog::Error('FleetDM Host Collector: Error while processing ' . $data['name'] . ' - ' . $e->getMessage());
            }
        }

        // Log completion of the process
        CollectorLog::Info('FleetDM Host Collector: Data synchronization finished');
    }

    /**
     * Prepares data for synchronization with iTop.
     *
     * @param array $data A single host's data from FleetDM
     */
    private function PrepareForSync(array &$data)
    {
        // Add any custom logic for preparing or transforming data before sync
        // For example, you may want to set some default values or modify certain fields
        if (!isset($data['status'])) {
            $data['status'] = 'active'; // Set a default status if not provided
        }

        // Additional data preparation can go here...
    }

    /**
     * Sends the prepared data to iTop using the web services.
     *
     * @param array $data The prepared data for a single host
     * @throws Exception If the synchronization fails
     */
    private function SendToItop(array $data)
    {
        // Build the request to the iTop web service using the data
        $oRestClient = new RestClient($this->oConfig->Get('itop_url'));
        $oRestClient->Login($this->oConfig->Get('itop_login'), $this->oConfig->Get('itop_password'));

        // Create or update the host record in iTop
        $response = $oRestClient->CreateOrUpdate('Server', $data);

        if (!$response->IsSuccess()) {
            throw new Exception('Failed to sync host with iTop: ' . $response->GetMessage());
        }
    }
}
