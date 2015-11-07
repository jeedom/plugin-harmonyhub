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
    
    public static function health() {
        $return = array();
        $dep=false;
        $config=false;
        $filename = realpath(dirname(__FILE__) . '/../../3rdparty/Harmonyhubcontrol/HarmonyHubControl');
        if (file_exists($filename)) {
            $dep=true;
        }
        if (file_exists('/tmp/harmonyhubconfig.json')) {
            $config=true;
        }
        $return[] = array(
            'test' => __('Dépendances', __FILE__),
            'result' => ($dep) ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => ($dep) ? '' : __('Vérifiez que vous avez les droits Sudo et allez sur la page du plugin et cliquez sur le bouton "Installer Dépendances"', __FILE__),
            'state' => $dep,
        );
        $return[] = array(
            'test' => __('Fichier de config', __FILE__),
            'result' => ($config) ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => ($config) ? '' : __('Vérifiez que vous avez les droits Sudo et allez sur la page du plugin et cliquez sur le bouton "Créer/Maj config"', __FILE__),
            'state' => $config,
        );
        return $return;
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
	
	public static function updateharmonyhub() {
		log::remove('harmonyhub_update');
		$cmd = '/bin/bash ' .dirname(__FILE__) . '/../../3rdparty/install.sh';
		$cmd .= ' >> ' . log::getPathToLog('harmonyhub_update') . ' 2>&1 &';
		exec($cmd);
	}
	
	public static function start() {
		foreach (eqLogic::byType('harmonyhub') as $harmonyhub) {
            $harmonyhub->configharmonyhub();
       }
	}
	
	public static function configharmonyhub() {
		log::remove('harmonyhub_update');
		$email = config::byKey('username', 'harmonyhub', 0);
		$pass = config::byKey('password', 'harmonyhub', 0);
		$ip = config::byKey('ip', 'harmonyhub', 0);
		$cmd = '/usr/bin/python ' .dirname(__FILE__) . '/../../3rdparty/PyHarmony/harmony/__main__.py --email '. $email .' --password "'. $pass . '" --harmony_ip '.$ip.' show_config';
		log::add('harmonyhub_update','debug',"########Recherche de la config en cours########");
		$config=str_replace('\\','\\\\',trim(shell_exec($cmd)));
		$result_json=json_decode($config,true);
		log::add('harmonyhub','debug',$cmd);
		log::add('harmonyhub_update','debug','######### Dispositifs trouvés |');
		foreach ($result_json["device"] as $key => $value) {
			log::add('harmonyhub_update','debug',$value["name"].' | ');
		}
		log::add('harmonyhub_update','debug','######### Activités trouvées |');
		foreach ($result_json["activity"] as $key => $value) {
			log::add('harmonyhub_update','debug',$value["name"].' | ');
		}
		$file='/tmp/harmonyhubconfig.json';
		file_put_contents($file, $config);
		log::add('harmonyhub_update','debug',"#### Fin de la recherche####");
		log::add('harmonyhub','debug','Sortie console : ' .$config);
		
	}
	
	public function getactivityInfo() {
		$harmony_path = realpath(dirname(__FILE__) . '/../../3rdparty/Harmonyhubcontrol/');
		$email = config::byKey('username', 'harmonyhub', 0);
		$pass = config::byKey('password', 'harmonyhub', 0);
		$ip = config::byKey('ip', 'harmonyhub', 0);
		$cmd='./HarmonyHubControl ' .$email.' "' .$pass . '" ' . $ip . ' get_current_activity_id_raw 2>&1';
		//log::add('harmonyhub','debug','Execution de :'. $cmd);
		chdir($harmony_path);
		$activityid=trim(shell_exec($cmd));
		$config=file_get_contents ( '/tmp/harmonyhubconfig.json');
		$result_json=json_decode($config,true);
		$activityname='N/A';
		foreach ($result_json["activity"] as $value) {
			$listid=$value['id'];
			if ($listid==$activityid){
				$activityname=$value['name'];
			}
		}
        $cmd_activity_info = $this->getCmd(null, 'activityinfo');
        if (is_object($cmd_activity_info)) {
            try {
                if ($cmd_activity_info->execCmd(null,2)==$activityname){
                    return $activityname;
                }
            } catch (Exception $e) {
                log::add('harmonyhub','debug','Première création de l\'équipement activité');
            }
        }
		log::add('harmonyhub','debug','L\'activité id est : ' .$activityid.' et son nom est ' . $activityname);
		foreach ($this->getCmd('info') as $cmd) {
			$cmd->event($activityname);
			$this->refreshWidget();
		}
		return $activityname;
	}
	
	public function preUpdate() {
		$config=file_get_contents ( '/tmp/harmonyhubconfig.json');
		$result_json=json_decode($config,true);
		if ($this->getConfiguration('dispositifid')=='activity'){
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
			foreach ($this->getCmd(null, null) as $actual){
				foreach ($result_json["device"] as $key => $value) {
					if ($value["id"]==$this->getConfiguration('dispositifid')){
						$devicejson=$value;
					}
				}
				foreach ($devicejson["commands"] as $value) {
					$command=explode("@$@", $value);
					$action=$command[1];
					$list[] = $action;
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
				$name=$value['name'];
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
			}
			$harmonyhubCmd->setEqLogic_id($this->getId());
			$harmonyhubCmd->setConfiguration('parameters', 'N/A');
			$harmonyhubCmd->setUnite('');
			$harmonyhubCmd->setType('info');
			$harmonyhubCmd->setEventOnly(1);
			$harmonyhubCmd->setIsVisible(0);
			$harmonyhubCmd->setSubType('string');
			$harmonyhubCmd->save();
            $harmonyhubCmd = $this->getCmd(null, 'refreshactivity');
			if (!is_object($harmonyhubCmd)) {
				$harmonyhubCmd = new harmonyhubCmd();
				$harmonyhubCmd->setName(__('Refresh Activity', __FILE__));
				$harmonyhubCmd->setLogicalId('refreshactivity');
				$harmonyhubCmd->setEqLogic_id($this->getId());
				$harmonyhubCmd->setConfiguration('parameters', 'N/A');
				$harmonyhubCmd->setUnite('');
				$harmonyhubCmd->setType('action');
				$harmonyhubCmd->setIsVisible(0);
				$harmonyhubCmd->setSubType('other');
				$harmonyhubCmd->save();
			}
			$this->getactivityInfo();
		} else {
			foreach ($result_json["device"] as $key => $value) {
				if ($value["id"]==$this->getConfiguration('dispositifid')){
					$devicejson=$value;
				}
			}
			$this->setConfiguration('dispoid', $devicejson["id"]);
			$this->setConfiguration('disponame', $devicejson["name"]);
			
			foreach ($devicejson["commands"] as $value) {
				$command=explode("@$@", $value);
				$name=$command[0];
				if ($name=='#'){
					$name='Diese';
				}
				$action=$command[1];
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
 
class harmonyhubCmd extends cmd {
    /*     * *************************Attributs****************************** */
	


    /*     * ***********************Methode static*************************** */

    /*     * *********************Methode d'instance************************* */
	public function execute($_options =null) {
		$harmonyhub = $this->getEqLogic();
        $harmony_path = realpath(dirname(__FILE__) . '/../../3rdparty/Harmonyhubcontrol/');
		$email = config::byKey('username', 'harmonyhub', 0);
		$pass = config::byKey('password', 'harmonyhub', 0);
		$ip = config::byKey('ip', 'harmonyhub', 0);
		$device = $harmonyhub->getConfiguration('dispoid');
        $logical = $this->getLogicalId();
		if ($this->type == 'action' && $logical != 'refreshactivity') {
			$action=$this->getConfiguration('parameters');
			$type=$this->getConfiguration('type');
			if ($type=='activity'){
				$cmd='./HarmonyHubControl ' .$email.' "' .$pass . '" ' . $ip . ' start_activity "'.$action.'"';
			} else {
				$cmd='./HarmonyHubControl ' .$email.' "' .$pass . '" ' . $ip . ' issue_device_command '.$device.' "'.$action.'"';
			}
			chdir($harmony_path);
			exec($cmd);
			log::add('harmonyhub','debug','Execution de : ' .$cmd . ' depuis : ' .$harmony_path);
		}
        else {
			$activityname=$harmonyhub->getactivityInfo();
			return $activityname;
		}
    }

}

?>