<?php

function BatimentBuildingPage(&$CurrentPlanet, $CurrentUser)
{
    global $_EnginePath, $_Vars_ResProduction, $_Lang, $_Vars_GameElements, $_Vars_ElementCategories,
           $_SkinPath, $_GameConfig, $_GET, $_Vars_PremiumBuildingPrices, $_Vars_MaxElementLevel, $_Vars_PremiumBuildings;

    $BuildingPage = '';

    include($_EnginePath.'includes/functions/GetElementTechReq.php');
    include($_EnginePath.'includes/functions/GetElementPrice.php');
    include($_EnginePath.'includes/functions/GetRestPrice.php');

    CheckPlanetUsedFields ($CurrentPlanet);

    $Now = time();
    $SubTemplate = gettemplate('buildings_builds_row');

    PlanetResourceUpdate($CurrentUser, $CurrentPlanet, $Now);

    // Handle Commands
    if(!isOnVacation($CurrentUser))
    {
        if(isset($_GET['cmd']))
        {
            $bDoItNow = false;
            $TheCommand = $_GET['cmd'];
            if(!empty($_GET['building']))
            {
                $Element = round(trim($_GET['building']));
            }
            if(!empty($_GET['listid']))
            {
                $ListID = round(trim($_GET['listid']));
            }

            if(isset($Element))
            {
                if(in_array($Element, $_Vars_ElementCategories['buildOn'][$CurrentPlanet['planet_type']]))
                {
                    $bDoItNow = true;
                }
            }
            else if(isset($ListID))
            {
                $bDoItNow = true;
            }
            if($bDoItNow == true)
            {
                switch($TheCommand)
                {
                    case 'cancel':
                        // Cancel Current Building
                        include($_EnginePath.'includes/functions/CancelBuildingFromQueue.php');
                        CancelBuildingFromQueue($CurrentPlanet, $CurrentUser);
                        $CommandDone = true;
                        break;
                    case 'remove':
                        // Remove planned Building from Queue
                        include($_EnginePath.'includes/functions/RemoveBuildingFromQueue.php');
                        RemoveBuildingFromQueue($CurrentPlanet, $CurrentUser, $ListID);
                        $CommandDone = true;
                        break;
                    case 'insert':
                        // Insert into Queue (to Build)
                        include($_EnginePath.'includes/functions/AddBuildingToQueue.php');
                        AddBuildingToQueue($CurrentPlanet, $CurrentUser, $Element, true);
                        $CommandDone = true;
                        break;
                    case 'destroy':
                        // Insert into Queue (to Destroy)
                        include($_EnginePath.'includes/functions/AddBuildingToQueue.php');
                        AddBuildingToQueue($CurrentPlanet, $CurrentUser, $Element, false);
                        $CommandDone = true;
                        break;
                    default:
                        break;
                }
            }

            if($CommandDone === true)
            {
                if(HandlePlanetQueue_StructuresSetNext($CurrentPlanet, $CurrentUser, $Now, true) === false)
                {
                    include($_EnginePath.'includes/functions/BuildingSavePlanetRecord.php');
                    BuildingSavePlanetRecord($CurrentPlanet);
                }
            }
        }
    }

    include($_EnginePath.'includes/functions/ShowBuildingQueue.php');
    $Queue = ShowBuildingQueue($CurrentPlanet, $CurrentUser);

    if($Queue['lenght'] < ((isPro($CurrentUser)) ? MAX_BUILDING_QUEUE_SIZE_PRO : MAX_BUILDING_QUEUE_SIZE ))
    {
        $CanBuildElement = true;
    }
    else
    {
        $CanBuildElement = false;
    }

    if($CurrentUser['engineer_time'] > $Now)
    {
        $EnergyMulti = 1.10;
    }
    else
    {
        $EnergyMulti = 1;
    }

    if(!empty($CurrentPlanet['buildQueue']))
    {
        $LockResources = array
        (
            'metal' => 0,
            'crystal' => 0,
            'deuterium' => 0
        );

        $CurrentQueue = explode(';', $CurrentPlanet['buildQueue']);
        foreach($CurrentQueue as $QueueIndex => $ThisBuilding)
        {
            $ThisBuilding = explode(',', $ThisBuilding);
            $ElementID = $ThisBuilding[0]; //ElementID
            $BuildMode = $ThisBuilding[4]; //BuildMode

            if($QueueIndex > 0)
            {
                if($BuildMode == 'destroy')
                {
                    $ForDestroy = true;
                }
                else
                {
                    $ForDestroy = false;
                }
                $GetResourcesToLock = GetBuildingPrice($CurrentUser, $CurrentPlanet, $ElementID, true, $ForDestroy);
                $LockResources['metal'] += $GetResourcesToLock['metal'];
                $LockResources['crystal'] += $GetResourcesToLock['crystal'];
                $LockResources['deuterium'] += $GetResourcesToLock['deuterium'];
            }

            if(!isset($LevelModifiers[$ElementID]))
            {
                $LevelModifiers[$ElementID] = 0;
            }
            if($BuildMode == 'destroy')
            {
                $LevelModifiers[$ElementID] += 1;
                $CurrentPlanet[$_Vars_GameElements[$ElementID]] -= 1;
            }
            else
            {
                $LevelModifiers[$ElementID] -= 1;
                $CurrentPlanet[$_Vars_GameElements[$ElementID]] += 1;
            }
        }
    }

    foreach($_Vars_ElementCategories['build'] as $Element)
    {
        if(in_array($Element, $_Vars_ElementCategories['buildOn'][$CurrentPlanet['planet_type']]))
        {
            $ElementName = $_Lang['tech'][$Element];
            $CurrentMaxFields = CalculateMaxPlanetFields($CurrentPlanet);
            if($CurrentPlanet['field_current'] < ($CurrentMaxFields - $Queue['lenght']))
            {
                $RoomIsOk = true;
            }
            else
            {
                $RoomIsOk = false;
            }

            $parse = array();
            $parse['skinpath'] = $_SkinPath;
            $parse['i'] = $Element;
            $BuildingLevel = $CurrentPlanet[$_Vars_GameElements[$Element]];
            if(isset($LevelModifiers[$Element]))
            {
                $PlanetLevel = $BuildingLevel + $LevelModifiers[$Element];
            }
            else
            {
                $PlanetLevel = $BuildingLevel;
            }
            $parse['nivel'] = ($BuildingLevel == 0) ? '' : " ({$_Lang['level']} {$PlanetLevel})";

            if(in_array($Element, array(1, 2, 3, 4, 12)))
            {
                // Show energy on BuildingPage
                $Prod[4] = null;
                $Prod[3] = null;
                $ActualNeedDeut = null;
                $BuildLevelFactor = 10;
                $BuildTemp = $CurrentPlanet['temp_max'];
                $CurrentBuildtLvl = $BuildingLevel;
                $BuildLevel = ($CurrentBuildtLvl > 0) ? $CurrentBuildtLvl : 0;

                // --- Calculate ThisLevel Income
                if($Element == 12)
                {
                    $Prod[3] = (floor(eval($_Vars_ResProduction[$Element]['formule']['deuterium']) * $_GameConfig['resource_multiplier']));
                    $ActualNeedDeut = $Prod[3];
                }
                if($Element == 4 OR $Element == 12)
                {
                    // If it's Power Station
                    $Prod[4] = (floor(eval($_Vars_ResProduction[$Element]['formule']['energy']) * $EnergyMulti));
                }
                else
                {
                    // If it's Mine
                    $Prod[4] = (floor(eval($_Vars_ResProduction[$Element]['formule']['energy'])));
                }
                $ActualNeed = $Prod[4];

                // --- Calculate NextLevel Income
                $BuildLevel += 1;
                if($Element == 12)
                {
                    $Prod[3] = (floor(eval($_Vars_ResProduction[$Element]['formule']['deuterium']) * $_GameConfig['resource_multiplier']));
                }
                if($Element == 4 OR $Element == 12)
                {
                    // If it's Power Station
                    $Prod[4] = (floor(eval($_Vars_ResProduction[$Element]['formule']['energy']) * $EnergyMulti));
                }
                else
                {
                    // If it's Mine
                    $Prod[4] = (floor(eval($_Vars_ResProduction[$Element]['formule']['energy'])));
                }

                $EnergyNeed = prettyColorNumber(floor($Prod[4] - $ActualNeed));

                if($Element >= 1 AND $Element <= 3)
                {
                    $parse['build_need_diff'] = "(<span class=\"red\">{$_Lang['Energy']}: {$EnergyNeed}</span>)";
                }
                else if($Element == 4 OR $Element == 12)
                {
                    $DeuteriumNeeded = prettyColorNumber(floor($Prod[3] - $ActualNeedDeut));
                    if($Element != 12)
                    {
                        $parse['build_need_diff'] = "(<span class=\"lime\">{$_Lang['Energy']}: +{$EnergyNeed}</span>)";
                    }
                    else
                    {
                        $parse['build_need_diff'] = "(<span class=\"lime\">{$_Lang['Energy']}: +{$EnergyNeed}</span> | <span class=\"red\">{$_Lang['Deuterium']}: {$DeuteriumNeeded}</span>)";
                    }
                }
                $BuildLevel = 0;
            }

            $parse['n'] = $ElementName;
            $parse['descriptions'] = $_Lang['res']['descriptions'][$Element];
            $parse['click'] = '';
            $NextBuildLevel = $CurrentPlanet[$_Vars_GameElements[$Element]] + 1;
            $skip = false;

            if(IsTechnologieAccessible($CurrentUser, $CurrentPlanet, $Element))
            {
                if(!empty($LockResources))
                {
                    foreach($LockResources as $Key => $Value)
                    {
                        $CurrentPlanet[$Key] -= $Value;
                    }
                }
                $HaveRessources = IsElementBuyable($CurrentUser, $CurrentPlanet, $Element, true, false);
                $ElementBuildTime = GetBuildingTime($CurrentUser, $CurrentPlanet, $Element);
                $parse['time'] = ShowBuildTime($ElementBuildTime);
                $parse['price'] = GetElementPrice($CurrentUser, $CurrentPlanet, $Element);
                $parse['rest_price'] = GetRestPrice($CurrentUser, $CurrentPlanet, $Element);
                if(!empty($LockResources))
                {
                    foreach($LockResources as $Key => $Value)
                    {
                        $CurrentPlanet[$Key] += $Value;
                    }
                }

                if(isset($LevelModifiers[$Element]) && $LevelModifiers[$Element] != 0)
                {
                    $parse['AddLevelPrice'] = "<b>[{$_Lang['level']}: {$NextBuildLevel}]</b><br/>";
                }

                if($Element == 31)
                {
                    // Block Lab Upgrade is Research running (and Config dont allow that)
                    if($CurrentUser['techQueue_Planet'] > 0 AND $CurrentUser['techQueue_EndTime'] > 0 AND $_GameConfig['BuildLabWhileRun'] != 1)
                    {
                        $parse['click'] = "<span class=red>{$_Lang['in_working']}</span>";
                    }
                }
                if(!empty($_Vars_MaxElementLevel[$Element]))
                {
                    if($NextBuildLevel > $_Vars_MaxElementLevel[$Element])
                    {
                        $parse['click'] = "<span class=red>{$_Lang['onlyOneLevel']}</span>";
                        $skip = true;
                    }
                }

                if(isset($_Vars_PremiumBuildings[$Element]) && $_Vars_PremiumBuildings[$Element] == 1)
                {
                    $parse['rest_price'] = "<br/><font color=\"#7f7f7f\">{$_Lang['Rest_ress']}: {$_Lang['DarkEnergy']}";
                    $parse['price'] = "{$_Lang['Requires']}: {$_Lang['DarkEnergy']} <span class=\"noresources\">";
                    if($CurrentUser['darkEnergy'] < $_Vars_PremiumBuildingPrices[$Element])
                    {
                        if($skip == false)
                        {
                            $parse['click'] = "<span class=\"red\">{$_Lang['BuildFirstLevel']}</span>";
                        }
                        $parse['price'] .= " <b class=\"red\"> ".prettyNumber($_Vars_PremiumBuildingPrices[$Element])."</b></span> ";
                        $parse['rest_price'] .= "<b style=\"color: rgb(127, 95, 96);\"> ".prettyNumber($CurrentUser['darkEnergy'] - $_Vars_PremiumBuildingPrices[$Element])."</b>";
                    }
                    else
                    {
                        $parse['price'] .= " <b class=\"lime\"> ".prettyNumber($_Vars_PremiumBuildingPrices[$Element])."</b></span> ";
                        $parse['rest_price'] .= "<b style=\"color: rgb(95, 127, 108);\"> ".prettyNumber($CurrentUser['darkEnergy'] - $_Vars_PremiumBuildingPrices[$Element])."</b>";
                    }
                    $parse['rest_price'] .= '</font>';
                }

                if(isOnVacation($CurrentUser))
                {
                    $parse['click'] = "<span class=\"red\">{$_Lang['ListBox_Disallow_VacationMode']}</span>";
                }

                if($parse['click'] != '')
                {
                    // Don't do anything here
                }
                else if($RoomIsOk AND $CanBuildElement)
                {
                    if($Queue['lenght'] == 0)
                    {
                        if($NextBuildLevel == 1)
                        {
                            if($HaveRessources == true)
                            {
                                $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"lime\">{$_Lang['BuildFirstLevel']}</a>";
                            }
                            else
                            {
                                $parse['click'] = "<span class=\"red\">{$_Lang['BuildFirstLevel']}</span>";
                            }
                        }
                        else
                        {
                            if($HaveRessources == true)
                            {
                                $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"lime\">{$_Lang['BuildNextLevel']} {$NextBuildLevel}</a>";
                            }
                            else
                            {
                                $parse['click'] = "<span class=\"red\">{$_Lang['BuildNextLevel']} {$NextBuildLevel}</span>";
                            }
                        }
                    }
                    else
                    {
                        if($HaveRessources == true)
                        {
                            $ThisColor = 'lime';
                        }
                        else
                        {
                            $ThisColor = 'orange';
                        }

                        $parse['click'] = "<a href=\"?cmd=insert&building={$Element}\" class=\"{$ThisColor}\">{$_Lang['InBuildQueue']}<br/>({$_Lang['level']} {$NextBuildLevel})</a>";
                    }
                }
                else if($RoomIsOk AND !$CanBuildElement)
                {
                    $parse['click'] = "<span class=\"red\">{$_Lang['QueueIsFull']}</span>";
                }
                else
                {
                    if($CurrentPlanet['planet_type'] == 3)
                    {
                        $parse['click'] = "<span class=\"red\">{$_Lang['NoMoreSpace_Moon']}</span>";
                    }
                    else
                    {
                        $parse['click'] = "<span class=\"red\">{$_Lang['NoMoreSpace']}</span>";
                    }
                }
            }
            else
            {
                if($CurrentUser['settings_ExpandedBuildView'] == 0)
                {
                    continue;
                }
                $parse['click'] = '&nbsp;';
                $parse['TechRequirementsPlace'] = GetElementTechReq($CurrentUser, $CurrentPlanet, $Element);
            }

            $BuildingPage .= parsetemplate($SubTemplate, $parse);
        }
    }

    if(!empty($LevelModifiers))
    {
        foreach($LevelModifiers as $ElementID => $Modifier)
        {
            $CurrentPlanet[$_Vars_GameElements[$ElementID]] += $Modifier;
        }
    }

    $parse = $_Lang;

    if($Queue['lenght'] > 0)
    {
        include($_EnginePath.'includes/functions/InsertBuildListScript.php');
        $parse['BuildListScript'] = InsertBuildListScript('buildings');
        $parse['BuildList'] = $Queue['buildlist'];
    }
    else
    {
        $parse['BuildListScript'] = '';
        $parse['BuildList'] = '';
    }

    $parse['planet_field_current'] = $CurrentPlanet['field_current'];
    $parse['planet_field_max'] = CalculateMaxPlanetFields($CurrentPlanet);
    $parse['field_libre'] = $parse['planet_field_max'] - $CurrentPlanet['field_current'];

    $parse['BuildingsList'] = $BuildingPage;

    display(parsetemplate(gettemplate('buildings_builds'), $parse), $_Lang['Builds']);
}

?>
