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

        $response = $oRestClient->Create('Server', $data, '');

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
        var_dump("Fetched data\n", $this->Fetch());
        var_dump("Params\n", Utils::GetConfigurationValue('host_categories', ''));

        // $host_categories = Utils::GetConfigurationValue('host_categories', '');

        // foreach ($host_categories as $hostCategory) {
        //     # code...
        // }

        var_dump("data", $this->fetchDataWithBearerToken("https://fdm.cdmx.io/api/v1/fleet/labels/17/hosts"));
        // Implementation to fetch synchronization data
        return [
            ['name' => 'New Test', 'status' => 'production', 'org_id'=> 1]
        ];
    }

    private function fetchDataWithBearerToken($url)
    {
        // Define your Bearer token inside the function
        $bearerToken = Utils::GetConfigurationValue('jsonpost', '')['api_token'];
        var_dump("token", $bearerToken);

        // Initialize cURL
        $ch = curl_init($url);

        // Set the cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $bearerToken",  // Set the Authorization header with Bearer token
            "Content-Type: application/json"       // Set content type to JSON (optional based on your API)
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Execute the request and get the response
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        } else {
            // Return the response
            return $response;
        }

        // Close the cURL session
        curl_close($ch);
    }
}
