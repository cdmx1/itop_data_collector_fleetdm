<?php

// Copyright (C) 2022 Combodo SARL
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

/**
 * Base class for all collection plans.
 */
abstract class CollectionPlan
{
    // Instance of the collection plan
    protected static $oCollectionPlan;

    public function __construct()
    {
        self::$oCollectionPlan = $this;
    }

    /**
     * Initialize collection plan.
     *
     * @throws IOException
     */
    public function Init(): void
    {
        Utils::Log(LOG_INFO, '---------- Build collection plan ----------');
    }

    /**
     * @return static
     */
    public static function GetPlan()
    {
        return self::$oCollectionPlan;
    }

    /**
     * Provide the launch sequence as defined in the configuration files.
     *
     * @throws Exception
     */
    public function GetSortedLaunchSequence(): array
    {
        Utils::Log(LOG_INFO, 'Fetching collectors_launch_sequence...');
        $aCollectorsLaunchSequence = utils::GetConfigurationValue('collectors_launch_sequence', []);

        Utils::Log(LOG_INFO, 'Fetching extensions_collectors_launch_sequence...');
        $aExtensionsCollectorsLaunchSequence = utils::GetConfigurationValue('extensions_collectors_launch_sequence', []);

        // Debug log the sequences retrieved
        Utils::Log(LOG_DEBUG, 'Initial collectors_launch_sequence: '.print_r($aCollectorsLaunchSequence, true));
        Utils::Log(LOG_DEBUG, 'Initial extensions_collectors_launch_sequence: '.print_r($aExtensionsCollectorsLaunchSequence, true));

        // Merge sequences
        $aCollectorsLaunchSequence = array_merge($aCollectorsLaunchSequence, $aExtensionsCollectorsLaunchSequence);
        if (!empty($aCollectorsLaunchSequence)) {
            // Initialize rank and sorted array
            $aSortedCollectorsLaunchSequence = [];
            $aRank = []; // Add rank initialization

            foreach ($aCollectorsLaunchSequence as $aCollector) {
                // Check if rank exists and log collector details
                if (array_key_exists('rank', $aCollector)) {
                    $aRank[] = $aCollector['rank'];
                    $aSortedCollectorsLaunchSequence[] = $aCollector;
                } else {
                    Utils::Log(LOG_INFO, '> Rank is missing from the launch_sequence of '.$aCollector['name'].'. It will not be launched.');
                }
            }

            // Sort by rank
            Utils::Log(LOG_INFO, 'Sorting collectors by rank...');
            array_multisort($aRank, SORT_ASC, $aSortedCollectorsLaunchSequence);

            // Log the final sorted sequence
            Utils::Log(LOG_DEBUG, 'Sorted collectors_launch_sequence: '.print_r($aSortedCollectorsLaunchSequence, true));

            return $aSortedCollectorsLaunchSequence;
        }

        // Log in case the sequence was empty
        Utils::Log(LOG_INFO, 'No collectors to launch.');

        return $aCollectorsLaunchSequence;
    }

    /**
     * Look for the collector definition file in the different possible collector directories.
     */
    public function GetCollectorDefinitionFile($sCollector): bool
    {
        if (file_exists(APPROOT.'collectors/extensions/src/'.$sCollector.'.class.inc.php')) {
            require_once APPROOT.'collectors/extensions/src/'.$sCollector.'.class.inc.php';
        } elseif (file_exists(APPROOT.'collectors/src/'.$sCollector.'.class.inc.php')) {
            require_once APPROOT.'collectors/src/'.$sCollector.'.class.inc.php';
        } elseif (file_exists(APPROOT.'collectors/'.$sCollector.'.class.inc.php')) {
            require_once APPROOT.'collectors/'.$sCollector.'.class.inc.php';
        } else {
            return false;
        }

        return true;
    }

    /**
     *  Add the collectors to be launched to the orchestrator.
     *
     * @throws Exception
     */
    public function AddCollectorsToOrchestrator(): bool
    {
        // Read and order launch sequence
        $aCollectorsLaunchSequence = $this->GetSortedLaunchSequence();
        if (empty($aCollectorsLaunchSequence)) {
            Utils::Log(LOG_INFO, '---------- No Launch sequence has been found, no collector has been orchestrated ----------');

            return false;
        }

        $iIndex = 1;
        $aOrchestratedCollectors = [];
        foreach ($aCollectorsLaunchSequence as $iKey => $aCollector) {
            $sCollectorName = $aCollector['name'];

            // Skip disabled collectors
            if (!array_key_exists('enable', $aCollector) || ('yes' != $aCollector['enable'])) {
                Utils::Log(LOG_INFO, '> '.$sCollectorName.' is disabled and will not be launched.');
                continue;
            }

            // Read collector php definition file
            if (!$this->GetCollectorDefinitionFile($sCollectorName)) {
                Utils::Log(LOG_INFO, '> No file definition file has been found for '.$sCollectorName.' It will not be launched.');
                continue;
            }

            /** @var Collector $oCollector */
            // Instantiate collector
            $oCollector = new $sCollectorName();
            $oCollector->Init();
            if ($oCollector->CheckToLaunch($aOrchestratedCollectors)) {
                Utils::Log(LOG_INFO, $sCollectorName.' will be launched !');
                Orchestrator::AddCollector($iIndex++, $sCollectorName);
                $aOrchestratedCollectors[$sCollectorName] = true;
            } else {
                $aOrchestratedCollectors[$sCollectorName] = false;
            }
            unset($oCollector);
        }
        Utils::Log(LOG_INFO, '---------- Collectors have been orchestrated ----------');

        return true;
    }
}
