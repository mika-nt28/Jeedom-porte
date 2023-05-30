<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
?>
<div>
			<legend>{{Configuration des Temps}} 
				<a class="btn btn-success btn-xs pull-right cursor CalAction" data-action="trigger">
					<i class="fa fa-check"></i> {{Trigger}}
				</a>
			</legend>
			<label class="col-sm-4 control-label openDate">{{Ouverure}}</label>
			<label class="col-sm-4 control-label tpsOpen">{{Temps d'ouverure}}</label>
			<label class="col-sm-4 control-label stopDate">{{Stop}}</label>
			<label class="col-sm-4 control-label tpsStop">{{Temps d'arret}}</label>
			<label class="col-sm-4 control-label">{{Fermeture}}</label>
			<label class="col-sm-4 control-label tpsClose">{{Temps de fermeture}}</label>
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
    $('.tpsOpen').text((stop - start) / 1000);
  } else if(close == null){
     close = new Date;
  $('.stopDate').text(close);
    $('.tpsOpen').text((close - stop) / 1000);
  }
});
     </script>
</div>
