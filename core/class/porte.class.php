<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class porte extends eqLogic {
	public static function timeout($_option) {	
		$Ouvrant = eqlogic::byId($_option['id']); 
		if (is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			while(true){
				$Move = cache::byKey('porte::Move::'.$Ouvrant->getId());
				//if(!is_object($Move) || !$Move->getValue(false)){
					
					
				sleep(1);		
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
				if($Ouvrant->getConfiguration('UpStateCmd') != '' ){				
					$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('DownStateCmd') != ''){				
					$listener = listener::byClassAndFunction('porte', 'CloseDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('StopStateCmd') != ''){				
					$listener = listener::byClassAndFunction('porte', 'StopDoors', array('id' => $Ouvrant->getId()));
					if (!is_object($listener))
						return $return;
				}
				if($Ouvrant->getConfiguration('EndUpCmd') != '' || $Ouvrant->getConfiguration('EndDownCmd') != ''){
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
				if(cache::byKey('porte::Move::'.$Ouvrant->getEqLogic()->getId())->getValue(false)){
					log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
					cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
					cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					$Ouvrant->UpdateOuverture();
				}else{
					if(cache::byKey('porte::Sense::'.$Ouvrant->getEqLogic()->getId())->getValue(false)){
						log::add('porte','info',$Ouvrant->getHumanName().'[Close]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						cache::set('porte::Sense::'.$Ouvrant->getId(),false, 0);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);

					}else{
						log::add('porte','info',$Ouvrant->getHumanName().'[Open]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						cache::set('porte::Sense::'.$Ouvrant->getId(),true, 0);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
					}
				}
			}
		}
	}
	public static function CloseDoors($_option) {
		log::add('porte','debug','Detection sur le listener Down : '.json_encode($_option));
		$Ouvrant = eqLogic::byId($_option['id']);
		$detectedCmd = cmd::byId($_option['event_id']);
		if (is_object($detectedCmd) && is_object($Ouvrant) && $Ouvrant->getIsEnable()) {
			$isClose=$Ouvrant->getConfiguration('CloseStateCmd').$Ouvrant->getConfiguration('CloseStateOperande').$Ouvrant->getConfiguration('CloseStateValue');
			if($Ouvrant->EvaluateCondition($isClose)){
				if(cache::byKey('porte::Move::'.$Ouvrant->getEqLogic()->getId())->getValue(false)){
					log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
					cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
					cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					$Ouvrant->UpdateOuverture();
				}else{
					if(cache::byKey('porte::Sense::'.$Ouvrant->getEqLogic()->getId())->getValue(false)){
						log::add('porte','info',$Ouvrant->getHumanName().'[Close]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						cache::set('porte::Sense::'.$Ouvrant->getId(),false, 0);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);

					}else{
						log::add('porte','info',$Ouvrant->getHumanName().'[Open]: Action détectée sur '.$detectedCmd->getHumanName());
						cache::set('porte::ChangeStateStart::'.$Ouvrant->getId(),microtime(true), 0);
						cache::set('porte::Sense::'.$Ouvrant->getId(),true, 0);
						cache::set('porte::Move::'.$Ouvrant->getId(),true, 0);
					}
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
				$Move=cache::byKey('porte::Move::'.$Ouvrant$Ouvrant->getId());
				if(is_object($Move) && $Move->getValue(false)){
					log::add('porte','info',$Ouvrant->getHumanName().'[Stop]: Action détectée sur '.$detectedCmd->getHumanName());
					cache::set('porte::ChangeStateStop::'.$Ouvrant->getId(),microtime(true), 0);
					cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
					$Ouvrant->UpdateOuverture()
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
			}
			$isEndClose=$Ouvrant->getConfiguration('EndCloseCmd').$Ouvrant->getConfiguration('EndCloseOperande').$Ouvrant->getConfiguration('EndCloseValue');
			if($Ouvrant->EvaluateCondition($isEndClose)){
				log::add('porte','info',$Ouvrant->getHumanName().'[Fin de cours]: Fin de course basse détectée');
				cache::set('porte::Move::'.$Ouvrant->getId(),false, 0);
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

	}
	public function StartListener() {
		if($this->getIsEnable()){
			$listener = listener::byClassAndFunction('porte', 'OpenDoors', array('id' => $this->getId()));
			$OpenStateCmd=$this->getConfiguration('OpenStateCmd');
			if ($UpStateCmd != ''){
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
			if ($DownStateCmd != ''){
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
		$hauteur=$this->AddCommande("Ouverture","ouverture","info",'numeric',0,null,null,'FLAP_STATE');
		//$this->AddCommande("Position","position","action",'slider',1,$hauteur->getId(),null,'FLAP_SLIDER');
		$this->AddCommande("Open","open","action", 'other',1,null,'<i class="fa fa-arrow-up"></i>','FLAP_UP');
		$this->AddCommande("Close","close","action", 'other',1,null,'<i class="fa fa-arrow-down"></i>','FLAP_DOWN');
		$this->AddCommande("Stop","stop","action", 'other',1,null,'<i class="fa fa-stop"></i>','FLAP_STOP');
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
		$ChangeState = cache::byKey('porte::ChangeState::'.$this->getId())->getValue(false);
		$ChangeStateStart = cache::byKey('porte::ChangeStateStart::'.$this->getId())->getValue(microtime(true));
		$ChangeStateStop = cache::byKey('porte::ChangeStateStop::'.$this->getId())->getValue(microtime(true));	
		$TempsAction=$ChangeStateStop-$ChangeStateStart;	
		$TempsAction=round($TempsAction*1000000);
		$OuvertureActuel=$this->getCmd(null,'ouverture')->execCmd();
		log::add('porte','debug',$this->getHumanName().' Temps de mouvement d de '.$TempsAction.'µs');
		if($OuvertureActuel == 0){
			$TempsAction -= $this->getTime('Tdecol');
			log::add('porte','debug',$this->getHumanName().' Suppression du temps de décollement');
		}
		if($ChangeState)
			$Temps = $this->getTime('TpsUp') - $this->getTime('Tdecol');
		else
			$Temps = $this->getTime('TpsDown') - $this->getTime('Tdecol');
		$Ouverture=round($TempsAction*100/$Temps);
		log::add('porte','debug',$this->getHumanName().' Mouvement du volet de '.$Ouverture.'%');
		if($ChangeState)
			$Ouverture=round($OuvertureActuel+$Ouverture);
		else
			$Ouverture=round($OuvertureActuel-$Ouverture);
		if($Ouverture<0)
			$Ouverture=0;
		if($Ouverture>100)
			$Ouverture=100;
		log::add('porte','debug',$this->getHumanName().' L\'ourant est à '.$Ouverture.'%');
		$this->checkAndUpdateCmd('ouverture',$Ouverture);
	}
}
class porteCmd extends cmd {
    public function execute($_options = null) {
		switch($this->getLogicalId()){
			case "open":
				if(!cache::byKey('porte::Sense::'.$this->getEqLogic()->getId())->getValue(false)){
					$cmd=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdOpen')));
					if(is_object($cmd)){
						if(cache::byKey('porte::Move::'.$this->getEqLogic()->getId())->getValue(false)){
							log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
							$cmd->execCmd(null);
						}
						log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
						$cmd->execCmd(null);
					}
				}
				cache::set('porte::ChangeStateStart::'.$this->getEqLogic()->getId(),microtime(true), 0);
				cache::set('porte::Sense::'.$this->getEqLogic()->getId(),true, 0);
				cache::set('porte::Move::'.$this->getEqLogic()->getId(),true, 0);
			break;
			case "close":
				$cmd=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdClose')));
				if(is_object($cmd)){
					log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
					$cmd->execCmd(null);
				}else{
					if(cache::byKey('porte::Sense::'.$this->getEqLogic()->getId())->getValue(false)){
						$cmd=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdOpen')));
						if(is_object($cmd)){
							if(cache::byKey('porte::Move::'.$this->getEqLogic()->getId())->getValue(false)){
								log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
								$cmd->execCmd(null);
							}
							log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
							$cmd->execCmd(null);
						}
					}
				}
				cache::set('porte::ChangeStateStart::'.$this->getEqLogic()->getId(),microtime(true), 0);
				cache::set('porte::Sense::'.$this->getEqLogic()->getId(),false, 0);
				cache::set('porte::Move::'.$this->getEqLogic()->getId(),true, 0);
			break;
			case "stop":
				$cmd=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdStop')));
				if(is_object($cmd)){
					log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
					$cmd->execCmd(null);
				}else{
					if(cache::byKey('porte::Move::'.$this->getEqLogic()->getId())->getValue(false)){
						$cmd=cmd::byId(str_replace('#','',$this->getEqLogic()->getConfiguration('cmdOpen')));
						if(is_object($cmd)){
							log::add('porte','debug',$this->getEqLogic()->getHumanName().' Exécution de la commande '.$cmd->getHumanName());
							$cmd->execCmd(null);
						}
					}
				}
				cache::set('porte::Move::'.$this->getEqLogic()->getId(),false, 0);
				cache::set('porte::ChangeStateStop::'.$this->getEqLogic()->getId(),microtime(true), 0);
				$this->getEqLogic()->UpdateOuverture();
			break;
	}
}
?>
