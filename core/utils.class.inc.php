<?php

// Copyright (C) 2014 Combodo SARL
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

define('LOG_NONE', -1);

define('CONF_DIR', APPROOT.'conf/');
require_once APPROOT.'core/ioexception.class.inc.php';
require_once APPROOT.'core/dopostrequestservice.class.inc.php';

class Utils
{
    public static $iConsoleLogLevel = LOG_INFO;
    public static $iSyslogLogLevel = LOG_NONE;
    public static $iEventIssueLogLevel = LOG_NONE;
    public static $sProjectName = '';
    public static $sStep = '';
    public static $oCollector = '';
    protected static $oConfig;
    protected static $aConfigFiles = [];
    protected static $iMockLogLevel = LOG_ERR;

    protected static $oMockedLogger;

    /**
     * @since 1.3.0 N°6012
     */
    protected static $oMockedDoPostRequestService;

    /**
     * @var string Keeps track of the latest date the datamodel has been installed/updated
     *             (in order to check which modules were installed with it)
     */
    protected static $sLastInstallDate;

    public static function SetProjectName($sProjectName)
    {
        if (null != $sProjectName) {
            self::$sProjectName = $sProjectName;
        }
    }

    public static function SetCollector($oCollector, $sStep = '')
    {
        self::$oCollector = $oCollector;
        self::$sStep = $sStep;
    }

    public static function ReadParameter($sParamName, $defaultValue)
    {
        global $argv;

        $retValue = $defaultValue;
        if (is_array($argv)) {
            foreach ($argv as $iArg => $sArg) {
                if (preg_match('/^--'.$sParamName.'=(.*)$/', $sArg, $aMatches)) {
                    $retValue = $aMatches[1];
                }
            }
        }

        return $retValue;
    }

    public static function ReadBooleanParameter($sParamName, $defaultValue)
    {
        global $argv;

        $retValue = $defaultValue;
        if (is_array($argv)) {
            foreach ($argv as $iArg => $sArg) {
                if (preg_match('/^--'.$sParamName.'$/', $sArg, $aMatches)) {
                    $retValue = true;
                } elseif (preg_match('/^--'.$sParamName.'=(.*)$/', $sArg, $aMatches)) {
                    $retValue = (0 != $aMatches[1]);
                }
            }
        }

        return $retValue;
    }

    public static function CheckParameters($aOptionalParams)
    {
        global $argv;

        $aUnknownParams = [];
        if (is_array($argv)) {
            foreach ($argv as $iArg => $sArg) {
                if (0 == $iArg) {
                    continue;
                } // Skip program name
                if (preg_match('/^--([A-Za-z0-9_]+)$/', $sArg, $aMatches)) {
                    // Looks like a boolean parameter
                    if (!array_key_exists($aMatches[1], $aOptionalParams) || ('boolean' != $aOptionalParams[$aMatches[1]])) {
                        $aUnknownParams[] = $sArg;
                    }
                } elseif (preg_match('/^--([A-Za-z0-9_]+)=(.*)$/', $sArg, $aMatches)) {
                    // Looks like a regular parameter
                    if (!array_key_exists($aMatches[1], $aOptionalParams) || ('boolean' == $aOptionalParams[$aMatches[1]])) {
                        $aUnknownParams[] = $sArg;
                    }
                } else {
                    $aUnknownParams[] = $sArg;
                }
            }
        }

        return $aUnknownParams;
    }

    /**
     * Init the console log level.
     *
     * Defaults to LOG_INFO if `console_log_level` is not configured
     * Can be overridden by `console_log_level` commandline argument.
     *
     * @throws Exception
     */
    public static function InitConsoleLogLevel()
    {
        $iDefaultConsoleLogLevel = static::GetConfigurationValue('console_log_level', LOG_INFO);
        static::$iConsoleLogLevel = static::ReadParameter('console_log_level', $iDefaultConsoleLogLevel);
    }

