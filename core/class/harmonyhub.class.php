<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class harmonyhub extends eqLogic {

    public static function deamon_info() {
        $return = array();
        $return['log'] = __CLASS__;
        $return['launchable'] = 'ok';
        $return['state'] = 'nok';
        $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        if (file_exists($pid_file)) {
            if (@posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
            }
        }
        return $return;
    }

    public static function deamon_stop() {
        $pid_file = jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        if (file_exists($pid_file)) {
            $pid = intval(trim(file_get_contents($pid_file)));
            system::kill($pid);
        }
        sleep(1);
        system::kill('harmonyd.py');
        // system::fuserk(config::byKey('socketport', __CLASS__));
    }

    public static function deamon_start() {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }

        $path = realpath(__DIR__ . '/../../resources');
        $cmd = system::getCmdPython3(__CLASS__) . " {$path}/harmonyd.py";
        $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
        $cmd .= ' --socketport ' . 24123;
        $cmd .= ' --cycle ' . config::byKey('cycle', __CLASS__, 0.5);
        $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/harmonyhub/core/php/harmonyhub.php';
        $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
        $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/daemon.pid';
        $cmd .= ' --harmony_ip ' . escapeshellarg(config::byKey('ip', 'harmonyhub'));
        log::add(__CLASS__, 'info', 'Lancement démon');
        exec($cmd . ' >> ' . log::getPathToLog(__CLASS__ . '_daemon') . ' 2>&1 &');
        $i = 0;
        while ($i < 10) {
            $deamon_info = self::deamon_info();
            if ($deamon_info['state'] == 'ok') {
                break;
            }
            sleep(1);
            $i++;
        }
        if ($i >= 10) {
            log::add(__CLASS__, 'error', __('Impossible de lancer le démon', __FILE__), 'unableStartDeamon');
            return false;
        }
        message::removeAll(__CLASS__, 'unableStartDeamon');

        return true;
    }

    public static function createHubs(array $hubs) {
        foreach ($hubs as $hubId => $hub_data) {
            /** @var harmonyhub */
            $hub = eqLogic::byLogicalId($hubId, __CLASS__);
            if (!is_object($hub)) {
                // search by ip to migrate eqLogic
                /** @var harmonyhub */
                $hub = eqLogic::byLogicalId($hub_data['ip_address'], __CLASS__);
                if (is_object($hub)) {
                    $hub->setLogicalId($hubId);
                }
            }
            if (!is_object($hub)) {
                log::add(__CLASS__, 'info', "Create new hub '{$hub_data['name']}' with ip {$hub_data['ip_address']} and id {$hubId}");
                $hub = new self();
                $hub->setLogicalId($hubId);
                $hub->setName($hub_data['name']);
                $hub->setEqType_name(__CLASS__);
                $hub->setIsVisible(1);
                $hub->setIsEnable(1);
            }
            $hub->setConfiguration('hub_name', $hub_data['name']);
            $hub->setConfiguration('hub_ip', $hub_data['ip_address']);
            $hub->save();

            if (isset($hub_data['Activities'])) {
                $hub->createActivityCommands($hub_data['Activities']);
            }

            if (isset($hub_data['Devices'])) {
                foreach ($hub_data['Devices'] as $name => $device_data) {
                    self::createDevice($hub, $name, $device_data);
                }
            }
        }
    }

    private function createActivityCommands($activities) {
        foreach ($activities as $activity_id => $activity_name) {
            $cmd = $this->getCmd('action', $activity_id);
            if (!is_object($cmd)) {
                $cmd = new harmonyhubCmd();
                $cmd->setName($activity_name);
                $cmd->setLogicalId($activity_id);
                $cmd->setEqLogic_id($this->getId());
                $cmd->setConfiguration('action_id', 'start_activity');
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setIsVisible(1);
                $cmd->save();
            }
        }
        $cmd = $this->getCmd('info', 'current_activity');
        if (!is_object($cmd)) {
            $cmd = new harmonyhubCmd();
            $cmd->setName(__('Activité Courante', __FILE__));
            $cmd->setLogicalId('current_activity');
            $cmd->setEqLogic_id($this->getId());
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setIsVisible(1);
            $cmd->save();
        }
        $cmd = $this->getCmd('info', 'starting_activity');
        if (!is_object($cmd)) {
            $cmd = new harmonyhubCmd();
            $cmd->setName(__('Démarrage activité', __FILE__));
            $cmd->setLogicalId('starting_activity');
            $cmd->setEqLogic_id($this->getId());
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setIsVisible(1);
            $cmd->save();
        }
    }

    private static function createDevice(harmonyhub $hub, string $name, array $data) {
        assert(isset($data['id']) && isset($data['commands']), "Expected id & commands to create device");

        /** @var harmonyhub */
        $device = eqLogic::byLogicalId($data['id'], __CLASS__);
        if (!is_object($device)) {
            log::add(__CLASS__, 'info', "Create new device '{$name}' with id {$data['id']}");
            $device = new self();
            $device->setLogicalId($data['id']);
            $device->setName($name);
            $device->setEqType_name(__CLASS__);
            $device->setIsVisible(0);
            $device->setIsEnable(1);
        }
        $device->setConfiguration('hub_id', $hub->getLogicalId());
        $device->setConfiguration('hub_name', $hub->getName());
        $device->setConfiguration('hub_ip', $hub->getConfiguration('hub_ip'));
        $device->save();

        foreach ($data['commands'] as $cmd) {
            $device->createDeviceCommand($cmd);
        }
    }

    private function createDeviceCommand($cmdId) {
        $cmd = $this->getCmd('action', $cmdId);
        if (!is_object($cmd)) {
            $cmd = new harmonyhubCmd();
            $cmd->setLogicalId($cmdId);
            $cmd->setName($cmdId);
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setEqLogic_id($this->getId());
            $cmd->setConfiguration('action_id', 'send_command');
            try {
                $cmd->save();
            } catch (\Throwable $th) {
                $cmd->setName($cmdId + "_new");
                try {
                    $cmd->save();
                } catch (\Throwable $th) {
                    log::add(__CLASS__, 'error', "Impossible de sauvegarder la commande {$cmdId} sur l'équipement {$this->getName()}, veuillez supprimer l'ancienne commande ou l'équipement pour réessayer");
                }
            }
        }
    }

    public function preInsert() {
        $this->setConfiguration('icon', 'generic.png');
    }

    public function getImage() {
        $icon = $this->getConfiguration('icon');
        if ($icon == '') {
            return parent::getImage();
        }
        return "plugins/harmonyhub/core/template/images/{$icon}";
    }

    public static function sendToDaemon($params) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] != 'ok') {
            throw new RuntimeException("Le démon n'est pas démarré");
        }
        $port = 24123;

        log::add(__CLASS__, 'debug', 'params to send to daemon:' . json_encode($params));
        $params['apikey'] = jeedom::getApiKey(__CLASS__);
        $payLoad = json_encode($params);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_connect($socket, '127.0.0.1', $port);
        socket_write($socket, $payLoad, strlen($payLoad));
        socket_close($socket);
    }
}

class harmonyhubCmd extends cmd {
    public function execute($_options = null) {
        /** @var harmonyhub */
        $eqLogic = $this->getEqLogic();
        $action = $this->getConfiguration('action_id');
        switch ($action) {
            case 'start_activity':
                $params = [
                    'action' => $action,
                    'hub_id' => $eqLogic->getLogicalId(),
                    'activity_id' => $this->getLogicalId()
                ];
                $eqLogic->sendToDaemon($params);
                break;
            case 'send_command':
                $params = [
                    'action' => $action,
                    'hub_id' => strval($eqLogic->getConfiguration('hub_id')),
                    'device_id' => $eqLogic->getLogicalId(),
                    'command' => $this->getLogicalId()
                ];
                $eqLogic->sendToDaemon($params);
                break;
            default:
                log::add('harmonyhub', 'warning', "Unknown action: {$action}");
                return;
        }
    }
}
