<?php

// Ensure the base JsonCollector class is included
require_once(APPROOT . '/core/collector.class.inc.php');

class FleetdmHostCollector extends JsonCollector
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

        $labels = Utils::GetConfigurationValue('labels', '');
        var_dump("Labels", $labels);

        foreach ($labels as $label) {
            $sync_data = $this->getSyncData($label['fleet_dm_id']);
            var_dump("Sync Data", $sync_data);

            foreach ($sync_data as $host) {
                $this->SendToItop($label['name'], ["name"=>"Name", "org_id"=>1]);
                var_dump("host");
            }
        }

        // Fetching sync data from a different source or configuration

        // foreach ($syncData as $data) {
        //     Utils::Log(LOG_INFO, "Syncing data: ");
        //     Utils::Log(LOG_INFO, print_r($data, true));

        //     try {
        //         $this->PrepareForSync($data);
        //         $this->SendToItop($data);

        //         Utils::Log(LOG_INFO, "Successfully synchronized data for " . $data['name']);
        //     } catch (Exception $e) {
        //         Utils::Log(LOG_ERR, "Error while processing " . $data['name'] . " - " . $e->getMessage());
        //     }
        // }

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

    private function SendToItop(string $label_name, array $data): void
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

        $response = $oRestClient->Create($label_name, $data, '');

        if (!$response['code'] == 0) {
            $errorMsg = 'Failed to sync host with iTop: ' . $response['message'];
            Utils::Log(LOG_ERR, $errorMsg);
            throw new Exception($errorMsg);
        }

        Utils::Log(LOG_INFO, 'Successfully synchronized data with iTop.');
    }

    // Define a method to fetch sync data if needed
    private function getSyncData($label_id): array
    {
        $jsonUrl = Utils::GetConfigurationValue('jsonurl', '');
        $data = json_decode($this->fetchDataWithBearerToken("{$jsonUrl}/api/v1/fleet/labels/{$label_id}/hosts"), true);
        return $data[Utils::GetConfigurationValue('path', 'hosts')];
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



    /**
     * Replace placeholders in the JSON template with actual values.
     *
     * @param mixed $data The JSON template data (could be array or string)
     * @param array $placeholders Array of placeholders and their values
     * @return mixed The JSON data with placeholders replaced
     */
    private function replacePlaceholders($data, $placeholders)
    {
        // If the data is an array, recursively process it
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = replacePlaceholders($value, $placeholders);
            }
        }
        // If the data is a string, replace placeholders
        elseif (is_string($data)) {
            foreach ($placeholders as $placeholder => $actualValue) {
                // Add $ prefix and suffix when replacing placeholders
                $data = str_replace('$' . $placeholder . '$', $actualValue, $data);
            }
        }
        return $data;
    }

    /**
     * Build an array of placeholders from the mapping array and API data.
     *
     * @param array $mappingArray The mapping array of template keys to API keys
     * @param array $apiData The data fetched from the API
     * @param array $defaultValues The default values for each placeholder
     * @return array The placeholders array where keys are placeholders and values are API data or defaults
     */
    private function buildPlaceholdersArray($mappingArray, $apiData, $defaultValues)
    {
        $placeholders = [];
        foreach ($mappingArray as $placeholder => $apiKey) {
            // Use the API value if available, otherwise use the default value
            $placeholders[$placeholder] = isset($apiData[$apiKey]) ? $apiData[$apiKey] : $defaultValues[$placeholder];
        }
        return $placeholders;
    }

    /**
     * Main function to update the JSON template with actual data from an API.
     *
     * @param string $jsonTemplate The JSON template with placeholders
     * @param array $mappingArray The mapping array linking template keys to API keys
     * @param string $apiUrl The URL to fetch the API data
     * @param array $defaultValues The default values to use when API data is missing
     * @return string The updated JSON string
     */
    private function updateJsonTemplate($jsonTemplate, $mappingArray, $apiUrl, $defaultValues)
    {
        // Fetch data from the API
        $apiData = $this->fetchDataWithBearerToken($apiUrl);

        // Build placeholders array with default values
        $placeholders = $this->buildPlaceholdersArray($mappingArray, $apiData, $defaultValues);

        // Decode the JSON template into an associative array
        $data = json_decode($jsonTemplate, true);

        // Replace the placeholders in the JSON structure
        $updatedData = replacePlaceholders($data, $placeholders);

        // Encode back to JSON and return
        return json_encode($updatedData, JSON_PRETTY_PRINT);
    }
}