    /**
     * Logs a message to the centralized log for the application, with the given priority.
     *
     * @param int    $iPriority Use the LOG_* constants for priority e.g. LOG_WARNING, LOG_INFO, LOG_ERR... (see:
     *                          www.php.net/manual/en/function.syslog.php)
     * @param string $sMessage  The message to log
     *
     * @return void
     *
     * @throws Exception
     */
    public static function Log($iPriority, $sMessage)
    {
        // testing only LOG_ERR
        if (self::$oMockedLogger) {
            if ($iPriority <= self::$iMockLogLevel) {
                var_dump($sMessage);
                self::$oMockedLogger->Log($iPriority, $sMessage);
            }
        }

        switch ($iPriority) {
            case LOG_EMERG:
                $sPrio = 'Emergency';
                break;

            case LOG_ALERT:
                $sPrio = 'Alert';
                break;
            case LOG_CRIT:
                $sPrio = 'Critical Error';
                break;

            case LOG_ERR:
                $sPrio = 'Error';
                break;

            case LOG_WARNING:
                $sPrio = 'Warning';
                break;

            case LOG_NOTICE:
                $sPrio = 'Notice';
                break;

            case LOG_INFO:
                $sPrio = 'Info';
                break;

            case LOG_DEBUG:
                $sPrio = 'Debug';
                break;
        }

        if ($iPriority <= self::$iConsoleLogLevel) {
            $log_date_format = self::GetConfigurationValue('console_log_dateformat', '[Y-m-d H:i:s]');
            $txt = date($log_date_format)."\t[".$sPrio."]\t".$sMessage."\n";
            echo $txt;
        }

        if ($iPriority <= self::$iSyslogLogLevel) {
            openlog('iTop Data Collector', LOG_PID, LOG_USER);
            syslog($iPriority, $sMessage);
            closelog();
        }

        if ($iPriority <= self::$iEventIssueLogLevel) {
            Utils::CreateEventIssue($sMessage);
        }
    }

    private static function CreateEventIssue($sMessage)
    {
        $sProjectName = self::$sProjectName;
        $sCollectorName = (null == self::$oCollector) ? '' : get_class(self::$oCollector);
        $sStep = self::$sStep;

        $aFields = [
            'message' => "$sMessage",
            'userinfo' => 'Collector',
            'issue' => "$sStep-$sCollectorName",
            'impact' => "$sProjectName",
        ];

        $oClient = new RestClient();
        $oClient->Create('EventIssue', $aFields, "create event issue from collector $sCollectorName execution.");
    }

    public static function MockLog($oMockedLogger, $iMockLogLevel = LOG_ERR)
    {
        self::$oMockedLogger = $oMockedLogger;
        self::$iMockLogLevel = $iMockLogLevel;
    }

    /**
     * @param DoPostRequestService|null $oMockedDoPostRequestService
     *
     * @since 1.3.0 N°6012
     *
     * @return void
     */
    public static function MockDoPostRequestService($oMockedDoPostRequestService)
    {
        self::$oMockedDoPostRequestService = $oMockedDoPostRequestService;
    }

    /**
     * Load the configuration from the various XML configuration files.
     *
     * @return Parameters
     *
     * @throws Exception
     */
    public static function LoadConfig()
    {
        $sCustomConfigFile = Utils::ReadParameter('config_file', null);

        self::$aConfigFiles[] = CONF_DIR.'params.distrib.xml';
        self::$oConfig = new Parameters(CONF_DIR.'params.distrib.xml');
        if (file_exists(APPROOT.'collectors/params.distrib.xml')) {
            self::MergeConfFile(APPROOT.'collectors/params.distrib.xml');
        }
        if (file_exists(APPROOT.'collectors/extensions/params.distrib.xml')) {
            self::MergeConfFile(APPROOT.'collectors/extensions/params.distrib.xml');
        }
        if (null !== $sCustomConfigFile) {
            // A custom config file was supplied on the command line
            if (file_exists($sCustomConfigFile)) {
                self::MergeConfFile($sCustomConfigFile);
            } else {
                throw new Exception("The specified configuration file '$sCustomConfigFile' does not exist.");
            }
        } elseif (file_exists(CONF_DIR.'params.local.xml')) {
            self::MergeConfFile(CONF_DIR.'params.local.xml');
        }

        return self::$oConfig;
    }

    private static function MergeConfFile($sFilePath)
    {
        self::$aConfigFiles[] = $sFilePath;
        $oLocalConfig = new Parameters($sFilePath);
        self::$oConfig->Merge($oLocalConfig);
    }

