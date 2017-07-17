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
<label></label>
<fieldset>    
<div class="form-group">
    <label class="col-lg-4 control-label">{{Adresse IP du Hub (plusieurs Ips possibles séparées par |) : }}</label>
    <div class="col-lg-2">
		<input id="harmonyhub_api" class="configKey form-control" data-l1key="ip" style="margin-top:-5px" placeholder="Adresse ip"/>
    </div>
</div>
<div class="form-group">
<label class="col-lg-4 control-label">{{Créer/MAJ les configs :}}</label>
			<div class="col-lg-2">
				<a class="btn btn-warning" id="bt_update"><i class="fa fa-check"></i> {{Lancer}}</a>
			</div>
</div>
</fieldset> 
</form>
<script>
$('#bt_update').on('click',function(){
		bootbox.confirm('{{Etes-vous sûr de vouloir installer/mettre à jour vos fichiers de config ? }}', function (result) {
			if (result) {
				$('#md_modal').dialog({title: "{{Installation / Mise à jour ! Cela peut prendre plus d'une minute. Veuillez patienter jusqu\'à l\'apparition du message de fin}}"});
				$('#md_modal').load('index.php?v=d&plugin=harmonyhub&modal=config.harmonyhub').dialog('open');
			}
		});
	});
</script>