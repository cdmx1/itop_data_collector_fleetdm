<?php

/**
 * @copyright   Copyright (C) 2010-2023 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
class FleetDMCollectionPlan extends CollectionPlan
{
    private $bTeemIpIsInstalled;
    private $bTeemIpZoneMgmtIsInstalled;
    private $bIpDiscoveryIsInstalled;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Initialize collection plan.
     *
     * @throws IOException
     */
    public function Init(): void
    {
        parent::Init();

        // Detects if TeemIp is installed or not
        Utils::Log(LOG_DEBUG, 'Detecting if TeemIp is installed on remote iTop server');
        $this->bTeemIpIsInstalled = true;
        $oRestClient = new RestClient();
        try {
            $aResult = $oRestClient->Get('IPAddress', 'SELECT IPAddress WHERE id = 0');
            if (0 == $aResult['code']) {
                $sMessage = 'TeemIp is installed on remote iTop server';
            } else {
                $sMessage = 'TeemIp is NOT installed on remote iTop server';
                $this->bTeemIpIsInstalled = false;
            }
        } catch (Exception $e) {
            $this->bTeemIpIsInstalled = false;
            $sMessage = 'TeemIp is considered as NOT installed due : '.$e->getMessage();
            if (is_a($e, 'IOException')) {
                Utils::Log(LOG_ERR, $sMessage);
                throw $e;
            }
        }

        Utils::Log(LOG_INFO, $sMessage);

        $this->bIpDiscoveryIsInstalled = false;
        $this->bTeemIpZoneMgmtIsInstalled = false;
        if ($this->bTeemIpIsInstalled) {
            // Detects if IP Discovery extension is installed or not
            Utils::Log(LOG_DEBUG, 'Detecting if IP Discovery extension is installed on remote iTop server');
            $oRestClient = new RestClient();
            try {
                $aResult = $oRestClient->Get('IPDiscovery', 'SELECT IPDiscovery WHERE id = 0');
                if (0 == $aResult['code']) {
                    $sMessage = 'IP Discovery extension is installed on remote iTop server';
                    $this->bIpDiscoveryIsInstalled = true;
                } else {
                    $sMessage = 'IP Discovery extension is NOT installed on remote iTop server';
                }
            } catch (Exception $e) {
                $sMessage = 'IP TDiscovery extension is NOT installed on remote iTop server';
            }
            Utils::Log(LOG_INFO, $sMessage);

            // Detects if Zone Management extension is installed or not
            Utils::Log(LOG_DEBUG, 'Detecting if TeemIp Zone Management extension is installed on remote iTop server');
            $oRestClient = new RestClient();
            try {
                $aResult = $oRestClient->Get('Zone', 'SELECT Zone WHERE id = 0');
                if (0 == $aResult['code']) {
                    $sMessage = 'TeemIp Zone Management is installed on remote iTop serve';
                    $this->bTeemIpZoneMgmtIsInstalled = true;
                } else {
                    $sMessage = 'TeemIp Zone Management is NOT installed on remote iTop server';
                }
            } catch (Exception $e) {
                $sMessage = 'TeemIp Zone Management is NOT installed on remote iTop server';
            }
            Utils::Log(LOG_INFO, $sMessage);
        }
    }

    public function AddCollectorsToOrchestrator(): bool
    {
        Utils::Log(LOG_INFO, '---------- Fleet DM Collectors to launched ----------');

        return parent::AddCollectorsToOrchestrator();
    }

    public function IsTeemIpInstalled()
    {
        return $this->bTeemIpIsInstalled;
    }

    public function IsTeemIpZoneMgmtInstalled()
    {
        return $this->bTeemIpZoneMgmtIsInstalled;
    }

    public function IsIpDiscoveryInstalled()
    {
        return $this->bIpDiscoveryIsInstalled;
    }
}
