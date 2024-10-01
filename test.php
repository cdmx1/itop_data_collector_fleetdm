<?php

/**
 * Fetch data from an API using CURL
 *
 * @param string $apiUrl The API URL to fetch data from
 * @return array The API data as an associative array
 */
function fetchApiData($apiUrl)
{
    $curl = curl_init();

    // Set CURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    // Execute the request and get the response
    $response = curl_exec($curl);
    curl_close($curl);

    // Decode JSON response to associative array
    return json_decode($response, true);
}

/**
 * Replace placeholders in the JSON template with actual values.
 *
 * @param mixed $data The JSON template data (could be array or string)
 * @param array $placeholders Array of placeholders and their values
 * @return mixed The JSON data with placeholders replaced
 */
function replacePlaceholders($data, $placeholders)
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
function buildPlaceholdersArray($mappingArray, $apiData, $defaultValues)
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
function updateJsonTemplate($jsonTemplate, $mappingArray, $apiUrl, $defaultValues)
{
    // Fetch data from the API
    $apiData = fetchApiData($apiUrl);

    // Build placeholders array with default values
    $placeholders = buildPlaceholdersArray($mappingArray, $apiData, $defaultValues);

    // Decode the JSON template into an associative array
    $data = json_decode($jsonTemplate, true);

    // Replace the placeholders in the JSON structure
    $updatedData = replacePlaceholders($data, $placeholders);

    // Encode back to JSON and return
    return json_encode($updatedData, JSON_PRETTY_PRINT);
}

// Example dynamic JSON template
$jsonTemplate = '{
    "name": "$prefix$:AssetCategory",
    "description": "$prefix$ Data Collector (v. $version$): Asset Categories",
    "status": "$synchro_status$",
    "user_id": "$synchro_user$",
    "notify_contact_id": "$contact_to_notify$",
    "scope_class": "FleetDMAssetCategory",
    "database_table_name":  "synchro_data_assetcategory_$prefix$$suffix$",
    "scope_restriction": "",
    "full_load_periodicity": "$full_load_interval$",
    "reconciliation_policy": "use_attributes",
    "action_on_zero": "create",
    "action_on_one": "update",
    "action_on_multiple": "error",
    "delete_policy": "ignore",
    "attribute_list": [
        {
            "attcode": "name",
            "update": "1",
            "reconcile": "1",
            "update_policy": "master_locked",
            "finalclass": "SynchroAttribute"
        },
        {
            "attcode": "target_class",
            "update": "0",
            "reconcile": "0",
            "update_policy": "master_locked",
            "finalclass": "SynchroAttribute"
        }, {
            "attcode": "description",
            "update": "1",
            "reconcile": "0",
            "update_policy": "master_locked",
            "finalclass": "SynchroAttribute"
        }
    ],
    "user_delete_policy": "nobody",
    "url_icon": "",
    "url_application": ""
}';

// Example API URL
$apiUrl = "https://api.example.com/data";

// Example mapping array without $ prefix and suffix
$mappingArray = [
    'prefix' => 'api_prefix',
    'version' => 'api_version',
    'synchro_status' => 'status',
    'synchro_user' => 'user_id',
    'contact_to_notify' => 'notify_contact_id',
    'suffix' => 'api_suffix',
    'full_load_interval' => 'load_interval'
];

// Example default values for placeholders
$defaultValues = [
    'prefix' => 'DefaultPrefix',
    'version' => '1.0',
    'synchro_status' => 'inactive',
    'synchro_user' => 'default_user',
    'contact_to_notify' => 'default_contact',
    'suffix' => 'DEF',
    'full_load_interval' => 'weekly'
];

// Update the JSON template using API data, mapping array, and default values
$updatedJson = updateJsonTemplate($jsonTemplate, $mappingArray, $apiUrl, $defaultValues);

// Output the updated JSON
echo $updatedJson;
