
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
  $('#md_modal').dialog({ title: "{{Santé Harmony}}" });
  $('#md_modal').load('index.php?v=d&plugin=harmonyhub&modal=health').dialog('open');
});

$("#sel_dispositifid").change(function () {
  if ($(this).val() == 'activity') {
    $('.cron').show();
  } else {
    $('.cron').hide();
  }
});

$("#sel_icone").change(function () {
  $("#icon_visu").attr("src", $(this).val());
});

function getdevicelist(_ip) {
  $.ajax({// fonction permettant de faire de l'ajax
    type: "POST", // methode de transmission des données au fichier php
    url: "plugins/harmonyhub/core/ajax/harmonyhub.ajax.php", // url du fichier php
    data: {
      action: "getdevicelist",
      ip: _ip,
      id: $('.eqLogicAttr[data-l1key=id]').value(),
    },
    dataType: 'json',
    global: false,
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) { // si l'appel a bien fonctionné
      if (data.state != 'ok') {
        $('#div_alert').showAlert({ message: data.result, level: 'danger' });
        return;
      }
      var options = '';
      if (data.result[0] != null) {
        for (var i in data.result[0].device) {
          if (data.result[0].device[i].id == data.result[1]) {
            options += '<option value="' + data.result[0].device[i].id + '" selected>' + data.result[0].device[i].label + '</option>';
          } else {
            options += '<option value="' + data.result[0].device[i].id + '">' + data.result[0].device[i].label + '</option>';
          }
        }
        if (data.result[1] == 'activity') {
          options += '<option value="activity" selected>Activité</option>';
        } else {
          options += '<option value="activity">Activité</option>';
        }
        $("#sel_dispositifid").html(options);

        const opt = $("#sel_dispositifid option").detach().sort(function (a, b) {
          return a.text.toUpperCase().localeCompare(b.text.toUpperCase())
        });
        console.log(opt);
        $("#sel_dispositifid").append(opt);
      }
    }
  });
}

function printEqLogic(_eqLogic) {
  const opt = $("#sel_icone option").detach().sort(function (a, b) {
    return a.text.toUpperCase().localeCompare(b.text.toUpperCase())
  });
  $("#sel_icone").append(opt).val(_eqLogic.configuration.icone);

  getdevicelist(_eqLogic.configuration.hubIp);

  if (_eqLogic.configuration.dispositifid == 'activity') {
    $('.cron').show();
  } else {
    $('.cron').hide();
  }
}

$("#table_cmd").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
  tr += '<div class="input-group">'
  tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '<span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>'
  tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>'
  tr += '</div>'
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="configuration" data-l2key="parameters"></span>';
  tr += '</td>';
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label> '
  tr += '</td>';
  tr += '<td>';
  tr += '<span class="cmdAttr" data-l1key="htmlstate"></span>';
  tr += '</td>';
  tr += '<td>';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="type" style="display : none;">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="subType" style="display : none;">';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
  tr += '</tr>';
  $('#table_cmd tbody').append(tr);
  $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
  jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}