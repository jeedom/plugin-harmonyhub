<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'harmonyhub');
$eqLogics = eqLogic::byType('harmonyhub');
?>

<div class="row row-overflow">
 <div class="col-lg-12 eqLogicThumbnailDisplay">
   <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
   <div class="eqLogicThumbnailContainer">
   <div class="cursor eqLogicAction logoPrimary" data-action="add" >
      <i class="fa fa-plus-circle"></i>
	<br/>
    <span><center>{{Ajouter}}</center></span>
  </div>
  <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
      <i class="fa fa-wrench"></i>
	<br/>
    <span><center>{{Configuration}}</center></span>
  </div>
  <div class="cursor logoSecondary" id="bt_healthharmony">
    <i class="fa fa-medkit"></i>
	<br/>
  <span><center>{{Santé}}</center></span>
</div>
</div>
  <legend><i class="icon techno-home115"></i>  {{Mes Dispositifs}}
  </legend>
  <?php
if (count($eqLogics) == 0) {
	echo "<br/><br/><br/><center><span style='color:#767676;font-size:1.2em;font-weight: bold;'>{{Vous n'avez pas encore de télécommande Harmony, aller sur Général -> Plugin et cliquez sur synchroniser pour commencer}}</span></center>";
} else {
	?>
   <div class="eqLogicThumbnailContainer">
    <?php
				foreach ($eqLogics as $eqLogic) {
					$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                    echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
                    $file = $eqLogic->getConfiguration('icone');
                    if (file_exists($file)) {
                        $path = $eqLogic->getConfiguration('icone');
                    } else {
                        $path = 'plugins/harmonyhub/core/template/images/harmonyhub_icon.png';
                    }
                    echo '<img src="'.$path.'"/>';
                   echo '<span>' . $eqLogic->getHumanName(true, true) . '</span>';
                    echo '</div>';
                }
                ?>
 </div>
 <?php }
?>
</div> 

    <div class="col-lg-12 eqLogic" style="display: none;">
         <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
    <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
  <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>

    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
        <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
        <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
    </ul>

    <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
        <div role="tabpanel" class="tab-pane active" id="eqlogictab">
		<div class="row">
            <div class="col-sm-6">
		<form class="form-horizontal">
            <fieldset>
                <div class="form-group">
                        <label class="col-lg-3 control-label">{{Nom de l'équipement}}</label>
                        <div class="col-lg-4">
                            <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                            <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                        </div>

                    </div>
                    <div class="form-group">
                        <label class="col-lg-3 control-label" >{{Objet parent}}</label>
                        <div class="col-lg-4">
                            <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                <option value="">{{Aucun}}</option>
                                <?php
foreach (object::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
                           </select>
                       </div>
                   </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Catégorie}}</label>
                    <div class="col-lg-9">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">';
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>';
                        }
                        ?>

                    </div>
                </div>
               <div class="form-group">
                    <label class="col-sm-2 control-label"></label>
                    <div class="col-sm-10">
                        <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                        <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
                    </div>
                </div>
                <legend><i class="fa fa-wrench"></i>  {{Configuration}}</legend>
                <div class="form-group">
					<label class="col-lg-2 control-label">{{Hub}}</label>
                    <div class="col-lg-4">
                        <select id="sel_itemhub" class="eqLogicAttr form-control hubIp" data-l1key="configuration" data-l2key="hubIp">
                            <?php
								foreach (explode('|',config::byKey('ip', 'harmonyhub', 0)) as $hub) {
									echo '<option value="' . $hub . '">' . $hub . '</option>';
                                }
                            ?> 
                        </select>
                    </div>
                    <label class="col-lg-2 control-label">{{Dispositif}}</label>
                    <div class="col-lg-4">
                        <select id="sel_item" class="eqLogicAttr form-control dispositifid" data-l1key="configuration" data-l2key="dispositifid">
                            
                        </select>
                    </div>
                </div>
				<div class="cron">
				<div class="form-group">
				<label class="col-lg-4 control-label" >{{Activer la récupération des activités toutes les minutes}}</label>
                    <div class="col-lg-1">
						<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="cronenabled" checked/>
                    </div>
				</div>
				</div>
			<legend><i class="fa fa-info"></i>  {{Informations}}</legend>
                
			<div class="form-group">
                    		<label class="col-lg-3 control-label">{{Nom dispositif}}</label>
                    		<div class="col-lg-3">
                        	<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="disponame" readonly/>
                    		</div>
							<label class="col-lg-3 control-label">{{Id dispositif}}</label>
                    		<div class="col-lg-3">
                        	<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dispoid" readonly/>
                    		</div>
            </div>
			</fieldset>
		</form>
		</div>
		 <div class="col-sm-6">
		<legend><i class="fa fa-camera"></i>   {{Visuel du dispositif}}</legend>
                	<div class="form-group">
                    		<label class="col-md-4 control-label">{{Icône du dispositif}}</label>
                    		<div class="col-md-4">
                        	<select id="sel_item2" class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="icone" onchange="document.icon_visu.src=this.value;">
								<option value="">{{}}</option>
								<option value="plugins/harmonyhub/core/template/images/tv.png">{{Télévision}}</option>
								<option value="plugins/harmonyhub/core/template/images/clim.png">{{Climatisation}}</option>
								<option value="plugins/harmonyhub/core/template/images/ventilateur.png">{{Ventilateur}}</option>
								<option value="plugins/harmonyhub/core/template/images/amplis.png">{{Ampli A/V}}</option>
								<option value="plugins/harmonyhub/core/template/images/lampe.png">{{Lampe}}</option>
								<option value="plugins/harmonyhub/core/template/images/htpc.png">{{Htpc}}</option>
								<option value="plugins/harmonyhub/core/template/images/lampepied.png">{{Lampe sur pied}}</option>
								<option value="plugins/harmonyhub/core/template/images/console.png">{{Console de jeux}}</option>
								<option value="plugins/harmonyhub/core/template/images/boxtv.png">{{Box TV}}</option>
								<option value="plugins/harmonyhub/core/template/images/bandeauled.png">{{Bandeau Leds}}</option>
								<option value="plugins/harmonyhub/core/template/images/action.png">{{Activité}}</option>
								<option value="plugins/harmonyhub/core/template/images/photophore.png">{{Photophore}}</option>
							</select>
                    		</div>
							
                	</div>
				</div>
					<div style="text-align: center">
							<img name="icon_visu" src=""/>
							</div>
		</div>
		</div>
		<div role="tabpanel" class="tab-pane" id="commandtab">
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 300px;">{{Nom}}</th><th>{{Commande}}</th><th>{{Options}}</th><th>{{Action}}</th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>
</div>
</div>
</div>
</div>

<?php include_file('desktop', 'harmonyhub', 'js', 'harmonyhub'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
<script>
$(document).ready(function() {
    var opt = $("#sel_item option").sort(function (a,b) { return a.text.toUpperCase().localeCompare(b.text.toUpperCase()) });
    $("#sel_item").append(opt);
});
$(document).ready(function() {
    var opt = $("#sel_item2 option").sort(function (a,b) { return a.text.toUpperCase().localeCompare(b.text.toUpperCase()) });
    $("#sel_item2").append(opt);
});
$("#sel_item").change(function(){
     var select=  $(this).val();
       if(select=='activity'){
           $('.cron').show();
         } else {
           $('.cron').hide();
         }
    }); 
</script>
