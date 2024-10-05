<?php

// Copyright (C) 2014-2020 Combodo SARL
//
//   This application is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with this application. If not, see <http://www.gnu.org/licenses/>

Orchestrator::AddRequirement('5.6.0'); // Minimum PHP version

/**
 * Base class for creating collectors which retrieve their data via a JSON files
 *
 * The minimum implementation for such a collector consists in:
 * - creating a class derived from JSONCollector
 * - configuring parameters in tag <name_of_the_collector_class> with :
 * - <command> to configure a CLI command executed before reading JSON file
 * - <jsonfile> to configure a JSON file pat
 *      or <jsonurl> to give a JSON URL with post params in <jsonpost>
 * - <path> to configuring the path in the json file to take to find the data
 * by example aa/bb for {"aa":{"bb":{mydata},"cc":"xxx"}
 *      "*" will replace any tag aa/ * /bb  for {"aa":{cc":{"bb":{mydata1}},"dd":{"bb":{mydata2}}}
 *
 */
abstract class JsonCollector extends Collector
{
	protected $sFileJson;
	protected $aJson;
	protected $sURL;
	protected $sFilePath;
	protected $aJsonKey;
	protected $aFieldsKey;
	protected $sJsonCliCommand;
	protected $iIdx;
	protected $aSynchroFieldsToDefaultValues = array();

	/**
	 * Initalization
	 */
	public function __construct()
	{
		parent::__construct();
		$this->sFileJson = null;
		$this->sURL = null;
		$this->aJson = null;
		$this->aFieldsKey = null;
		$this->iIdx = 0;
	}

