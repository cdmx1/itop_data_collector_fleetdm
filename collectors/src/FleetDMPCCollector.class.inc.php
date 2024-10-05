<?php
/**
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
class FleetDMPCCollector extends AbstractFleetDMAssetCollector
{

    protected $oOSVersionLookup;
    protected $oOSLicenceLookup;
    protected $oModelLookup;

    public function AttributeIsOptional($sAttCode)
    {
        return parent::AttributeIsOptional($sAttCode);
    }

    protected function GetTargetClass()
    {
        return 'PC';
    }
	public function CheckToLaunch(array $aOrchestratedCollectors): bool
    {
        if (Utils::GetConfigurationValue('PCCollection', 'no') == 'yes') {
            return true;
        }
        return false;
    }
}
