<?php

/**
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
class FleetDMPCModelCollector extends AbstractFleetDMAssetCollector
{
    protected function GetTargetClass()
    {
        return 'Model';
    }

    public function CheckToLaunch(array $aOrchestratedCollectors): bool
    {
        if ('yes' == Utils::GetConfigurationValue('PCCollection', 'no')) {
            return true;
        }

        return false;
    }
}
