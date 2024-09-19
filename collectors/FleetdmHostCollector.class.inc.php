<?php

// Ensure the base JsonCollector class is included
require_once(APPROOT . '/core/collector.class.inc.php');
require_once(APPROOT . '/StatusEnum.php');
require_once(APPROOT . '/BrandsEnum.php');

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

        foreach ($labels as $label) {
            $sync_data = $this->getSyncData($label['fleet_dm_id']);

            foreach ($sync_data as $host) {
                $json_template = file_get_contents(__DIR__ ."/json_source/{$label['itop_json_source']}");
                $data = $this->updateJsonTemplate($json_template, $label['api_params'], $host, $label['default_values']);
                Utils::Log(LOG_INFO, "Syncing data for : {$label['name']}");
                Utils::Log(LOG_INFO, print_r($data, true));

                try {
                    $this->PrepareForSync($data);
                    $this->SendToItop($label['name'], $data);

                    Utils::Log(LOG_INFO, "Successfully synchronized data for " . $label['name']);
                } catch (Exception $e) {
                    Utils::Log(LOG_ERR, "Error while processing " . $label['name'] . " - " . $e->getMessage());
                }
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

        $statuses = StatusEnum::cases();
        $statuses =
        array_combine(
            array_map(fn($case) => $case->name, $statuses),  // Keys: Enum names
            array_map(fn($case) => $case->value, $statuses)  // Values: Enum values
        );


        if (!isset($data['status'])) {
            $data['status'] = 'production';
        } else {
            $data['status'] =
            isset($statuses[$data['status']]) ? $statuses[$data['status']] : $statuses['online'];

        }

        $brands = BrandsEnum::cases();
        $brands =
        array_combine(
            array_map(fn($case) => $case->name, $brands),  // Keys: Enum names
            array_map(fn($case) => $case->value, $brands)  // Values: Enum values
        );

        var_dump("Brands", $brands);
        var_dump("data", $data);
        if (!isset($data['brand_id']) && isset($data['brand'])) {
            $data['brand_id'] = isset($brands[$data['brand']]) ? $brands[$data['brand']] : $brands['other'];
            unset($data['brand']);
        }
        $data['org_id'] = Utils::GetConfigurationValue('org_id', '');
        var_dump("data", $data);
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
        $jsonUrl = Utils::GetConfigurationValue('jsonurl', ''); // Print "Try programiz.pro" message
        echo "Try programiz.pro";
        $data = json_decode($this->fetchDataWithBearerToken("{$jsonUrl}/api/v1/fleet/labels/{$label_id}/hosts"), true);
        return $data[Utils::GetConfigurationValue('path', 'hosts')];
    }

    private function fetchDataWithBearerToken($url)
    {
        // Define your Bearer token inside the function
        $bearerToken = Utils::GetConfigurationValue('jsonpost', '')['api_token'];

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
                $data[$key] = $this->replacePlaceholders($value, $placeholders);
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
     * @param string $apiData API data
     * @param array $defaultValues The default values to use when API data is missing
     * @return array The updated JSON string
     */
    private function updateJsonTemplate($jsonTemplate, $mappingArray, $apiData, $defaultValues)
    {
        // Build placeholders array with default values
        $placeholders = $this->buildPlaceholdersArray($mappingArray, $apiData, $defaultValues);

        // Decode the JSON template into an associative array
        $data = json_decode($jsonTemplate, true);

        // Replace the placeholders in the JSON structure
        $updatedData = $this->replacePlaceholders($data, $placeholders);

        // Encode back to JSON and return
        return $updatedData;
    }
}
