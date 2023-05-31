<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<div>
	<form class="form-horizontal">
	<legend>{{Configuration des Temps}} 
		<a class="btn btn-success btn-xs pull-right cursor CalAction" data-action="trigger">
			<i class="fa fa-check"></i> {{Trigger}}
		</a>
	</legend>
			<fieldset>
	<div class="form-group">
		<label class="col-sm-3 control-label" >{{Ouverure}}
			<sup>
				<i class="fa fa-question-circle tooltips" title="{{Date de démarrage de l'ouverture et temps d'ouverture}}"></i>
			</sup>
		</label>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning openDate">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning stopDate">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-success tpsCalOpen">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label" >{{Fermeture automatique}}
			<sup>
				<i class="fa fa-question-circle tooltips" title="{{Date de démarrage de l'ouverture et temps d'ouverture}}"></i>
			</sup>
		</label>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning stopDate">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning closeDate">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-success tpsCalAutoClose">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-3 control-label" >{{Fermeture}}
			<sup>
				<i class="fa fa-question-circle tooltips" title="{{Date de démarrage de la fermeture et temps d'ouverture}}"></i>
			</sup>
		</label>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning closeDate">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-warning autoClose">
		</div>
		<div class="col-sm-3">
			<span  class="badge btn btn-success tpsCalClose">
		</div>
	</div>
</fieldset>
</from>
	<script>  
		var open = null;
		var stop = null;
		var close = null;
		$('body').off('.CalAction[data-action=trigger]').on('click','.CalAction[data-action=trigger]', function() {
			if(open == null){
				open = new Date;
				$('.openDate').text(open);
			}else if(stop == null){
				stop = new Date;
				$('.stopDate').text(stop);
				$('.tpsCalOpen').text((stop - open) / 1000);
			}else if(close == null){
				close = new Date;
				$('.closeDate').text(close);
				$('.tpsCalAutoClose').text((close - stop) / 1000);
			}else{
				$('.autoClose').text(new Date);
				$('.tpsCalClose').text((new Date - close) / 1000);
			}
		});
	</script>
</div>
