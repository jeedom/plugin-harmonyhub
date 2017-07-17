
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
  $('#bt_healthharmony').on('click', function () {
    $('#md_modal').dialog({title: "{{Santé Harmony}}"});
    $('#md_modal').load('index.php?v=d&plugin=harmonyhub&modal=health').dialog('open');
});
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    tr += '<div class="row">';
	tr += '<div class="col-sm-6">';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
	tr += '</div>';
	tr += '<div class="col-sm-6">';
	tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icone</a>';
	tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
	tr += '</div>';
	tr += '</div>';
	tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="configuration" data-l2key="parameters"></span>';
    tr += '</td>'; 
	tr += '<td>';
	if (_cmd.logicalId != 'refresh'){
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    }
	tr += '</td>';
	tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}

 $('.eqLogicAttr[data-l1key=configuration][data-l2key=hubIp]').on('change', function () {
	getdevicelist($(this).value());
});

function getdevicelist(_ip) {
    $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/harmonyhub/core/ajax/harmonyhub.ajax.php", // url du fichier php
        data: {
            action: "getdevicelist",
            ip: _ip,
			id: $('.li_eqLogic.active').attr('data-eqlogic_id'),
        },
        dataType: 'json',
        global: false,
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) { // si l'appel a bien fonctionné
        if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'});
            return;
        }
        var options = '';
		if (data.result[0] != null) {
			for (var i in data.result[0].device) {
				if (data.result[0].device[i].id ==  data.result[1]){
					options += '<option value="'+data.result[0].device[i].id+'" selected>'+data.result[0].device[i].label+'</option>';
				} else {
					options += '<option value="'+data.result[0].device[i].id+'">'+data.result[0].device[i].label+'</option>';
				}
			}
			if (data.result[1] == 'activity'){
				options += '<option value="activity" selected>Activité</option>';
			} else {
				options += '<option value="activity">Activité</option>';
			}
			$(".dispositifid").html(options);
		}
    }
});
}
