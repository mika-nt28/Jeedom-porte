<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class porte extends eqLogic {
	public static function timeout($_option) {	
		$Ouvrant = eqlogic::byId($_option['id']); 
		if (is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			log::add('porte','debug',$Ouvrant->getHumanName().'[Timeout] Démarrage du démon');
			while(true){
				sleep(1);
				$Sense = $Ouvrant->getCmd(null,'state')->execCmd(); //Ouvert a False et Fermer a True
				if(cache::byKey('porte::TpsAutoClose::'.$Ouvrant->getId())->getValue(false) !== false && !$Sense){
					$TpsAutoClose = $Ouvrant->getTime('TpsAutoClose');
					if($TpsAutoClose > 0){
						$Timeout = microtime(true) - cache::byKey('porte::TpsAutoClose::'.$Ouvrant->getId())->getValue(microtime(true));
						$Timeout=round($Timeout*1000000);
						if($Timeout < $TpsAutoClose)
							continue;
						log::add('porte','info',$Ouvrant->getHumanName().'[Close]: Fermeture automatique de la porte');
						if($Ouvrant->getconfiguration('AutoClose')){
							$Ouvrant->getCmd(null,'close')->excCmd();
						}else{
							cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
							$Ouvrant->checkAndUpdateCmd('state',true);
							cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
						}
					}
					cache::set('porte::TpsAutoClose::'.$Ouvrant->getId(),false, 0);
					continue;
				}
				if(!cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false))
					continue;
				$Temps = cache::byKey('porte::TimeMove::'.$Ouvrant->getId())->getValue(false);
				if ($Temps === false){
					if(!$Sense)
						$Temps = $Ouvrant->getTime('TpsOpen');
					else
						$Temps = $Ouvrant->getTime('TpsClose');
				}
				$Timeout = microtime(true) - cache::byKey('porte::ChangeStateStart::'.$Ouvrant->getId())->getValue(microtime(true));
				$Timeout=round($Timeout*1000000);
				if($Timeout < $Temps)
					continue;
				log::add('porte','debug',$Ouvrant->getHumanName().'[Timeout] Le temps de mouvement est dépassé. Mise a jour des états');
				cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
				cache::set('porte::TimeMove::'.$Ouvrant->getId(),false, 0);
				cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
				$Ouvrant->UpdateOuverture();
				if($Ouvrant->getCmd(null,'ouverture')->execCmd() == 100 && $Ouvrant->getconfiguration('TpsAutoClose',0) > 0){
					cache::set('porte::TpsAutoClose::'.$Ouvrant->getId(),microtime(true), 0);
					log::add('porte','debug',$Ouvrant->getHumanName().'[Timeout] En attente de la fermeture automatique');
				}
			}
		}
	}
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'porte';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('porte') as $Ouvrant){
			if($Ouvrant->getIsEnable()){
				if($Ouvrant->getConfiguration('OpenStateCmd') != '' ){				
					$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('CloseStateCmd') != ''){				
					$listener = listener::byClassAndFunction('porte', 'CloseDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('StopStateCmd') != ''){				
					$listener = listener::byClassAndFunction('porte', 'StopDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('EndOpenCmd') != '' || $Ouvrant->getConfiguration('EndCloseCmd') != ''){
					$listener = listener::byClassAndFunction('porte', 'EndDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				$cron = cron::byClassAndFunction('porte', 'timeout', array('id' => $Ouvrant->getId()));
				if(!is_object($cron) || !$cron->running()) 	
					return $return;
			}
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('porte');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		foreach(eqLogic::byType('porte') as $Ouvrant){
			cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
			$Ouvrant->StartListener();
			$Ouvrant->CreateDemon();   
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('porte') as $Ouvrant){
			$Ouvrant->StopListener();
			$cron = cron::byClassAndFunction('porte', 'timeout', array('id' => $Ouvrant->getId()));
			if (is_object($cron)) 	
				$cron->remove();
		}
	}
	public static function OpenDoors($_option) {
		log::add('porte','debug','Detection sur le listener Open : '.json_encode($_option));
		$Ouvrant = eqLogic::byId($_option['id']);
		$detectedCmd = cmd::byId($_option['event_id']);
		if (is_object($detectedCmd) && is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			$isOpen=$Ouvrant->getConfiguration('OpenStateCmd').$Ouvrant->getConfiguration('OpenStateOperande').$Ouvrant->getConfiguration('OpenStateValue');
			if($Ouvrant->EvaluateCondition($isOpen)){
				switch($Ouvrant->getConfiguration('typeControleur')){
					case 'multi':
						log::add('porte','info',$Ouvrant->getHumanName().'[Open]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						$Ouvrant->checkAndUpdateCmd('state',false);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
					break;
					case 'OpenStopClose':
						if(cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false)){
							log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
							cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
							cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
							$Ouvrant->UpdateOuverture();
						}else{
							$Sense = $Ouvrant->getCmd(null,'state')->execCmd();
							if(!$Sense){
								log::add('porte','info',$Ouvrant->getHumanName().'[Close]: Action détectée sur '.$detectedCmd->getHumanName());
								cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
								$Ouvrant->checkAndUpdateCmd('state',true);
								cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
							}else{
								log::add('porte','info',$Ouvrant->getHumanName().'[Open]: Action détectée sur '.$detectedCmd->getHumanName());
								cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
								$Ouvrant->checkAndUpdateCmd('state',false);
								cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
							}
						}
					break;
				}
			}
		}
	}
	public static function CloseDoors($_option) {
		log::add('porte','debug','Detection sur le listener Close : '.json_encode($_option));
		$Ouvrant = eqLogic::byId($_option['id']);
		$detectedCmd = cmd::byId($_option['event_id']);
		if (is_object($detectedCmd) && is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			$isClose=$Ouvrant->getConfiguration('CloseStateCmd').$Ouvrant->getConfiguration('CloseStateOperande').$Ouvrant->getConfiguration('CloseStateValue');
			if($Ouvrant->EvaluateCondition($isClose)){
				switch($Ouvrant->getConfiguration('typeControleur')){
					case 'multi':
						log::add('porte','info',$Ouvrant->getHumanName().'[Close]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						$Ouvrant->checkAndUpdateCmd('state',true);
						cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					break;
				}
			}
		}
	}
	public static function StopDoors($_option) {
		log::add('porte','debug','Detection sur le listener Stop : '.json_encode($_option));
		$Ouvrant = eqLogic::byId($_option['id']);
		$detectedCmd = cmd::byId($_option['event_id']);
		if (is_object($detectedCmd) && is_object($Ouvrant) && $Ouvrant->getIsEnable()) {			
			$isStop=$Ouvrant->getConfiguration('StopStateCmd').$Ouvrant->getConfiguration('StopStateOperande').$Ouvrant->getConfiguration('StopStateValue');
			if($Ouvrant->EvaluateCondition($isStop)){
				log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
				if(cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false)){
					log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
					cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
					cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					$Ouvrant->UpdateOuverture();
				}
			}
		}
	}
	public static function EndDoors($_option) {
		log::add('porte','debug','Detection sur le listener End : '.json_encode($_option));
		$Ouvrant = eqLogic::byId($_option['id']);
		$detectedCmd = cmd::byId($_option['event_id']);
		if (is_object($detectedCmd) && is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			$isEndOpen=$Ouvrant->getConfiguration('EndOpenCmd').$Ouvrant->getConfiguration('EndOpenOperande').$Ouvrant->getConfiguration('EndOpenValue');
			if($Ouvrant->EvaluateCondition($isEndOpen)){
				log::add('porte','info',$Ouvrant->getHumanName().'[Fin de cours]: Fin de course haute détectée');
				cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
				$Ouvrant->checkAndUpdateCmd('state',false);
				$Ouvrant->checkAndUpdateCmd('ouverture',100);
			}
			$isEndClose=$Ouvrant->getConfiguration('EndCloseCmd').$Ouvrant->getConfiguration('EndCloseOperande').$Ouvrant->getConfiguration('EndCloseValue');
			if($Ouvrant->EvaluateCondition($isEndClose)){
				log::add('porte','info',$Ouvrant->getHumanName().'[Fin de cours]: Fin de course basse détectée');
				cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
				$Ouvrant->checkAndUpdateCmd('state',true);
				$Ouvrant->checkAndUpdateCmd('ouverture',0);
			}
		}
	}
	private function CreateDemon() {
		$cron =cron::byClassAndFunction('porte', 'timeout', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('porte');
			$cron->setFunction('timeout');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout('1');
			$cron->save();
		}
		$cron->save();
		$cron->start();
		$cron->run();
		return $cron;
	}
	public function boolToText($value){
		if (is_bool($value)) {
			if ($value) 
				return __('Vrai', __FILE__);
			else 
				return __('Faux', __FILE__);
		} else 
			return $value;
	}
	public function EvaluateCondition($Condition){
		$_scenario = null;
		$expression = scenarioExpression::setTags($Condition, $_scenario, true);
		$message = __('Evaluation de la condition : ['.jeedom::toHumanReadable($Condition).'][', __FILE__) . trim($expression) . '] = ';
		$result = evaluate($expression);
		$message .=$this->boolToText($result);
		log::add('porte','info',$this->getHumanName().$message);
		if(!$result)
			return false;		
		return true;
	}
	public function StopListener() {
		$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'CloseDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'StopDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'EndDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cache = cache::byKey('porte::Move::'.$this->getId());
		if (is_object($cache))
			$cache->remove();
		$cache = cache::byKey('porte::TpsAutoClose::'.$this->getId());
		if (is_object($cache))
			$cache->remove();

	}
	public function StartListener() {
		if($this->getIsEnable()){
			$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $this->getId()));
			$OpenStateCmd=$this->getConfiguration('OpenStateCmd');
			if ($OpenStateCmd != ''){
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('porte');
				$listener->setFunction('OpenDoors');
				$listener->setOption(array('id' => $this->getId()));
				$listener->emptyEvent();	
				$listener->addEvent($OpenStateCmd);
				$listener->save();			
			}
			$listener = listener::byClassAndFunction('porte', 'CloseDoors', array('id' => $this->getId()));
			$CloseStateCmd=$this->getConfiguration('CloseStateCmd');
			if ($CloseStateCmd != ''){
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('porte');
				$listener->setFunction('CloseDoors');
				$listener->setOption(array('id' => $this->getId()));
				$listener->emptyEvent();	
					$listener->addEvent($CloseStateCmd);
				$listener->save();			
			}
			$listener = listener::byClassAndFunction('porte', 'StopDoors', array('id' => $this->getId()));
			$StopStateCmd=$this->getConfiguration('StopStateCmd');
			if ($StopStateCmd != ''){
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('porte');
				$listener->setFunction('StopDoors');
				$listener->setOption(array('id' => $this->getId()));
				$listener->emptyEvent();	
				$listener->addEvent($StopStateCmd);
				$listener->save();				
			}
			$listener = listener::byClassAndFunction('porte', 'EndDoors', array('id' => $this->getId()));
			if ($this->getConfiguration('EndOpenpCmd') != '' || $this->getConfiguration('EndCloseCmd') != ''){
				if (!is_object($listener))
				    $listener = new listener();
				$listener->setClass('porte');
				$listener->setFunction('EndDoors');
				$listener->setOption(array('id' => $this->getId()));
				$listener->emptyEvent();	
				if ($this->getConfiguration('EndUpCmd') != '')
					$listener->addEvent($this->getConfiguration('EndOpenCmd'));
				if ($this->getConfiguration('EndDownCmd') != '')
					$listener->addEvent($this->getConfiguration('EndCloseCmd'));
				$listener->save();
			}
		}
	}
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary',$visible,$Value=null,$icon=null,$generic_type=null) {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande)){
			$Commande = new porteCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible($visible);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($this->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);	
			if($Value != null)
				$Commande->setValue($Value);
			if($icon != null)
				$Commande->setDisplay('icon', $icon);
			if($generic_type != null)
				$Commande->setDisplay('generic_type', $generic_type);
			$Commande->save();
		}
		return $Commande;
	}
	public function postSave() {
		$this->StopListener();
		$etat=$this->AddCommande("Etat","state","info",'binary',0,null,"core::garage",'GARAGE_STATE');
		$hauteur=$this->AddCommande("Proportionnelle","ouverture","info",'numeric',1);
		$this->AddCommande("Position","position","action",'slider',1,$hauteur->getId(),null,'FLAP_SLIDER');
		$this->AddCommande("Ouvrir","open","action", 'other',1,$etat->getId(),"core::garage",'GB_OPEN');
		$this->AddCommande("Fermer","close","action", 'other',1,$etat->getId(),"core::garage",'GB_CLOSE');
		$this->AddCommande("Arret","stop","action", 'other',1,null,null,'<i class="fa fa-stop"></i>');
		$this->StartListener();
		$this->CreateDemon();   
	}	
	public function preRemove() {
		$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'CloseDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'StopDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$listener = listener::byClassAndFunction('porte', 'EndDoors', array('id' => $this->getId()));
		if (is_object($listener))
			$listener->remove();
		$cron = cron::byClassAndFunction('porte', 'timeout', array('id' => $this->getId()));
		if (is_object($cron)) 	
			$cron->remove();
	}
	public function UpdateOuverture() {
		$Sense = $this->getCmd(null,'state')->execCmd();
		$ChangeStateStart = cache::byKey('porte::ChangeStateStart::'.$this->getId())->getValue(microtime(true));
		$ChangeStateStop = cache::byKey('porte::ChangeStateStop::'.$this->getId())->getValue(microtime(true));	
		$TempsAction=$ChangeStateStop-$ChangeStateStart;	
		$TempsAction=round($TempsAction*1000000);
		$OuvertureActuel=$this->getCmd(null,'ouverture')->execCmd();
		log::add('porte','debug',$this->getHumanName().' Temps de mouvement de '.$TempsAction.'µs');
		if(!$Sense)
			$Temps = $this->getTime('TpsOpen');
		else
			$Temps = $this->getTime('TpsClose');
		$Ouverture=round($TempsAction*100/$Temps);
		log::add('porte','debug',$this->getHumanName().' Mouvement de '.$Ouverture.'%');
		if(!$Sense)
			$Ouverture=round($OuvertureActuel+$Ouverture);
		else
			$Ouverture=round($OuvertureActuel-$Ouverture);
		if($Ouverture<0)
			$Ouverture=0;
		if($Ouverture>100)
			$Ouverture=100;
		log::add('porte','debug',$this->getHumanName().' L\'ouvrant est à '.$Ouverture.'%');
		$this->checkAndUpdateCmd('ouverture',$Ouverture);
	}
	public function getTime($Type) {
		return intval($this->getConfiguration($Type,0))*intval($this->getConfiguration($Type.'Base',1000000));
	}
	public function execPropPorte($Ouverture) {
		$OuverturePorte = $this->getCmd(null,'ouverture')->execCmd();
		if($OuverturePorte < $Ouverture){
			$Delta=$Ouverture-$OuverturePorte;
			$this->getCmd(null,'open')->execCmd(null);
			log::add('porte','info',$this->getHumanName().' L\'ouvrant est à '.$OuverturePorte.'% et nous allons l\'ouvrir  de '.$Delta.'%');
		}else{
			$Delta=$OuverturePorte-$Ouverture;
			$this->getCmd(null,'close')->execCmd(null);
			log::add('porte','info',$this->getHumanName().' L\'ouvrant est à '.$OuverturePorte.'% et nous allons le fermer de '.$Delta.'%');
		}
		cache::set('porte::TimeMove::'.$this->getId(),$this->TpsAction($Delta), 0);
	}
	public function TpsAction($Ouverture) {
		$Sense = $this->getCmd(null,'state')->execCmd();
		if(!$Sense)
			$Temps = $this->getTime('TpsOpen');
		else
			$Temps = $this->getTime('TpsClose');
		$TempsAction=round($Ouverture*$Temps/100);
		log::add('porte','debug',$this->getHumanName().' Temps d\'action '.$TempsAction.'µs');
		return $TempsAction;
	}
}
class porteCmd extends cmd {
    public function execute($_options = null) {
		$Ouvrant = $this->getEqLogic();
		$Sense = $Ouvrant->getCmd(null,'state')->execCmd();
		switch($this->getLogicalId()){
			case "open":
				if(!cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false) && $Sense){
					$cmd=cmd::byId(str_replace('#','',$Ouvrant->getConfiguration('cmdOpen')));
					if(is_object($cmd)){
						log::add('porte','debug',$Ouvrant->getHumanName().'[Open] Exécution de la commande '.$cmd->getHumanName());
						$cmd->execCmd(null);
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						$Ouvrant->checkAndUpdateCmd('state',false);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
					}
				}
			break;
			case "close":
				if(!cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false) && !$Sense){
					switch($Ouvrant->getConfiguration('typeControleur')){
						case 'multi':
							$cmd=cmd::byId(str_replace('#','',$Ouvrant->getConfiguration('cmdClose')));
							if(is_object($cmd)){
								log::add('porte','debug',$Ouvrant->getHumanName().'[Close] Exécution de la commande '.$cmd->getHumanName());
								$cmd->execCmd(null);
							}
						break;
						case 'OpenStopClose':
							$cmd=cmd::byId(str_replace('#','',$Ouvrant->getConfiguration('cmdOpen')));
							if(is_object($cmd)){
								log::add('porte','debug',$Ouvrant->getHumanName().'[Close] Exécution de la commande '.$cmd->getHumanName());
								$cmd->execCmd(null);
							}
						break;
					}
					cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
					$Ouvrant->checkAndUpdateCmd('state',true);
					cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
				}
			break;
			case "stop":
				if(cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false)){
					switch($Ouvrant->getConfiguration('typeControleur')){
						case 'multi':
							$cmd=cmd::byId(str_replace('#','',$Ouvrant->getConfiguration('cmdStop')));
							if(is_object($cmd)){
								log::add('porte','debug',$Ouvrant->getHumanName().'[Stop] Exécution de la commande '.$cmd->getHumanName());
								$cmd->execCmd(null);
							}
						break;
						case 'OpenStopClose':
							$cmd=cmd::byId(str_replace('#','',$Ouvrant->getConfiguration('cmdOpen')));
							if(is_object($cmd)){
								log::add('porte','debug',$Ouvrant->getHumanName().'[Close] Exécution de la commande '.$cmd->getHumanName());
								$cmd->execCmd(null);
							}
						break;
					}
					cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
					$Ouvrant->UpdateOuverture();
				}
			break;
			case "position":
				if(!cache::byKey('porte::Move::'.$Ouvrant->getId())->getValue(false)){
					$Ouvrant->execPropPorte($_options['slider']);
				}
			break;
		}
	}
}
?>
