<?php

/* This file is part of NextDom.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom. If not, see <http://www.gnu.org/licenses/>.
 */


/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once 'shuttersCmd.class.php';

class shutters extends eqLogic
{
    /*     * *************************Attributs****************************** */

    private static $_externalConditions = ['fireCondition', 'absenceCondition', 'presenceCondition', 'outdoorLuminosityCondition', 'outdoorTemperatureCondition', 'firstUserCondition', 'secondUserCondition'];

    /*     * ***********************Methode static*************************** */

    /*
    * Fonction exécutée automatiquement toutes les minutes par NextDom
    public static function cron() {

    }
    */

    /*
    * Fonction exécutée automatiquement toutes les heures par NextDom
      public static function cronHourly() {

    }
    */

    /*
    * Fonction exécutée automatiquement tous les jours par NextDom
      public static function cronDaily() {

    }
    */
    public static function start()
    {
        log::add('shutters', 'debug', 'shutters::start()');
        self::updateEventsManagement();
    }

    public static function stop()
    {
        log::add('shutters', 'debug', 'shutters::stop()');
    }

    private static function updateEventsManagement() 
    {
        foreach (eqLogic::byType('shutters', false) as $eqLogic) {
            if (!is_object($eqLogic) || $eqLogic->getConfiguration('eqType', null) !== 'shutter') {
                continue;
            }
        
            $eqLogicName = $eqLogic->getName();
            $eqLogicId = $eqLogic->getId();

            $conditionsWithEvent = [];

            $conditionsEventListener = listener::byClassAndFunction('shutters', 'externalConditionsEvents', ['shutterId' => $eqLogic->getId()]);
            $heliotropeEventListener = listener::byClassAndFunction('shutters', 'heliotropeZoneEvents', array('shutterId' => $eqLogic->getId()));
            $sunriseCron =cron::byClassAndFunction('shutters', 'sunriseEvent', array('shutterId' => $eqLogic->getId()));
            $sunsetCron =cron::byClassAndFunction('shutters', 'sunsetEvent', array('shutterId' => $eqLogic->getId()));

            if ($eqLogic->getIsEnable()) {
                $externalConditionsId = str_replace('#', '', $eqLogic->getConfiguration('externalConditionsId', null));
                if (!empty($externalConditionsId) && $externalConditionsId !== 'none') {
                    $externalConditionsEqLogic = shutters::byId($externalConditionsId);
                    if (is_object($externalConditionsEqLogic)) {
                        if ($externalConditionsEqLogic->getIsEnable()) {
                            if (!is_object($conditionsEventListener)) {
                                $conditionsEventListener = new listener();
                                $conditionsEventListener->setClass('shutters');
                                $conditionsEventListener->setFunction('externalConditionsEvents');
                                $conditionsEventListener->setOption(['shutterId' => $eqLogic->getId()]);
                                $conditionsEventListener->emptyEvent();
                                $conditionsEventListener->save();
                                $conditionsEventListenerId = $conditionsEventListener->getId();
                                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : external conditions events listener [' . $conditionsEventListenerId . '] successfully created for shutter [' . $eqLogicName . ']');
                            } else {
                                $conditionsEventListener->emptyEvent();
                                $conditionsEventListener->save();
                                $conditionsEventListenerId = $conditionsEventListener->getId();
                            }
                            $conditionsWithEvent['primaryConditionsPriority'] = $externalConditionsEqLogic->getConfiguration('primaryConditionsPriority', null);
                            foreach (self::$_externalConditions as $condition) {
                                $cmdId = str_replace('#', '', $externalConditionsEqLogic->getConfiguration($condition, null));
                                if (!empty($cmdId)) {
                                    $cmd = cmd::byId($cmdId);
                                    if (is_object($cmd)) {
                                        $conditionsWithEvent[$condition] = ['cmdId' => $cmdId, 'status' => $externalConditionsEqLogic->getConfiguration($condition . 'Status', null)];
                                        $conditionsEventListener->addEvent($cmdId);
                                        log::add('shutters', 'debug', 'shutters::updateEventsManagement() : cmd [' . $cmdId  . '] configured in externalConditions [' . $externalConditionsId . '] successfully added to listener [' . $conditionsEventListenerId . '] for shutter [' . $eqLogicName . ']');
                                    } else {
                                        log::add('shutters', 'debug', 'shutters::updateEventsManagement() : cmd  [' . $cmdId  . '] configured in externalConditions [' . $externalConditionsId . '] doesn\'t exist');
                                    }
                                }
                            }
                            $conditionsEventListener->save();
                        } else {
                            $conditionsEventListener->emptyEvent();
                            $conditionsEventListener->save();
                            log::add('shutters', 'debug', 'shutters::updateEventsManagement() : externalConditions [' . $externalConditionsId . '] isn\'t activated for shutter [' . $eqLogicName . ']');
                        } 
                    } else {
                        $conditionsEventListener->emptyEvent();
                        $conditionsEventListener->save();
                        log::add('shutters', 'debug', 'shutters::updateEventsManagement() : externalConditions [' . $externalConditionsId . '] doesn\'t exist for shutter [' . $eqLogicName . ']');
                    }
                } else {
                    if (is_object($conditionsEventListener)) {
                        $conditionsEventListener->emptyEvent();
                        $conditionsEventListener->save();
                    }
                }

                $heliotropeZoneId = str_replace('#', '', $eqLogic->getConfiguration('heliotropeZoneId', null));
                if (!empty($heliotropeZoneId) && $heliotropeZoneId !== 'none') {
                   $heliotropeZoneEqLogic = shutters::byId($heliotropeZoneId);
                    if (is_object($heliotropeZoneEqLogic)) {
                        if ($heliotropeZoneEqLogic->getIsEnable()) {
                            if (!is_object($heliotropeEventListener)) {
                                $heliotropeEventListener = new listener();
                                $heliotropeEventListener->setClass('shutters');
                                $heliotropeEventListener->setFunction('heliotropeZoneEvents');
                                $heliotropeEventListener->setOption(array('shutterId' => $eqLogic->getId()));
                                $heliotropeEventListener->emptyEvent();
                                $heliotropeEventListener->save();
                                $heliotropeEventListenerId = $heliotropeEventListener->getId();
                                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : heliotrope events listener [' . $heliotropeEventListenerId . '] successfully created for shutter [' . $eqLogicName . ']');
                            } else {
                                $heliotropeEventListener->emptyEvent();
                                $heliotropeEventListener->save();
                                $heliotropeEventListenerId = $heliotropeEventListener->getId();
                            }
                            if (!is_object($sunriseCron)) {
                                $sunriseCron = new cron();
                                $sunriseCron->setClass('shutters');
                                $sunriseCron->setFunction('sunriseEvent');
                                $sunriseCron->setOption(array('shutterId' => $eqLogic->getId()));
                                $sunriseCron->setDeamon(0);
                                $sunriseCron->setOnce(0);
                                $sunriseCron->setTimeout(2);
                                $sunriseCron->setSchedule('* * * * * *');
                                $sunriseCron->save();
                                $sunriseCronId = $sunriseCron->getId();
                                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : sunrise cron [' . $sunriseCronId . '] successfully created for shutter [' . $eqLogicName . ']');
                            }else{
                                $sunriseCron->setSchedule('* * * * * *');
                                $sunriseCron->save();
                                $sunriseCronId = $sunriseCron->getId();
                            }
                            if (!is_object($sunsetCron)) {
                                $sunsetCron = new cron();
                                $sunsetCron->setClass('shutters');
                                $sunsetCron->setFunction('sunsetEvent');
                                $sunsetCron->setOption(array('shutterId' => $eqLogic->getId()));
                                $sunsetCron->setDeamon(0);
                                $sunsetCron->setOnce(0);
                                $sunsetCron->setTimeout(2);
                                $sunsetCron->setSchedule('* * * * * *');
                                $sunsetCron->save();
                                $sunsetCronId = $sunsetCron->getId();
                                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : sunset cron [' . $sunsetCronId . '] successfully created for shutter [' . $eqLogicName . ']');
                            }else{
                                $sunsetCron->setSchedule('* * * * * *');
                                $sunsetCron->save();
                                $sunsetCronId = $sunsetCron->getId();
                            }
                            $heliotropeId = str_replace('#', '', $heliotropeZoneEqLogic->getConfiguration('heliotrope', null));
                            if(!empty($heliotropeId) && $heliotropeId !== 'none'){
                                $heliotrope=eqlogic::byId($heliotropeId);
                                if(is_object($heliotrope)) {
                                    $heliotropeCmdLogicalId = ['altitude', 'azimuth360'];
                                    foreach ($heliotropeCmdLogicalId as $cmdLogicalId) {
                                        $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, $cmdLogicalId);
                                        if(is_object($cmd)) {
                                            $cmdId = $cmd->getId();
                                            $conditionsWithEvent[$cmdLogicalId] = ['cmdId' => $cmdId, 'status' => null];
                                            $heliotropeEventListener->addEvent($cmdId);
                                            log::add('shutters', 'debug', 'shutters::updateEventsManagement() : cmd [' . $cmdId  . '] from heliotrope [' . $heliotropeId . '] successfully added to listener [' . $heliotropeEventListenerId . '] for shutter [' . $eqLogicName . ']');
                                        } else {
                                            log::add('shutters', 'debug', 'shutters::updateEventsManagement() : cmd [' . $cmdId  . '] from heliotrope [' . $heliotropeId . '] doesn\'t exist for shutter [' . $eqLogicName . ']');
                                        }
                                    }
                                    $heliotropeEventListener->save();
                                    switch ($$heliotropeZoneEqLogic->getConfiguration('dawnType', null)) {
                                        case 'astronomicalDawn':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'aubeast');
                                            break;
                                        case 'nauticalDawn':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'aubenau');
                                            break;
                                        case 'civilDawn':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'aubeciv');
                                            break;
                                        case 'sunrise':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'sunrise');
                                            break;
                                        default:
                                            $cmd = null;
                                            break;
                                    }
                                    if (is_object($cmd)) {
                                        $heliotropeSunriseHour = $cmd->execCmd();
                                    }
                                    $sunriseHour = $heliotropeZoneEqLogic->getConfiguration('sunriseHour', null);
                                    if (!is_numeric($sunriseHour) || $sunriseHour < 600 || $sunriseHour > 1200) {
                                        $sunriseHour = 1200;
                                    }
                                    if ($heliotropeZoneEqLogic->getConfiguration('sunriseHourType', null) === 'min' && $heliotropeSunriseHour > $sunriseHour) {
                                            $sunriseHour = $heliotropeSunriseHour;
                                    }
                                    $schedule = substr($sunriseHour, -2) . ' ' . substr($sunriseHour, 0, -2) . ' * * * *';
                                    $sunriseCron->setSchedule($schedule);
                                    $sunriseCron->setEnable(1);
                                    $sunriseCron->save();
                                    log::add('shutters', 'debug', 'shutters::updateEventsManagement() : sunrise cron [' . $sunriseCronId . '] set to  [' . $schedule . '] for shutter [' . $eqLogicName . ']');
                    
                                    switch ($heliotropeZoneEqLogic->getConfiguration('duskType', null)) {
                                        case 'astronomicalDusk':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'crepast');
                                            break;
                                        case 'nauticalDusk':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'crepnau');
                                            break;
                                        case 'civilDusk':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'crepciv');
                                            break;
                                        case 'sunset':
                                            $cmd = cmd::byEqLogicIdAndLogicalId($heliotropeId, 'sunset');
                                            break;
                                        default:
                                            $cmd = null;
                                            break;
                                    }
                                    if (is_object($cmd)) {
                                        $heliotropeSunsetHour = $cmd->execCmd();
                                    }
                                    $sunsetHour = $heliotropeZoneEqLogic->getConfiguration('sunsetHour', null);
                                    if (!is_numeric($sunsetHour) || $sunsetHour < 1800 || $sunsetHour > 2359) {
                                        $sunsetHour = 1800;
                                    }
                                    if ($heliotropeZoneEqLogic->getConfiguration('sunsetHourType', null) === 'max' && $heliotropeSunsetHour < $sunsetHour) {
                                            $sunsetHour = $heliotropeSunsetHour;
                                    }
                                    $schedule = substr($sunsetHour, -2) . ' ' . substr($sunsetHour, 0, -2) . ' * * * *';
                                    $sunsetCron->setSchedule($schedule);
                                    $sunsetCron->setEnable(1);
                                    $sunsetCron->save();
                                    log::add('shutters', 'debug', 'shutters::updateEventsManagement() : sunset cron [' . $sunsetCronId . '] set to  [' . $schedule . '] for shutter [' . $eqLogicName . ']');
                                } else {
                                    $sunriseCron->remove();
                                    log::add('shutters', 'debug', 'shutters::updateEventsManagement() : heliotrope [' . $heliotropeId . '] configured in heliotropeZone [' . $heliotropeZoneId . '] doesn\'t exist for shutter [' . $eqLogicName . ']');
                                }
                            } else {
                                $sunriseCron->remove();
                                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : no heliotrope configured in heliotropeZone [' . $heliotropeZoneId . '] for shutter [' . $eqLogicName . ']');
                            }
                        } else {
                            $heliotropeEventListener->emptyEvent();
                            $heliotropeEventListener->save();
                            $sunriseCron->remove();
                            $sunsetCron->remove();
                            log::add('shutters', 'debug', 'shutters::updateEventsManagement() : heliotropeZone [' . $heliotropeZoneId . '] isn\'t activated for shutter [' . $eqLogicName . ']');
                        } 
                    } else {
                        $heliotropeEventListener->emptyEvent();
                        $heliotropeEventListener->save();
                        $sunriseCron->remove();
                        $sunsetCron->remove();
                        log::add('shutters', 'debug', 'shutters::updateEventsManagement() : heliotropeZone [' . $heliotropeZoneId . '] doesn\'t exist for shutter [' . $eqLogicName . ']');
                    }
                } else {
                    if (is_object($heliotropeEventListener)) {
                        $heliotropeEventListener->emptyEvent();
                        $heliotropeEventListener->save();
                    }
                    if (is_object($sunriseCron)) {
                        $sunriseCron->remove();
                    }
                    if (is_object($sunsetCron)) {
                        $sunsetCron->remove();
                    }
                }
            } else {
                $conditionsEventListener->emptyEvent();
                $conditionsEventListener->save();
                $heliotropeEventListener->emptyEvent();
                $heliotropeEventListener->save();
                $sunriseCron->remove();
                $sunsetCron->remove();
                log::add('shutters', 'debug', 'shutters::updateEventsManagement() : shutter [' . $eqLogicName . '] isn\'t activated');
            } 
            $eqLogic->setConfiguration('conditionsWithEvent', $conditionsWithEvent);
            $eqLogic->save(true);
        }
    }

    public static function externalConditionsEvents($_option)
    {
        $shutterId = $_option['shutterId'];
        $shutter = shutters::byId($shutterId);
        $cmdId = $_option['event_id'];
        $cmdValue = $_option['value'];
        log::add('shutters', 'debug', 'shutters::externalConditionsEvents() : event received for shutter [' . $shutterId . '] from cmd [' . $cmdId . '] cmd value => ' . $cmdValue);
        //shutters::main($shutterId);
   }

    public static function heliotropeZoneEvents($_option)
    {
        $shutterId = $_option['shutterId'];
        $cmdId = $_option['event_id'];
        $cmdValue = $_option['value'];
        log::add('shutters', 'debug', print_r($_option, true));
        log::add('shutters', 'debug', 'shutters::heliotropeZoneEvents() : event received for shutter [' . $shutterId . '] from cmd [' . $cmdId . '] cmd value => ' . $cmdValue);
    }

    public static function sunriseEvent($_option)
    {

    }

    public static function sunsetEvent($_option)
    {

    }

    private static function main($_shutterId)
    {
        if (empty($_shutterId)) {
            return;
        }
        $shutter = shutters::byId($_shutterId);
        if (!is_object($shutter)) {
            log::add('shutters', 'debug', 'shutters::main() : shutter [' . $_shutterId . '] doesn\'t exist');
            return;
        }
        if (!$shutter->getIsEnable()) {
            log::add('shutters', 'debug', 'shutters::main() : shutter [' . $_shutterId . '] isn\'t activated');
            return;
        }

        $activeCondition = '';
        $conditionsWithEvent = $shutter->getConfiguration('conditionsWithEvent', null);
        $primaryConditions = explode(',', $conditionsWithEvent['primaryConditionsPriority']);
        foreach ($primaryConditions as $condition) {
            if (shuttersCmd::byEqLogicIdAndLogicalId($_shutterId, 'shutter::' . $condition . 'Status')->execCmd() === 'enable') {
                $cmdId = str_replace('#', '', $conditionsWithEvent[$condition]['cmdId']);
                if (!empty($cmdId)) {
                    $cmd = cmd::byId($cmdId);
                    if (is_object($cmd)) {
                        if ($cmd->execCmd() === $conditionsWithEvent[$condition]['status']) {
                            $activeCondition = $condition;
                            break;
                        }
                    }
                }
            }
        }
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert()
    {
 
    }

    public function postInsert()
    {

    }

    public function preSave()
    {
        $thisEqType = $this->getConfiguration('eqType', null);
        $thisName = $this->getName();
        log::add('shutters', 'debug', 'shutters::preSave() : eqLogic[' . $thisName . '] eqType [' . $thisEqType . ']');

        switch ($thisEqType) {
            case 'externalConditions':
                break;
            case 'heliotropeZone':
               break;
            case 'shuttersGroup':
                break;
            case 'shutter':
                break;
            default:
                break;
        }
    }

    public function postSave()
    {
        $thisEqType = $this->getConfiguration('eqType', null);
        $thisName = $this->getName();
        log::add('shutters', 'debug', 'shutters::postSave() : eqLogic[' . $thisName . '] eqType [' . $thisEqType . ']');

        if(!empty($thisEqType)) {
            $this->loadCmdFromConfFile($thisEqType);
        }

        switch ($thisEqType) {
            case 'externalConditions':
                self::updateEventsManagement();
                break;
            case 'heliotropeZone':
                self::updateEventsManagement();
                break;
            case 'shuttersGroup':
                break;
            case 'shutter':
                self::updateEventsManagement();
                break;
            default:
                break;
        }
    }

    public function preUpdate()
    {
        
    }    

    public function postUpdate()
    {

    }

    public function preRemove()
    {
        $thisEqType = $this->getConfiguration('eqType', null);
        $thisName = $this->getName();
        log::add('shutters', 'debug', 'shutters::preRemove() : eqLogic[' . $thisName . '] eqType [' . $thisEqType . ']');

        switch ($thisEqType) {
            case 'externalConditions':
                $this->removeEvents();
                break;
            case 'heliotropeZone':
                $this->removeEvents();
                break;
            case 'shuttersGroup':
                break;
            case 'shutter':
                $this->removeEventListener();
                break;
            default:
                break;
        }
    }
        
    public function postRemove()
    {
        
    }

    private function checkSettings()
    {
        $thisEqType = $this->getConfiguration('eqType', null);

        if (empty($thisEqType)) {
            throw new \Exception (__('Le type d\'équipement doit être renseigné!', __FILE__));
            return;
        }

    }
    
    /**
     * Load commands from JSON file
     */
    private function loadCmdFromConfFile(string $_eqType = '')
    {
        $thisName = $this->getName();
        $file = dirname(__FILE__) . '/../config/devices/' . $_eqType . '.json';
        if (!is_file($file)) {
			//log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : no commands configuration file to import for ['. $thisName . ']');
			return;
		}
		$content = file_get_contents($file);
		if (!is_json($content)) {
			//log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : commands configuration file is not JSON formatted for ['. $thisName . ']');
			return;
		}
		$device = json_decode($content, true);
		if (!is_array($device) || !isset($device['commands'])) {
			//log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : commands configuration file isn\'t well formatted for ['. $thisName . ']');
			return;
		}

        foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $existingCmd) {
				if ((isset($command['logicalId']) && $existingCmd->getLogicalId() === $command['logicalId'])
				    || (isset($command['name']) && $existingCmd->getName() === $command['name'])) {
                    //log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : cmd [' . $command['logicalId'] . '] already exist for ['. $thisName . ']');
                    $cmd = $existingCmd;
					break;
				}
            }
            /*
            if($this->getConfiguration('eqType', null) === 'externalConditions') {
                if(isset($command['configuration']['condition']) && empty($this->getConfiguration($command['configuration']['condition'], null))) {
                    if($cmd !== null || is_object($cmd)) {
                        $cmd->remove();
                        log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : command => ' . $command['logicalId'] . ' successfully removed for => '. $eqLogicName);
                    }
                    continue;
                }
            }
            */
			if ($cmd === null || !is_object($cmd)) {
				$cmd = new shuttersCmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
                //log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : cmd [' . $command['logicalId'] . '] successfully added for ['. $thisName . ']');
            }
        }
        //log::add('shutters', 'debug', 'shutters::loadCmdFromConfFile() : commands successfully imported for ['. $thisName . ']');
    }

    private function removeEvents()
    {
        $thisName = $this->getName();
        $thisId = $this->getId();
        $thisEqType = $this->getConfiguration('eqType', null);
        log::add('shutters', 'debug', 'shutters::removeEvents() : eqLogic[' . $thisName . '] eqType [' . $thisEqType . ']');

        foreach (eqLogic::byType('shutters', true) as $eqLogic) {
            if (!is_object($eqLogic) || $eqLogic->getConfiguration('eqType', null) !== 'shutter') {
                continue;
            }
            if ($thisId === $eqLogic->getConfiguration('externalConditionsId', null)) {
                $listener = listener::byClassAndFunction('shutters', 'externalConditionsEvents', array('shutterId' => $eqLogic->getId()));
                $listener->emptyEvent();
                $listener->save();
                $listener->remove();
                }
            if ($thisId === $eqLogic->getConfiguration('heliotropeZoneId', null)) {
                $listener = listener::byClassAndFunction('shutters', 'heliotropeZoneEvents', array('shutterId' => $eqLogic->getId()));
                $listener->emptyEvent();
                $listener->save();
                $listener->remove();
                $sunriseCron =cron::byClassAndFunction('shutters', 'sunriseEvent', array('shutterId' => $eqLogic->getId()));
                $sunriseCron->remove();
                $sunsetCron =cron::byClassAndFunction('shutters', 'sunsetEvent', array('shutterId' => $eqLogic->getId()));
                $sunsetCron->remove();
            }
            log::add('shutters', 'debug', 'shutters::removeEvents() : [' . $thisName . '] events successfully removed for shutter [' . $eqLogic->getName() . ']');
        }
    }

    private function removeEventsListener()
    {
        $thisName = $this->getName();
        $thisEqType = $this->getConfiguration('eqType', null);
        log::add('shutters', 'debug', 'shutters::removeEventListener() : eqLogic[' . $thisName . '] eqType [' . $thisEqType . ']');
        $listener = listener::byClassAndFunction('shutters', 'externalConditionsEvents', array('shutterId' => $this->getId()));
        if (is_object($listener)) {
            $listener->emptyEvent();
            $listener->save();
            $listener->remove();
            log::add('shutters', 'debug', 'shutters::removeEventListener() : external conditions events listener [' . $listener->getId() . '] successfully removed for shutter [' . $thisName . ']');
        }

        $listener = listener::byClassAndFunction('shutters', 'heliotropeZoneEvents', array('shutterId' => $this->getId()));
        if (is_object($listener)) {
            $listener->emptyEvent();
            $listener->save();
            $listener->remove();
            log::add('shutters', 'debug', 'shutters::removeEventListener() : heliotrope events listener [' . $listener->getId() . '] successfully removed for shutter [' . $thisName . ']');
        }

        $cron = cron::byClassAndFunction('shutters', 'sunriseEvent', array('shutterId' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
            log::add('shutters', 'debug', 'shutters::removeEventListener() : sunrise cron [' . $cron->getId() . '] successfully removed for shutter [' . $thisName . ']');
        }

        $cron = cron::byClassAndFunction('shutters', 'sunsetEvent', array('shutterId' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
            log::add('shutters', 'debug', 'shutters::removeEventListener() : sunset cron [' . $cron->getId() . '] successfully removed for shutter [' . $thisName . ']');
        }
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous 
     en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après 
     modification de variable de configuration
      public static function postConfig_<Variable>() {
      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant 
     modification de variable de configuration
      public static function preConfig_<Variable>() {
      }
     */

    /*     * **********************Getteur Setteur*************************** */

}