	/**
	 * Runs the configured query to start fetching the data from the database
	 *
	 * @see Collector::Prepare()
	 */
	public function Prepare()
	{
		$bRet = parent::Prepare();
		if (!$bRet) {
			return false;
		}



		//**** step 1 : get all parameters from config file
		$aParamsSourceJson = $this->aCollectorConfig;
		if (isset($aParamsSourceJson["command"])) {
			$this->sJsonCliCommand = $aParamsSourceJson["command"];
		}
		if (isset($aParamsSourceJson["COMMAND"])) {
			$this->sJsonCliCommand = $aParamsSourceJson["COMMAND"];
			Utils::Log(LOG_INFO, "[".get_class($this)."] CLI command used is [".$this->sJsonCliCommand."]");
		}
		if (array_key_exists('defaults', $aParamsSourceJson)) {
			if ($aParamsSourceJson['defaults'] !== '') {
				$this->aSynchroFieldsToDefaultValues = $aParamsSourceJson['defaults'];
				if (!is_array($this->aSynchroFieldsToDefaultValues)) {
					Utils::Log(LOG_ERR, "[".get_class($this)."] defaults section configuration is not correct. please see documentation.");

					return false;
				}
			}
		}

		if (isset($aParamsSourceJson["path"])) {
			$aPath = explode('/', $aParamsSourceJson["path"]);
		}
		if (isset($aParamsSourceJson["PATH"])) {
			$aPath = explode('/', $aParamsSourceJson["PATH"]);
		}
		if ($aPath == '') {
			Utils::Log(LOG_ERR, "[".get_class($this)."] no path to find data in JSON file");
		}

		//**** step 2 : get json file
		//execute cmd before get the json
		if (!empty($this->sJsonCliCommand)) {
			utils::Exec($this->sJsonCliCommand);
		}

		//get Json file
			Utils::Log(LOG_DEBUG, 'Get params for uploading data file ');
            $aDataGet = [];
			if (isset($aParamsSourceJson["jsonpost"])) {
				$aDataGet = $aParamsSourceJson['jsonpost'];
			} else {
				$aDataGet = [];
			}
			$iSynchroTimeout = (int)Utils::GetConfigurationValue('itop_synchro_timeout', 600); // timeout in seconds, for a synchro to run

			$sBearerToken = isset($aParamsSourceJson["bearer_token"]) ? $aParamsSourceJson["bearer_token"] : null;

			$jsonUrl = Utils::GetConfigurationValue('jsonurl', '');
			$sBaseUrl = $jsonUrl . "/api/v1/fleet/labels/";
			Utils::Log(LOG_INFO, "JSON URL from config: " . $sBaseUrl);

			// Get the array of label IDs from the configuration
			$labels = Utils::GetConfigurationValue('labels', '');

			if (empty($labels)) {
				Utils::Log(LOG_ERR, "[".get_class($this)."] No labels configured. Please provide label IDs in the configuration.");
				return false;
			}
            
			// Initialize the array to hold merged data from all labels
			$aDataGet = [];

			// Iterate over each label ID to build the full URL and fetch data
			foreach ($labels as $label) {
				// Ensure label is treated as a string
				$labelId = (string)$label['fleet_dm_id']; 
				$this->sURL = $sBaseUrl . $labelId . "/hosts";
				Utils::Log(LOG_DEBUG, 'Fetching data for label ID: ' . $labelId);
				
				$iSynchroTimeout = (int) Utils::GetConfigurationValue('itop_synchro_timeout', 600); // timeout in seconds, for a synchro to run
				$sBearerToken = isset($aParamsSourceJson["bearer_token"]) ? $aParamsSourceJson["bearer_token"] : null;
				
				// Fetch the data for the current label's URL using the bearer token (if provided)
				$this->sFileJson = $this->fetchDataWithBearerToken($this->sURL, $sBearerToken);
				// Check if data fetching was successful
				if ($this->sFileJson === false) {
					Utils::Log(LOG_ERR, '[' . get_class($this) . '] Failed to get JSON file for label ID: ' . $labelId);
					return false;
				}

				// Decode the fetched JSON
				$aJson = json_decode($this->sFileJson, true);
				if ($aJson == null) {
					Utils::Log(LOG_ERR, "[" . get_class($this) . "] Failed to parse JSON file for label ID: '" . $labelId . "'. Reason: " . json_last_error_msg());
					return false;
				}

				// Merge the fetched data into $aDataGet
				if (!empty($aJson)) {
					$aDataGet = array_merge($aDataGet, $aJson); // Merging fetched data into the main array
				}

				Utils::Log(LOG_DEBUG, "Data merged from label ID: " . $labelId);
			}

			// Log the final merged data
			//Utils::Log(LOG_INFO, 'Final merged aDataGet: ' . json_encode($aDataGet));
			
			Utils::Log(LOG_INFO, 'Synchro URL (target): '.Utils::GetConfigurationValue('itop_url', array()));

		//verify the file
		if ($this->sFileJson === false) {
			Utils::Log(LOG_ERR, '['.get_class($this).'] Failed to get JSON file: '.$this->sURL);

			return false;
		}
		//**** step 3 : read json file
		$this->aJson = json_decode($this->sFileJson, true);
		Utils::Log(LOG_INFO, 'Final merged aDataGet: ' . json_encode($this->sFileJson));
		if ($this->aJson == null) {
			Utils::Log(LOG_ERR, "[".get_class($this)."] Failed to translate data from JSON file: '".$this->sURL.$this->sFilePath."'. Reason: ".json_last_error_msg());

			return false;
		}

		//Get table of Element in JSON file with a specific path
		foreach ($aPath as $sTag) {
			Utils::Log(LOG_DEBUG, "tag: ".$sTag);
			//!array_key_exists(0, $this->aJson) => element $this->aJson is not a classic array It's an array with defined keys
			if (!array_key_exists(0, $this->aJson) && $sTag != '*') {
				$this->aJson = $this->aJson[$sTag];
			} else {
				$aJsonNew = array();
				foreach ($this->aJson as $aElement) {
					if ($sTag == '*') //Any tag
					{
						array_push($aJsonNew, $aElement);
					} else {
						if (isset($aElement[$sTag])) {
							array_push($aJsonNew, $aElement[$sTag]);
						}
					}
				}
				$this->aJson = $aJsonNew;
			}
			if (count($this->aJson) == 0) {
				Utils::Log(LOG_ERR, "[".get_class($this)."] Failed to find path ".implode("/", $aPath)." until data in json file: $this->sURL $this->sFilePath.");

				return false;
			}
		}
		$this->aJsonKey = array_keys($this->aJson);
		if (isset($aParamsSourceJson["fields"])) {
			$this->aFieldsKey = $aParamsSourceJson["fields"];
		}
		if (isset($aParamsSourceJson["FIELDS"])) {
			$this->aFieldsKey = $aParamsSourceJson["FIELDS"];
		}
		Utils::Log(LOG_DEBUG, "aFieldsKey: ".json_encode($this->aFieldsKey));
		Utils::Log(LOG_DEBUG, "aJson: ".json_encode($this->aJson));
		Utils::Log(LOG_DEBUG, "aJsonKey: ".json_encode($this->aJsonKey));
		Utils::Log(LOG_DEBUG, "nb of elements:".count($this->aJson));

		$this->iIdx = 0;

		return true;
	}

