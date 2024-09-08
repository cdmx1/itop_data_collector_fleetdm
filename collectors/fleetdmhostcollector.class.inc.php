<?php

// Ensure the base JsonCollector class is included
require_once(APPROOT . '/core/collector.class.inc.php');

class fleetdmhostcollector extends JsonCollector
{
    public function __construct()
    {
        parent::__construct();
        Utils::Log(LOG_INFO, "FleetDMHostCollector constructor called.");
    }

    /**
     * Initialize collection plan
     *
     * @return void
     * @throws \IOException
     */
    // Updated method signature to match the parent class
    public function Run(): void
    {
        // Debugging output
        $jsonUrl = Utils::GetConfigurationValue('jsonurl', '');
        Utils::Log(LOG_INFO, "JSON URL from config: " . $jsonUrl);

        Utils::Log(LOG_INFO, "Run method called.");

        // Fetching sync data from a different source or configuration
        $syncData = $this->getSyncData();

        foreach ($syncData as $data) {
            Utils::Log(LOG_INFO, "Syncing data: ");
            Utils::Log(LOG_INFO, print_r($data, true));

            try {
                $this->PrepareForSync($data);
                $this->SendToItop($data);

                Utils::Log(LOG_INFO, "Successfully synchronized data for " . $data['name']);
            } catch (Exception $e) {
                Utils::Log(LOG_ERR, "Error while processing " . $data['name'] . " - " . $e->getMessage());
            }
        }

        Utils::Log(LOG_INFO, "Data synchronization finished");
    }

    private function PrepareForSync(array &$data): void
    {
        Utils::Log(LOG_INFO, "Preparing data for sync: ");
        Utils::Log(LOG_INFO, print_r($data, true));

        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }
    }

    private function SendToItop(array $data): void
    {
        Utils::Log(LOG_DEBUG, "Sending data to iTop: ");
        Utils::Log(LOG_DEBUG, print_r($data, true));
    
        $itopUrl = Utils::GetConfigurationValue('itop_url', '');
        $itopLogin = Utils::GetConfigurationValue('itop_login', '');
        $itopPassword = Utils::GetConfigurationValue('itop_password', '');
    
        $oRestClient = new RestClient();
        $result = $oRestClient->CheckCredentials($itopLogin, $itopPassword);
    
        if ($result['code'] != 0) {
            $errorMsg = 'Failed to authenticate with iTop: ' . $result['message'];
            Utils::Log(LOG_ERR, $errorMsg);
            throw new Exception($errorMsg);
        }
    
        $response = $oRestClient->CreateOrUpdate('Server', $data, '');
    
        if (!$response['code'] == 0) {
            $errorMsg = 'Failed to sync host with iTop: ' . $response['message'];
            Utils::Log(LOG_ERR, $errorMsg);
            throw new Exception($errorMsg);
        }
    
        Utils::Log(LOG_INFO, 'Successfully synchronized data with iTop.');
    }

    // Define a method to fetch sync data if needed
    private function getSyncData(): array
    {
        // Implementation to fetch synchronization data
        return [
            ['name' => 'Server1', 'status' => 'active'],
            ['name' => 'Server2', 'status' => 'inactive'],
        ];
    }
}
