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

class enphase extends eqLogic {
	/*		 * *************************Attributs****************************** */
	public static $_widgetPossibility = array('custom' => true);
	private static $_enphase = null;

	/*		 * ***********************Methode static*************************** */
	/* Fonction exécutée automatiquement toutes les minutes par Jeedom */
	public static function cron() {
		foreach (eqLogic::byType('enphase') as $enphase) {
			try {
				$enphase->getEnphaseInfo();
			} catch (Exception $e) {
			}
		}
	}

	/* Fonction exécutée automatiquement toutes les heures par Jeedom */
	/*
	public static function cronHourly() {
	}
	*/

	/* Fonction exécutée automatiquement tous les jours par Jeedom */
	/*
	public static function cronDaily() {
	}
	*/

	/*		 * *********************Méthodes d'instance************************* */
	public function preInsert() {
		$this->setCategory('energy', 1);
	}

	public function postInsert() {
	}

	public function preSave() {
	}

	public function postSave() {
		$enphaseCmd = $this->getCmd(null, 'now');
		if (!is_object($enphaseCmd)) {
			$enphaseCmd = new enphaseCmd();
		}
		$enphaseCmd->setName(__('Instantané', __FILE__));
		$enphaseCmd->setLogicalId('now');
		$enphaseCmd->setEqLogic_id($this->getId());
		$enphaseCmd->setIsHistorized(1);
		$enphaseCmd->setUnite('Wh');
		$enphaseCmd->setType('info');
		$enphaseCmd->setSubType('numeric');
		$enphaseCmd->setDisplay('generic_type', 'ENPHASE_NOW');
		$enphaseCmd->save();

		$enphaseCmd = $this->getCmd(null, 'daily');
		if (!is_object($enphaseCmd)) {
			$enphaseCmd = new enphaseCmd();
		}
		$enphaseCmd->setName(__('Journalier', __FILE__));
		$enphaseCmd->setLogicalId('daily');
		$enphaseCmd->setEqLogic_id($this->getId());
		$enphaseCmd->setUnite('Wh');
		$enphaseCmd->setType('info');
		$enphaseCmd->setSubType('numeric');
		$enphaseCmd->setDisplay('generic_type', 'ENPHASE_DAILY');
		$enphaseCmd->save();

		$enphaseCmd = $this->getCmd(null, 'lifetime');
		if (!is_object($enphaseCmd)) {
			$enphaseCmd = new enphaseCmd();
		}
		$enphaseCmd->setName(__('Cumulé', __FILE__));
		$enphaseCmd->setLogicalId('lifetime');
		$enphaseCmd->setEqLogic_id($this->getId());
		$enphaseCmd->setUnite('Wh');
		$enphaseCmd->setType('info');
		$enphaseCmd->setSubType('numeric');
		$enphaseCmd->setDisplay('generic_type', 'ENPHASE_LIFETIME');
		$enphaseCmd->save();
	}

	public function preUpdate() {
		switch ('') {
			case $this->getConfiguration('ip'):
				throw new Exception(__('L\'adresse IP ne peut être vide', __FILE__));
			case $this->getConfiguration('user'):
				throw new Exception(__('L\'identifiant ne peut être vide', __FILE__));
			case $this->getConfiguration('pass'):
				throw new Exception(__('Le mot de passe ne peut être vide', __FILE__));
		}
	}

	public function postUpdate() {
	}

	public function preRemove() {
	}

	public function postRemove() {
	}

	/* Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin */
	/*
	public function toHtml($_version = 'dashboard') {
	}
	*/

	/* Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration */
	/*
	public static function postConfig_<Variable>() {
	}
	*/

	/* Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration */
	/*
	public static function preConfig_<Variable>() {
	}
	*/

	/*		 * **********************Getteur Setteur*************************** */

	/*		 * ********************Méthodes fonctionnelles********************* */
	public function getEnphaseInfo() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->getConfiguration('ip') . '/api/v1/production');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_USERPWD, $this->getConfiguration('user') . ":" . $this->getConfiguration('pass'));
		$response = curl_exec($ch);
		curl_close($ch);

		$json_data = json_decode($response, true);

      	$this->formatWattHours('now', $json_data['wattsNow']);
      	$this->formatWattHours('daily', $json_data['wattHoursToday']);
      	$this->formatWattHours('lifetime', $json_data['wattHoursLifetime']);
	}

	private function formatWattHours($key, $value) {
		$enphaseCmd = $this->getCmd(null, $key);
      	$length = strlen($value);

      	$wattHours = 0;

      	switch ($length) {
			case 1:
			case 2:
			case 3:
            	$wattHours = $value;
				$enphaseCmd->setUnite('Wh');
            	break;
			case 4:
			case 5:
			case 6:
            	$wattHours = $value / pow(10, 3);
				$enphaseCmd->setUnite('kWh');
            	break;
			case 7:
			case 8:
			case 9:
            	$wattHours = $value / pow(10, 6);
				$enphaseCmd->setUnite('MWh');
            	break;
			default:
            	$wattHours = $value / pow(10, 9);
				$enphaseCmd->setUnite('GWh');
            	break;
        }

      	$enphaseCmd->save();
      	$this->checkAndUpdateCmd($key, $wattHours);
    }
}

class enphaseCmd extends cmd {
	/*		 * *************************Attributs****************************** */

	/*		 * ***********************Methode static*************************** */

	/*		 * *********************Methode d'instance************************* */
	/* Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS */
	/*
	public function dontRemoveCmd() {
		return true;
	}
	*/

	public function execute($_options = array()) {
		$eqLogic = $this->getEqlogic();
		if ($this->getLogicalId() == 'refresh') {
			return enphase::cron($eqLogic->getId());
		}
		$eqLogic->getEnphaseInfo();
	}

	/*		 * **********************Getteur Setteur*************************** */
}