	/**
	 * Fetch one element from the JSON file
	 * The first element is used to check if the columns of the result match the expected "fields"
	 *
	 * @see Collector::Fetch()
	 */
	public function Fetch()
	{
		if (empty($this->aJson)) {
			return false;
		}
		if ($this->iIdx < count($this->aJson)) {
			$aData = $this->aJson[$this->aJsonKey[$this->iIdx]];
			Utils::Log(LOG_DEBUG, '$aData: '.json_encode($aData));

			$aDataToSynchronize = $this->SearchFieldValues($aData);

			foreach ($this->aSkippedAttributes as $sCode) {
				unset($aDataToSynchronize[$sCode]);
			}

			if ($this->iIdx == 0) {
				$this->CheckColumns($aDataToSynchronize, [], 'json file');
			}
			//check if all expected fields are in array. If not add it with null value
			foreach ($this->aCSVHeaders as $sHeader) {
				if (!isset($aDataToSynchronize[$sHeader])) {
					$aDataToSynchronize[$sHeader] = null;
				}
			}

			foreach ($this->aNullifiedAttributes as $sHeader) {
				if (!isset($aDataToSynchronize[$sHeader])) {
					$aDataToSynchronize[$sHeader] = null;
				}
			}

			$this->iIdx++;

			return $aDataToSynchronize;
		}

		return false;
	}