    /**
     * Get the value of a configuration parameter.
     *
     * @param string $sCode
     *
     * @throws Exception
     */
    public static function GetConfigurationValue($sCode, $defaultValue = '')
    {
        if (null == self::$oConfig) {
            self::LoadConfig();
        }

        $value = self::$oConfig->Get($sCode, $defaultValue);
        $value = self::Substitute($value);

        return $value;
    }

    /**
     * @since 1.3.0 N°6012
     */
    public static function GetCredentials(): array
    {
        $sToken = Utils::GetConfigurationValue('itop_token', '');
        if (strlen($sToken) > 0) {
            return [
                'auth_token' => $sToken,
            ];
        }

        return [
            'auth_user' => Utils::GetConfigurationValue('itop_login', ''),
            'auth_pwd' => Utils::GetConfigurationValue('itop_password', ''),
        ];
    }

    /**
     * @since 1.3.0 N°6012
     */
    public static function GetLoginMode(): string
    {
        $sLoginform = Utils::GetConfigurationValue('itop_login_mode', '');
        if (strlen($sLoginform) > 0) {
            return $sLoginform;
        }

        $sToken = Utils::GetConfigurationValue('itop_token', '');
        if (strlen($sToken) > 0) {
            return 'token';
        }

        return 'form';
    }

    /**
     * Dump information about the configuration (value of the parameters).
     *
     * @return string
     *
     * @throws Exception
     */
    public static function DumpConfig()
    {
        if (null == self::$oConfig) {
            self::LoadConfig();
        }

        return self::$oConfig->Dump();
    }

    /**
     * Get the ordered list of configuration files loaded.
     *
     * @return string
     *
     * @throws Exception
     */
    public static function GetConfigFiles()
    {
        if (null == self::$oConfig) {
            self::LoadConfig();
        }

        return self::$aConfigFiles;
    }

    protected static function Substitute($value)
    {
        if (is_array($value)) {
            // Recursiverly process each entry
            foreach ($value as $key => $val) {
                $value[$key] = self::Substitute($val);
            }
        } elseif (is_string($value)) {
            preg_match_all('/\$([A-Za-z0-9-_]+)\$/', $value, $aMatches);
            $aReplacements = [];
            if (count($aMatches) > 0) {
                foreach ($aMatches[1] as $sSubCode) {
                    $aReplacements['$'.$sSubCode.'$'] = self::GetConfigurationValue($sSubCode, '#ERROR_UNDEFINED_PLACEHOLDER_'.$sSubCode.'#');
                }
                $value = str_replace(array_keys($aReplacements), $aReplacements, $value);
            }
        } else {
            // Do nothing, return as-is
        }

        return $value;
    }

    /**
     * Return the (valid) location where to store some temporary data
     * Throws an exception if the directory specified in the 'data_path' configuration does not exist and cannot be created.
     *
     * @param string $sFileName
     *
     * @return string
     *
     * @throws Exception
     */
    public static function GetDataFilePath($sFileName)
    {
        $sPath = static::GetConfigurationValue('data_path', '%APPROOT%/data/');
        $sPath = str_replace('%APPROOT%', APPROOT, $sPath); // substitute the %APPROOT% placeholder with its actual value
        $sPath = rtrim($sPath, '/').'/'; // Make that the path ends with exactly one /
        if (!file_exists($sPath)) {
            if (!mkdir($sPath, 0700, true)) {
                throw new Exception("Failed to create data_path: '$sPath'. Either create the directory yourself or make sure that the script has enough rights to create it.");
            }
        }

        return $sPath.basename($sFileName);
    }

