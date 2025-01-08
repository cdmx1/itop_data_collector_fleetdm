<?php

/**
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
abstract class AbstractFleetDMSoftwareCollector extends AbstractFleetDMCollector
{
    protected function GetSQLQueryName()
    {
        $sSQLQueryName = '_query';
        if ('yes' == Utils::GetConfigurationValue('use_software_categories', 'no')) {
            if ('yes' == Utils::GetConfigurationValue('use_asset_categories', 'no')) {
                $sSQLQueryName = '_with_2categories'.$sSQLQueryName;
            } else {
                $sSQLQueryName = '_with_categories'.$sSQLQueryName;
            }
        }

        return $sSQLQueryName;
    }

    abstract protected function GetTargetClass();

    protected function AddOtherParams(&$sQuery)
    {
        $aListAssetCategories = [];

        if ('yes' == Utils::GetConfigurationValue('use_asset_categories', 'no')) {
            // Get list of asset category
            $aListSynchronisedClasses = [];
            if ('yes' == Utils::GetConfigurationValue('PCCollection', 'no')) {
                $aListSynchronisedClasses[] = 'PC';
            }
            if ('yes' == Utils::GetConfigurationValue('ServerCollection', 'no')) {
                $aListSynchronisedClasses[] = 'Server';
            }
            if ('yes' == Utils::GetConfigurationValue('VMCollection', 'no')) {
                $aListSynchronisedClasses[] = 'VirtualMachine';
            }
            if ('yes' == Utils::GetConfigurationValue('MobilePhoneCollection', 'no')) {
                $aListSynchronisedClasses[] = 'MobilePhone';
            }

            $sQueryITopGetAssetCategory = Utils::GetConfigurationValue('FleetDMSoftwareCollector_getListAssetCategoryFromItop', '');
            $sQueryITopGetAssetCategory = str_replace('#ERROR_UNDEFINED_PLACEHOLDER_targetlist#', implode("','", $aListSynchronisedClasses), $sQueryITopGetAssetCategory);

            $oRestClientAssetCategory = new RestClient();
            $aResultAssetCategory = $oRestClientAssetCategory->Get('FleetDMAssetCategory', $sQueryITopGetAssetCategory, 'name');
            if (is_null($aResultAssetCategory['objects'])) {
                Utils::Log(LOG_NOTICE, 'No Asset category found in iTop with query: '.$sQueryITopGetAssetCategory);

                return;
            }
            foreach ($aResultAssetCategory['objects'] as $idx => $aAttDef) {
                $aListAssetCategories[$aAttDef['fields']['name']] = $aAttDef['fields']['name'];
            }
        }

        // Get list of software category depending class synchronized
        $sSQLQueryName = '_getListFromItop';
        $sClass = 'Software';

        if ('yes' == Utils::GetConfigurationValue('use_software_categories', 'no')) {
            $sSQLQueryName = '_with_categories'.$sSQLQueryName;
            $sClass = 'FleetDMSoftwareCategory';
        }
        Utils::Log(LOG_NOTICE, get_class($this).$sSQLQueryName);
        $sQueryITop = Utils::GetConfigurationValue(get_class($this).$sSQLQueryName, '');
        if ('' == $sQueryITop) {
            // Try all lowercase
            $sQueryITop = Utils::GetConfigurationValue(strtolower(get_class($this)).$sSQLQueryName, '');
        }
        $oRestClient = new RestClient();
        $aResult = $oRestClient->Get($sClass, $sQueryITop, 'name, type');
        if (is_null($aResult['objects'])) {
            Utils::Log(LOG_NOTICE, "No $sClass found in iTop with query: ".$sQueryITop);

            return;
        }

        $aListSoftware = [];
        foreach ($aResult['objects'] as $idx => $aAttDef) {
            $sType = $aAttDef['fields']['type'];
            if (is_null($sType)) {
                $sType = 'OtherSoftware';
            }
            $aListSoftware[$sType][] = $aAttDef['fields']['name'];
        }

        $sInitialQuery = $sQuery;
        $bIsFirst = true;
        foreach ($aListSoftware as $sType => $aName) {
            $sQueryByType = str_replace('#ERROR_UNDEFINED_PLACEHOLDER_categorielist#', implode("','", $aListAssetCategories), $sInitialQuery);
            $sQueryByType = str_replace('#ERROR_UNDEFINED_PLACEHOLDER_softwarelist#', implode("','", $aName), $sQueryByType);
            $sQueryByType = str_replace('#ERROR_UNDEFINED_PLACEHOLDER_type_id#', $sType, $sQueryByType);
            if ($bIsFirst) {
                $sQuery = $sQueryByType;
                $bIsFirst = false;
            } else {
                $sQuery = $sQuery.' UNION '.$sQueryByType;
            }
        }
        Utils::Log(LOG_DEBUG, $sQuery);
    }

    public function CheckToLaunch(array $aOrchestratedCollectors): bool
    {
        if ('yes' == Utils::GetConfigurationValue('SoftwareCollection', 'no')) {
            return true;
        }

        return false;
    }
}