	/**
	 * @param array $aData
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function SearchFieldValues($aData, $aTestOnlyFieldsKey = null) {
		$aDataToSynchronize = [];
	
		// Determine which fields to use
		$aCurrentFieldKeys = (is_null($aTestOnlyFieldsKey)) ? $this->aFieldsKey : $aTestOnlyFieldsKey;
		foreach ($aCurrentFieldKeys as $key => $sPath) {
			if ($this->iIdx == 0) {
				// Log key and index position
				Utils::Log(LOG_INFO, "Processing key: $key with index: " . array_search($key, $aCurrentFieldKeys));
			}
	
			// Log the JSON key path
			$aJsonKeyPath = explode('/', $sPath);
			Utils::Log(LOG_INFO, "Searching for value in path: " . implode(' -> ', $aJsonKeyPath));
	
			// Search for the value in the data
			$aValue = $this->SearchValue($aJsonKeyPath, $aData);

			if ($key == "brand_id") {
				// Check if the brand exists in iTop
				$brandExists = $this->checkBrandExists($aValue);
				if (!$brandExists) {
					// If not found, create the brand
					$createResponse = $this->createBrand($aValue);
					if ($createResponse['code'] != 0) {
						Utils::Log(LOG_ERR, "Failed to create brand: {$createResponse['message']}");
					} else {
						Utils::Log(LOG_INFO, "Brand created successfully with ID: {$createResponse['id']}");
					}
				} else {
					Utils::Log(LOG_INFO, "Brand already exists with ID: $aValue");
				}
			}
			if (empty($aValue) && array_key_exists($key, $this->aSynchroFieldsToDefaultValues)) {
				// Use default value if field is empty
				$sDefaultValue = $this->aSynchroFieldsToDefaultValues[$key];
				Utils::Log(LOG_INFO, "Using default value for $key: $sDefaultValue");
				$aDataToSynchronize[$key] = $sDefaultValue;
			} else if (!is_null($aValue)) {
				// Log the actual value found
				Utils::Log(LOG_INFO, "Found value for $key: " . print_r($aValue, true));
				$aDataToSynchronize[$key] = $aValue;
			}
		}
		$osFamilyId = $aDataToSynchronize['osfamily_id'];
		$osVersionId = $aDataToSynchronize['osversion_id'];

		// Step 1: Check if the OS Family exists
		if (!$this->checkOsFamilyExists($osFamilyId)) {
			// OS Family does not exist, create it
			$this->createOsFamily($osFamilyId);
			Utils::Log(LOG_INFO, "Created OS Family: " . $osFamilyId);
		} else {
			Utils::Log(LOG_INFO, "OS Family already exists: " . $osFamilyId);
		}

		// Step 2: Now check if the OS Version exists for the created or existing OS Family
		$oResultOsExists = $this->checkOsVersionExists($osVersionId, $osFamilyId);
		$resultOsExists = $oResultOsExists->result;
		$resultOsVersionId = $oResultOsExists->osVersionId;
		if (!$resultOsExists) {
			// OS Version does not exist, create it
			$oResult = $this->createOsVersion($osVersionId, $osFamilyId);
			Utils::Log(LOG_INFO, "createOsVersion: " . print_r($oResult, true));
			
			Utils::Log(LOG_INFO, "Created OS Version: " . $osVersionId . " for OS Family: " . $osFamilyId);
		} else {
			Utils::Log(LOG_INFO, "OS Version already exists: " . $osVersionId);
		}
		$aDataToSynchronize['osversion_id'] = $resultOsVersionId;
		Utils::Log(LOG_INFO, 'Final data to synchronize: ' . json_encode($aDataToSynchronize, JSON_PRETTY_PRINT));
		return $aDataToSynchronize;
	}

	private function checkBrandExists($brandName) {
		// Use the RestClient to search for the brand by name
		$restClient = new RestClient();
		// Prepare a search query to find the brand by its name
		$query = sprintf("SELECT Brand WHERE name='%s'", addslashes($brandName));
		// Perform the search
		$result = $restClient->Get('Brand', $query);
		// Assuming code 0 means success and we have results
		return $result['code'] == 0 && !empty($result['objects']);
	}
	private function createBrand($brandId) {
		$restClient = new RestClient();
		$validAttributes = [
			'name' => $brandId
		];
		return $restClient->Create('Brand', $validAttributes, 'Created brand from synchronization process');
	}
	private function createOsFamily($osFamilyId) {
		// Initialize the RestClient
		$restClient = new RestClient();
	
		// Prepare valid attributes for the OS Family
		$validAttributes = [
			'name' => $osFamilyId  // Adjust this key based on your iTop OS Family model
		];
	
		// Create the OS Family using the RestClient
		return $restClient->Create('OSFamily', $validAttributes, 'Created OS Family from synchronization process');
	}
	private function checkOsFamilyExists($osFamilyName) {
		// Use the RestClient to search for the OS Family by name
		$restClient = new RestClient();
		
		// Prepare a search query to find the OS Family by its name
		$query = sprintf("SELECT OSFamily WHERE name='%s'", addslashes($osFamilyName));
		
		// Perform the search
		$result = $restClient->Get('OSFamily', $query);
		
		// Assuming code 0 means success and we have results
		return $result['code'] == 0 && !empty($result['objects']);
	}
	private function createOsVersion($osVersionId, $osFamilyName) {
		// Initialize the RestClient
		$restClient = new RestClient();

		$query = sprintf("SELECT OSFamily WHERE name='%s'", addslashes($osFamilyName));
		$familyResult = $restClient->Get('OSFamily', $query);
		$osFamilyId = $familyResult['objects'][array_key_first($familyResult['objects'])]['key']; // Adjust based on your API response structure

        // Log the retrieved OS Family ID for debugging
        Utils::Log(LOG_INFO, "Retrieved OS Family ID: " . $osFamilyId);
		
		// Prepare valid attributes for the OS Version, including the OS family ID
		$validAttributes = [
			'name' => $osVersionId,  // Adjust this key based on your iTop OS Version model
			'osfamily_id' => $osFamilyId  // Associate with the specific OS family
		];
		// Create the OS Version using the RestClient
		return $restClient->Create('OSVersion', $validAttributes, 'Created OS Version from synchronization process');
	}
	private function checkOsVersionExists($osVersionName, $osFamilyName) {
		// Use the RestClient to search for the OS Version by name within the specific OS Family
		$restClient = new RestClient();
		$query = sprintf("SELECT OSFamily WHERE name='%s'", addslashes($osFamilyName));
		$familyResult = $restClient->Get('OSFamily', $query);
		$osFamilyId = $familyResult['objects'][array_key_first($familyResult['objects'])]['key']; // Adjust based on your API response structure

		// Prepare a search query to find the OS Version by its name within the specified OS Family
		$query = sprintf("SELECT OSVersion WHERE name='%s' AND osfamily_id='%s'", 
						 addslashes($osVersionName), 
						 addslashes($osFamilyId));
		
		// Perform the search
		$result = $restClient->Get('OSVersion', $query);
		// Check if the result contains objects
		if (empty($result['objects'])) {
			Utils::Log(LOG_INFO, "OS Version '$osVersionName' not found in OS Family '$osFamilyName'.");
			return null; // or handle it as necessary
		}
		$osVersionId = $result['objects'][array_key_first($result['objects'])]['key'];
		Utils::Log(LOG_INFO, "osVersionId: " . print_r($osVersionId, true));
		
		// Assuming code 0 means success and we have results
		if ($result['code'] == 0 && is_array($result['objects']) && !empty($result['objects'])) {
			return (object) [
				'result' => $result,
				'osVersionId' => $osVersionId
			];
		}
	}
	private function SearchValue($aJsonKeyPath, $aData){
		$sTag = array_shift($aJsonKeyPath);

		if($sTag === '*'){
			foreach ($aData as $sKey => $aDataValue){
				$aCurrentValue = $this->SearchValue($aJsonKeyPath, $aDataValue);
				if (null !== $aCurrentValue){
					return $aCurrentValue;
				}
			}
			return null;
		}

		if (is_int($sTag)
			&& array_is_list($aData)
			&&  array_key_exists((int) $sTag, $aData)
		) {
			$aValue = $aData[(int) $sTag];
		} else if(($sTag != '*')
			&& is_array($aData)
			&& isset($aData[$sTag])
		){
			$aValue = $aData[$sTag];
		} else {
			return null;
		}

		if (empty($aJsonKeyPath)){
			return (is_array($aValue)) ? null : $aValue;
		}

		return $this->SearchValue($aJsonKeyPath, $aValue);
	}

	/**
	 * Determine if a given attribute is allowed to be missing in the data datamodel.
	 *
	 * The implementation is based on a predefined configuration parameter named from the
	 * class of the collector (all lowercase) with _ignored_attributes appended.
	 *
	 * Example: here is the configuration to "ignore" the attribute 'location_id' for the class MyJSONCollector:
	 * <myjsoncollector_ignored_attributes type="array">
	 *    <attribute>location_id</attribute>
	 * </myjsoncollector_ignored_attributes>
	 *
	 * @param string $sAttCode
	 *
	 * @return boolean True if the attribute can be skipped, false otherwise
	 */
	public function AttributeIsOptional($sAttCode)
	{
		$aIgnoredAttributes = Utils::GetConfigurationValue(get_class($this)."_ignored_attributes", null);
		if ($aIgnoredAttributes === null) {
			// Try all lowercase
			$aIgnoredAttributes = Utils::GetConfigurationValue(strtolower(get_class($this))."_ignored_attributes", null);
		}
		if (is_array($aIgnoredAttributes)) {
			if (in_array($sAttCode, $aIgnoredAttributes)) {
				return true;
			}
		}

		return parent::AttributeIsOptional($sAttCode);
	}

	private function fetchDataWithBearerToken($url, $bearerToken)
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

}
