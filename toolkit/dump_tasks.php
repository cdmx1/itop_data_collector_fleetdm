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

/*
 * Command line utility to generate the JSON representation of the SynchroDataSource
 * already configured in iTop
 *
 * Usage: php dump_task.php [--task_name="name of the task"]
 */
define('APPROOT', dirname(dirname(__FILE__)).'/');

require_once APPROOT.'core/parameters.class.inc.php';
require_once APPROOT.'core/ioexception.class.inc.php';
require_once APPROOT.'core/utils.class.inc.php';
require_once APPROOT.'core/restclient.class.inc.php';

$sTaskName = Utils::ReadParameter('task_name', '*');
Utils::InitConsoleLogLevel();

if ('*' == $sTaskName) {
    // Usage
    echo "Command line utility to generate the JSON representation of an iTop SynchroDataSource.\n\n";
    echo 'Usage: php '.basename($argv[0])." --task_name=\"selected task name\"\n\n";

    // List all tasks defined in iTop
    $oRestClient = new RestClient();
    $aResult = $oRestClient->Get('SynchroDataSource', 'SELECT SynchroDataSource');
    $sITopUrl = Utils::GetConfigurationValue('itop_url', '');
    if (0 != $aResult['code']) {
        echo "[{$aResult['code']}] {$aResult['message']}\n";
        exit -1;
    }

    echo "iTop: \033[34m{$sITopUrl}\033[0m\n";
    if (empty($aResult['objects'])) {
        echo "   no SynchroDataSource defined\n";
    } else {
        switch (count($aResult['objects'])) {
            case 1:
                echo "   1 SynchroDataSource defined\n";
                break;

            default:
                echo '   '.count($aResult['objects'])." SynchroDataSource objects defined\n";
                break;
        }

        echo "+-----+--------------------------------------------------------------------------------------+\n";
        echo "|  Id |             Name                                                                     |\n";
        echo "+-----+--------------------------------------------------------------------------------------+\n";
        foreach ($aResult['objects'] as $aValues) {
            $aCurrentTaskDefinition = $aValues['fields'];
            echo sprintf("| %3d | %-84.84s |\n",
                $aValues['key'],
                $aCurrentTaskDefinition['name'],
            );
        }
        echo "+-----+--------------------------------------------------------------------------------------+\n";
    }
    $sMaxVersion = $oRestClient->GetNewestKnownVersion();
    echo "iTop REST/API version: $sMaxVersion\n";
} else {
    // Generate the pretty-printed JSON representation of the specified task
    $oRestClient = new RestClient();
    $aResult = $oRestClient->Get('SynchroDataSource', ['name' => $sTaskName], '*');
    if (0 != $aResult['code']) {
        echo "Sorry, an error occurred while retrieving the information from iTop: {$aResult['message']} ({$aResult['code']})\n";
    } else {
        if (is_array($aResult['objects']) && (count($aResult['objects']) > 0)) {
            foreach ($aResult['objects'] as $sKey => $aValues) {
                if (!array_key_exists('key', $aValues)) {
                    // Emulate the behavior for older versions of the API
                    if (preg_match('/::([0-9]+)$/', $sKey, $aMatches)) {
                        $iKey = (int) $aMatches[1];
                    }
                } else {
                    $iKey = (int) $aValues['key'];
                }
                $aCurrentTaskDefinition = $aValues['fields'];
                RestClient::GetFullSynchroDataSource($aCurrentTaskDefinition, $iKey);

                // Replace some literals by their usual placeholders
                $aCurrentTaskDefinition['user_id'] = '$synchro_user$';
                $aCurrentTaskDefinition['notify_contact_id'] = '$contact_to_notify$';

                echo json_encode($aCurrentTaskDefinition, JSON_PRETTY_PRINT);
            }
        } else {
            echo "Sorry, no SynchroDataSource named '$sTaskName' found in iTop.\n";
        }
    }
}
