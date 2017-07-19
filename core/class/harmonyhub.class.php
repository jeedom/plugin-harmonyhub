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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';


class harmonyhub extends eqLogic {
    
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'harmonyhub_update';
		$return['progress_file'] = jeedom::getTmpFolder('harmonyhub') . '/dependance';
		$cmd = "pip list | grep requests";
        exec($cmd, $output, $return_var);
		$return['state'] = 'nok';
		if (array_key_exists(0,$output)) {
            if ($output[0] != "") {
                $return['state'] = 'ok';
            }
        }
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('harmonyhub') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}
    
	
	public static function cron() {
		$eqLogics = eqLogic::byType('harmonyhub');
		foreach($eqLogics as $harmonyhub) {
			if ($harmonyhub->getIsEnable() == 1 && $harmonyhub->getConfiguration('disponame')=='Activité' && $harmonyhub->getConfiguration('cronenabled')==1) {
				foreach ($harmonyhub->getCmd('info') as $cmd) {
					//log::add('harmonyhub', 'debug', 'Pull Cron pour harmonyhub' );
					$activityname= $harmonyhub->getactivityInfo();
				}
			}
		}
	}
	
	public static function getdevicelist($_ip,$_id = '') {
		$result_json = array();
		$data_path = dirname(__FILE__) . '/../../data';
		if (!file_exists($data_path)) {
			exec('mkdir ' . $data_path . ' && chmod 775 -R ' . $data_path . ' && chown -R www-data:www-data ' . $data_path);
		}
		$file= $data_path . '/' . str_replace('.','',$_ip);
		if (file_exists($file)){
			$config=file_get_contents($file);
			$result_json=json_decode($config,true);
		}
		$selected ='';
		if ($_id != ''){
			$eqLogic =  eqLogic::byId($_id);
			$selected = $eqLogic->getConfiguration('dispositifid','');
		}
		return [$result_json,$selected];
	}
	
	public static function configharmonyhub() {
		log::remove('harmonyhub_update');
		$ips = explode('|',config::byKey('ip', 'harmonyhub', 0));
		foreach ($ips as $ip) {
			$cmd = 'sudo /usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/PyHarmony/harmony/__main__.py --harmony_ip '.$ip.' show_config';
			log::add('harmonyhub_update','alert',"########Recherche de la config en cours########");
			$config=trim(shell_exec($cmd));
			$result_json=json_decode($config,true);
			log::add('harmonyhub_update','alert','######### Dispositifs trouvés ' . $ip);
			foreach ($result_json['device'] as $device) {
				log::add('harmonyhub_update','alert',$device["label"].' | ');
			}
			log::add('harmonyhub_update','alert','######### Activités trouvées ' . $ip);
			foreach ($result_json["activity"] as $activity) {
				log::add('harmonyhub_update','alert',$activity["label"].' | ');
			}
			$data_path = dirname(__FILE__) . '/../../data';
			if (!file_exists($data_path)) {
				exec('mkdir ' . $data_path . ' && chmod 775 -R ' . $data_path . ' && chown -R www-data:www-data ' . $data_path);
			}
			$file= $data_path . '/' . str_replace('.','',$ip);
			file_put_contents($file, $config);
			log::add('harmonyhub_update','alert',"#### Fin de la recherche " . $ip);
			log::add('harmonyhub','debug','Sortie console : ' .$config);
		}
	}
	
	public function getactivityInfo() {
		$ip = $this->getConfiguration('hubIp','');
		if ($ip != ''){
			$ip = config::byKey('ip', 'harmonyhub', 0);
			$cmd = 'sudo /usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/PyHarmony/harmony/__main__.py --harmony_ip '.$ip.' show_current_activity';
			log::add('harmonyhub','debug','Execution de :'. $cmd);
			$activityid=trim(shell_exec($cmd));
			foreach ($this->getCmd('info') as $cmd) {
				$cmd->event($activityid);
			}
			return $activityid;
		}
	}
	
	
	public function getImage() {
		$file = $this->getConfiguration('icone','plugins/harmonyhub/plugin_info/harmonyhub_icon.png');
		return $file;
	}
	
	public function preUpdate() {
		$result_json = array();
		$data_path = dirname(__FILE__) . '/../../data';
		$file= $data_path . '/' . str_replace('.','',$this->getConfiguration('hubIp'));
		if (file_exists($file)){
			$config=file_get_contents($file);
			$result_json=json_decode($config,true);
		}
		if ($this->getConfiguration('dispositifid')=='activity'){
			$list = array();
			foreach ($this->getCmd(null, null) as $actual){
				foreach ($result_json["activity"] as $value) {
					$action=$value['id'];
					$list[] = $action;
				}
				if (!in_array($actual->getLogicalId(),$list) && $actual->getLogicalId()!='activityinfo'){
					$actual->remove();
				}
			}
		} else {
			$list = array();
			foreach ($this->getCmd(null, null) as $actual){
				foreach ($result_json["device"] as $device) {
					if ($device["id"]==$this->getConfiguration('dispositifid')){
						foreach ($device["controlGroup"] as $controlGroup) {
							foreach ($controlGroup["function"] as $function) {
								$action=$function["name"];
								$list[] = $action;
							}
						}
					}
				}
				if (!in_array($actual->getLogicalId(),$list)){
					$actual->remove();
				}
			}
		}
		//Recherche de l'id et du name
		if ($this->getConfiguration('dispositifid')=='activity'){
			$this->setConfiguration('dispoid', 'Pas d\'id');
			$this->setConfiguration('disponame', 'Activité');
			foreach ($result_json["activity"] as $value) {
				$name= $value['label'];
				$action=$value['id'];
				$harmonyhubCmd = $this->getCmd(null, $action);
				if (!is_object($harmonyhubCmd)) {
					$harmonyhubCmd = new harmonyhubCmd();
					$harmonyhubCmd->setName(__($name, __FILE__));
					$harmonyhubCmd->setLogicalId($action);
					$harmonyhubCmd->setEqLogic_id($this->getId());
					$harmonyhubCmd->setConfiguration('parameters', $action);
					$harmonyhubCmd->setConfiguration('type', 'activity');
					$harmonyhubCmd->setType('action');
					$harmonyhubCmd->setSubType('other');
					$harmonyhubCmd->setIsVisible(0);
					$harmonyhubCmd->save();
				}
			}
			$harmonyhubCmd = $this->getCmd(null, 'activityinfo');
			if (!is_object($harmonyhubCmd)) {
				$harmonyhubCmd = new harmonyhubCmd();
				$harmonyhubCmd->setName(__('Activité Courante', __FILE__));
				$harmonyhubCmd->setLogicalId('activityinfo');
				$harmonyhubCmd->setIsVisible(0);
			}
			$harmonyhubCmd->setEqLogic_id($this->getId());
			$harmonyhubCmd->setConfiguration('parameters', 'N/A');
			$harmonyhubCmd->setUnite('');
			$harmonyhubCmd->setType('info');
			$harmonyhubCmd->setSubType('string');
			$harmonyhubCmd->save();
            $harmonyhubCmd = $this->getCmd(null, 'refreshactivity');
			if (!is_object($harmonyhubCmd)) {
				$harmonyhubCmd = new harmonyhubCmd();
				$harmonyhubCmd->setName(__('Refresh Activity', __FILE__));
				$harmonyhubCmd->setEqLogic_id($this->getId());
				$harmonyhubCmd->setConfiguration('parameters', 'N/A');
				$harmonyhubCmd->setUnite('');
				$harmonyhubCmd->setType('action');
				$harmonyhubCmd->setSubType('other');
			}
			$harmonyhubCmd->setLogicalId('refresh');
			$harmonyhubCmd->save();
			$this->getactivityInfo();
		} else {
			foreach ($result_json["device"] as $device) {
				if ($device["id"]==$this->getConfiguration('dispositifid')){
					$this->setConfiguration('dispoid', $device["id"]);
					$this->setConfiguration('disponame', $device["label"]);
					foreach ($device["controlGroup"] as $controlGroup) {
						foreach ($controlGroup["function"] as $function) {
							$action=$function["name"];
							$name=str_replace('#','sharp',$function["label"]);
							$harmonyhubCmd = $this->getCmd(null, $action);
							if (!is_object($harmonyhubCmd)) {
								$harmonyhubCmd = new harmonyhubCmd();
								$harmonyhubCmd->setName(__($name, __FILE__));
								$harmonyhubCmd->setLogicalId($action);
								$harmonyhubCmd->setEqLogic_id($this->getId());
								$harmonyhubCmd->setConfiguration('parameters', $action);
								$harmonyhubCmd->setConfiguration('type', 'device');
								$harmonyhubCmd->setType('action');
								$harmonyhubCmd->setSubType('other');
								$harmonyhubCmd->setIsVisible(0);
								$harmonyhubCmd->save();
							}
						}
					}
				}
			}
		}
	}
}
 
class harmonyhubCmd extends cmd {
    /*     * *************************Attributs****************************** */
	


    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */
	public function execute($_options =null) {
		$harmonyhub = $this->getEqLogic();
        $harmony_path = realpath(dirname(__FILE__) . '/../../3rdparty/Harmonyhubcontrol/');
		$ip = $harmonyhub->getConfiguration('hubIp');
		$device = $harmonyhub->getConfiguration('dispoid');
        $logical = $this->getLogicalId();
        $refreshactivity = 0;
		if ($this->type == 'action' && $logical != 'refreshactivity') {
			$action=$this->getConfiguration('parameters');
			$type=$this->getConfiguration('type');
			if ($type=='activity'){
				$cmd = 'sudo /usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/PyHarmony/harmony/__main__.py --harmony_ip '.$ip.' start_activity --activity ' . $action;
			} else {
				$cmd = 'sudo /usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/PyHarmony/harmony/__main__.py --harmony_ip '.$ip.' send_command  --device_id ' . $device . ' --command ' . $action;
			}
			exec($cmd);
			log::add('harmonyhub','debug','Execution de : ' .$cmd);
		}
        else {
			$activityname=$harmonyhub->getactivityInfo();
			return $activityname;
		}
    }

}

?>
