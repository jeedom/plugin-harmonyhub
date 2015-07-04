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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
<div class="form-group">
<fieldset>
		<label class="col-lg-2 control-label">{{Dépendances : }}</label>
		<?php
		$filename = realpath(dirname(__FILE__) . '/../3rdparty/Harmonyhubcontrol/HarmonyHubControl');
		if (!file_exists($filename)) {
			echo '<div class="col-lg-2"><span class="label label-danger">NOK</span></div>';
		} else {
			echo '<div class="col-lg-2"><span class="label label-success">OK</span></div>';
		}
		?>
		<label class="col-lg-2 control-label">{{Droits Sudo : }}</label>
		<?php
		if (exec('sudo cat /etc/sudoers') != "") {
			echo '<div class="col-lg-2"><span class="label label-success">OK</span></div>';
		} else {
			echo '<div class="col-lg-2"><span class="label label-danger">NOK</span>    <span><a href="http://doc.jeedom.fr/fr_FR/doc-installation.html#troubleshoting"><i class="fa fa-question-circle"></i></a></span></div>';
		}
		?>
</fieldset>
</div>
<label></label>
<fieldset>
<div class="form-group">
    <label class="col-lg-2 control-label">{{Email du compte : }}</label>
    <div class="col-lg-2">
		<input id="harmonyhub_api" class="configKey form-control" data-l1key="username" style="margin-top:-5px" placeholder="Email"/>
    </div>
	<label class="col-lg-2 control-label">{{Mot de passe : }}</label>
    <div class="col-lg-2">
		<input id="harmonyhub_api" type="password" class="configKey form-control" data-l1key="password" style="margin-top:-5px" placeholder="Mot de passe"/>
    </div>
</div>
    
<div class="form-group">
    <label class="col-lg-2 control-label">{{Adresse IP du Hub : }}</label>
    <div class="col-lg-2">
		<input id="harmonyhub_api" class="configKey form-control" data-l1key="ip" style="margin-top:-5px" placeholder="Adresse ip"/>
    </div>
</div>
<div class="form-group">
<label class="col-lg-2 control-label">{{Installer les dépendances :}}</label>
			<div class="col-lg-2">
				<a class="btn btn-danger" id="bt_installDeps"><i class="fa fa-check"></i> {{Lancer}}</a>
			</div>
<label class="col-lg-2 control-label">{{Créer/MAJ la config :}}</label>
			<div class="col-lg-2">
				<a class="btn btn-warning" id="bt_update"><i class="fa fa-check"></i> {{Lancer}}</a>
			</div>
</div>
</fieldset> 
</form>
<script>
$('#bt_installDeps').on('click',function(){
		bootbox.confirm('{{Etes-vous sûr de vouloir installer les dépendances }}', function (result) {
			if (result) {
				$('#md_modal').dialog({title: "{{Installation}}"});
				$('#md_modal').load('index.php?v=d&plugin=harmonyhub&modal=update.harmonyhub').dialog('open');
			}
		});
	});
$('#bt_update').on('click',function(){
		bootbox.confirm('{{Etes-vous sûr de vouloir installer/mettre à jour votre fichier de config ? }}', function (result) {
			if (result) {
				$('#md_modal').dialog({title: "{{Installation / Mise à jour ! Cela peut prendre plus d'une minute. Veuillez patienter jusqu\'à l\'apparition du message de fin}}"});
				$('#md_modal').load('index.php?v=d&plugin=harmonyhub&modal=config.harmonyhub').dialog('open');
			}
		});
	});
</script>