    /**
     * Helper to execute an HTTP POST request
     * Source: http://netevil.org/blog/2006/nov/http-post-from-php-without-curl
     *         originaly named after do_post_request
     * Does not require cUrl but requires openssl for performing https POSTs.
     *
     * @param string $sUrl              The URL to POST the data to
     * @param hash   $aData             The data to POST as an array('param_name' => value)
     * @param string $sOptionnalHeaders Additional HTTP headers as a string with newlines between headers
     * @param hash   $aResponseHeaders  An array to be filled with reponse headers: WARNING: the actual content of the array depends on the library used: cURL or fopen, test with both !! See: http://fr.php.net/manual/en/function.curl-getinfo.php
     * @param hash   $aCurlOptions      An (optional) array of options to pass to curl_init. The format is 'option_code' => 'value'. These values have precedence over the default ones
     *
     * @return string The result of the POST request
     *
     * @throws Exception
     */
    public static function DoPostRequest($sUrl, $aData, $sOptionnalHeaders = null, &$aResponseHeaders = null, $aCurlOptions = [])
    {
        // var_dump("Request Data" , $sUrl, $aData, $sOptionnalHeaders, $aResponseHeaders, $aCurlOptions );
        if (self::$oMockedDoPostRequestService) {
            return self::$oMockedDoPostRequestService->DoPostRequest($sUrl, $aData, $sOptionnalHeaders, $aResponseHeaders, $aCurlOptions);
        }

        // $sOptionnalHeaders is a string containing additional HTTP headers that you would like to send in your request.

        if (function_exists('curl_init')) {
            // If cURL is available, let's use it, since it provides a greater control over the various HTTP/SSL options
            // For instance fopen does not allow to work around the bug: http://stackoverflow.com/questions/18191672/php-curl-ssl-routinesssl23-get-server-helloreason1112
            // by setting the SSLVERSION to 3 as done below.
            $aHeaders = explode("\n", $sOptionnalHeaders);
            // N°3267 - Webservices: Fix optional headers not being taken into account
            //          See https://www.php.net/curl_setopt CURLOPT_HTTPHEADER
            $aHTTPHeaders = [];
            foreach ($aHeaders as $sHeaderString) {
                $aHTTPHeaders[] = trim($sHeaderString);
            }
            // Default options, can be overloaded/extended with the 4th parameter of this method, see above $aCurlOptions
            $aOptions = [
                CURLOPT_RETURNTRANSFER => true,     // return the content of the request
                CURLOPT_HEADER => false,    // don't return the headers in the output
                CURLOPT_FOLLOWLOCATION => true,     // follow redirects
                CURLOPT_ENCODING => '',       // handle all encodings
                CURLOPT_USERAGENT => 'spider', // who am i
                CURLOPT_AUTOREFERER => true,     // set referer on redirect
                CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
                CURLOPT_TIMEOUT => 120,      // timeout on response
                CURLOPT_MAXREDIRS => 10,       // stop after 10 redirects
                CURLOPT_SSL_VERIFYHOST => 0,     // Disabled SSL Cert checks
                CURLOPT_SSL_VERIFYPEER => 0,     // Disabled SSL Cert checks
                // SSLV3 (CURL_SSLVERSION_SSLv3 = 3) is now considered as obsolete/dangerous: http://disablessl3.com/#why
                // but it used to be a MUST to prevent a strange SSL error: http://stackoverflow.com/questions/18191672/php-curl-ssl-routinesssl23-get-server-helloreason1112
                // CURLOPT_SSLVERSION		=> 3,
                CURLOPT_POST => count($aData),
                CURLOPT_POSTFIELDS => http_build_query($aData),
                CURLOPT_HTTPHEADER => $aHTTPHeaders,
            ];

            $aAllOptions = $aCurlOptions + $aOptions;
            $ch = curl_init($sUrl);
            curl_setopt_array($ch, $aAllOptions);
            $response = curl_exec($ch);
            $iErr = curl_errno($ch);
            $sErrMsg = curl_error($ch);
            $aHeaders = curl_getinfo($ch);
            if (0 !== $iErr) {
                throw new IOException("Problem opening URL: $sUrl".PHP_EOL."    error msg: $sErrMsg".PHP_EOL."    curl_init error code: $iErr (cf https://www.php.net/manual/en/function.curl-errno.php)");
            }
            if (is_array($aResponseHeaders)) {
                $aHeaders = curl_getinfo($ch);
                foreach ($aHeaders as $sCode => $sValue) {
                    $sName = str_replace(' ', '-', ucwords(str_replace('_', ' ', $sCode))); // Transform "content_type" into "Content-Type"
                    $aResponseHeaders[$sName] = $sValue;
                }
            }
            curl_close($ch);
        } else {
            // cURL is not available let's try with streams and fopen...

            $sData = http_build_query($aData);
            $aParams = [
                'http' => [
                    'method' => 'POST',
                    'content' => $sData,
                    'header' => "Content-type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($sData)."\r\n",
                ],
            ];
            if (null !== $sOptionnalHeaders) {
                $aParams['http']['header'] .= $sOptionnalHeaders;
            }
            $ctx = stream_context_create($aParams);

            $fp = @fopen($sUrl, 'rb', false, $ctx);
            if (!$fp) {
                $error_arr = error_get_last();
                if (is_array($error_arr)) {
                    throw new IOException("Wrong URL: $sUrl, Error: ".json_encode($error_arr));
                } elseif (('https' == strtolower(substr($sUrl, 0, 5))) && !extension_loaded('openssl')) {
                    throw new IOException("Cannot connect to $sUrl: missing module 'openssl'");
                } else {
                    throw new IOException("Wrong URL: $sUrl");
                }
            }
            $response = @stream_get_contents($fp);
            if (false === $response) {
                throw new IOException("Problem reading data from $sUrl, $php_errormsg");
            }
            if (is_array($aResponseHeaders)) {
                $aMeta = stream_get_meta_data($fp);
                $aHeaders = $aMeta['wrapper_data'];
                foreach ($aHeaders as $sHeaderString) {
                    if (preg_match('/^([^:]+): (.+)$/', $sHeaderString, $aMatches)) {
                        $aResponseHeaders[$aMatches[1]] = trim($aMatches[2]);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Pretty print a JSON formatted string. Copied/pasted from http://stackoverflow.com/questions/6054033/pretty-printing-json-with-php.
     *
     * @deprecated 1.3.0 use `json_encode($value, JSON_PRETTY_PRINT);` instead (PHP 5.4.0 required)
     *
     * @param string $json A JSON formatted object definition
     *
     * @return string The nicely formatted JSON definition
     */
    public static function JSONPrettyPrint($json)
    {
        Utils::Log(LOG_NOTICE, 'Use of deprecated method '.__METHOD__);

        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = null;
        $json_length = strlen($json);

        for ($i = 0; $i < $json_length; ++$i) {
            $char = $json[$i];
            $new_line_level = null;
            $post = '';
            if (null !== $ends_line_level) {
                $new_line_level = $ends_line_level;
                $ends_line_level = null;
            }
            if ($in_escape) {
                $in_escape = false;
            } elseif ('"' === $char) {
                $in_quotes = !$in_quotes;
            } elseif (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = null;
                        $new_line_level = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
                        // no break
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = ' ';
                        break;

                    case ' ':
                    case "\t":
                    case "\n":
                    case "\r":
                        $char = '';
                        $ends_line_level = $new_line_level;
                        $new_line_level = null;
                        break;
                }
            } elseif ('\\' === $char) {
                $in_escape = true;
            }
            if (null !== $new_line_level) {
                $result .= "\n".str_repeat("\t", $new_line_level);
            }
            $result .= $char.$post;
        }

        return $result;
    }

    /**
     * Executes a command and returns an array with exit code, stdout and stderr content.
     *
     * @return false|string
     *
     * @throws Exception
     */
    public static function Exec($sCmd)
    {
        $iBeginTime = time();
        $sWorkDir = APPROOT;
        $aDescriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        Utils::Log(LOG_INFO, "Command: $sCmd. Workdir: $sWorkDir");
        $rProcess = proc_open($sCmd, $aDescriptorSpec, $aPipes, $sWorkDir, null);

        $sStdOut = stream_get_contents($aPipes[1]);
        fclose($aPipes[1]);

        $sStdErr = stream_get_contents($aPipes[2]);
        fclose($aPipes[2]);

        $iCode = proc_close($rProcess);

        $iElapsed = time() - $iBeginTime;
        if (0 === $iCode) {
            Utils::Log(LOG_INFO, "elapsed:{$iElapsed}s output: $sStdOut");

            return $sStdOut;
        } else {
            throw new Exception("Command failed : $sCmd \n\t\t=== with status:$iCode \n\t\t=== stderr:$sStdErr \n\t\t=== stdout: $sStdOut");
        }
    }

    /**
     * @since 1.3.0
     */
    public static function GetCurlOptions(int $iCurrentTimeOut = -1): array
    {
        $aRawCurlOptions = Utils::GetConfigurationValue('curl_options', [CURLOPT_SSLVERSION => CURL_SSLVERSION_SSLv3]);

        return self::ComputeCurlOptions($aRawCurlOptions, $iCurrentTimeOut);
    }

    /**
     * @since 1.3.0
     */
    public static function ComputeCurlOptions(array $aRawCurlOptions, int $iCurrentTimeOut): array
    {
        $aCurlOptions = [];
        foreach ($aRawCurlOptions as $key => $value) {
            // Convert strings like 'CURLOPT_SSLVERSION' to the value of the corresponding define i.e CURLOPT_SSLVERSION = 32 !
            $iKey = (!is_numeric($key)) ? constant((string) $key) : (int) $key;
            $aCurlOptions[$iKey] = (!is_numeric($value) && defined($value)) ? constant($value) : $value;
        }

        if (-1 !== $iCurrentTimeOut) {
            $aCurlOptions[CURLOPT_CONNECTTIMEOUT] = $iCurrentTimeOut;
            $aCurlOptions[CURLOPT_TIMEOUT] = $iCurrentTimeOut;
        }

        return $aCurlOptions;
    }

    /**
     * Check if the given module is installed in iTop.
     * Mind that this assumes the `ModuleInstallation` class is ordered by descending installation date.
     *
     * @param string $sModuleId Name of the module to be found, optionally included version (e.g. "some-module" or "some-module/1.2.3")
     * @param bool   $bRequired Whether to throw exceptions when module not found
     *
     * @return bool True when the given module is installed, false otherwise
     *
     * @throws Exception When the module is required but could not be found
     */
    public static function CheckModuleInstallation(string $sModuleId, bool $bRequired = false, ?RestClient $oClient = null): bool
    {
        if (!isset($oClient)) {
            $oClient = new RestClient();
        }

        if (preg_match('/^([^\/]+)(?:\/([<>]?=?)(.+))?$/', $sModuleId, $aModuleMatches)) {
            $sName = $aModuleMatches[1];
            $sOperator = $aModuleMatches[2] ?? null ?: '>=';
            $sExpectedVersion = $aModuleMatches[3] ?? null;
        }

        try {
            if (!isset(static::$sLastInstallDate)) {
                $aDatamodelResults = $oClient->Get('ModuleInstallation', ['name' => 'datamodel'], 'installed', 1);
                if (0 != $aDatamodelResults['code'] || empty($aDatamodelResults['objects'])) {
                    throw new Exception($aDatamodelResults['message'], $aDatamodelResults['code']);
                }
                $aDatamodel = current($aDatamodelResults['objects']);
                static::$sLastInstallDate = $aDatamodel['fields']['installed'];
            }

            $aResults = $oClient->Get('ModuleInstallation', ['name' => $sName, 'installed' => static::$sLastInstallDate], 'name,version', 1);
            if (0 != $aResults['code'] || empty($aResults['objects'])) {
                throw new Exception($aResults['message'], $aResults['code']);
            }
            $aObject = current($aResults['objects']);
            $sCurrentVersion = $aObject['fields']['version'];

            if (isset($sExpectedVersion) && !version_compare($sCurrentVersion, $sExpectedVersion, $sOperator)) {
                throw new Exception(sprintf('Version mismatch (%s %s %s)', $sCurrentVersion, $sOperator, $sExpectedVersion));
            }

            Utils::Log(LOG_DEBUG, sprintf('iTop module %s version %s is installed.', $aObject['fields']['name'], $sCurrentVersion));
        } catch (Exception $e) {
            $sMessage = sprintf('%s iTop module %s is considered as not installed due to: %s', $bRequired ? 'Required' : 'Optional', $sName, $e->getMessage());
            if ($bRequired) {
                throw new Exception($sMessage, 0, $e);
            } else {
                Utils::Log(LOG_INFO, $sMessage);

                return false;
            }
        }

        return true;
    }
}

class UtilsLogger
{
    /**
     * UtilsLogger constructor.
     */
    public function __construct()
    {
    }

    public function Log($iPriority, $sMessage)
    {
    }
}
