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
		$enphaseCmd->setConfiguration('maxValue', $this->getConfiguration('maxP'));
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
	public function toHtml($_version = 'dashboard') {
		$replace = $this->preToHtml($_version);
		if (!is_array($replace)) {
				return $replace;
		}
		$version = jeedom::versionAlias($_version);

		$now = $this->getCmd(null, 'now');
		$replace['#now#'] = is_object($now) ? $this->formatWattHours($now->execCmd()) : '';
		$replace['#nowid#'] = is_object($now) ? $now->getId() : '';
		$replace['#nowuid#'] = is_object($now) ? $now->getId() : '';

		$daily = $this->getCmd(null, 'daily');
		$replace['#daily#'] = is_object($daily) ? $this->formatWattHours($daily->execCmd()) : '';
		$replace['#dailyid#'] = is_object($daily) ? $daily->getId() : '';

		$lifetime = $this->getCmd(null, 'lifetime');
		$replace['#lifetime#'] = is_object($lifetime) ? $this->formatWattHours($lifetime->execCmd()) : '';
		$replace['#lifetimeid#'] = is_object($lifetime) ? $lifetime->getId() : '';
		
		$html = template_replace($replace, getTemplate('core', $version, 'widget', 'enphase'));
		cache::set('widgetHtml' . $_version . $this->getId(), $html, 0);
		return $html;
	}

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

		$this->updateWattHours('now', $json_data['wattsNow']);
		$this->updateWattHours('daily', $json_data['wattHoursToday']);
		$this->updateWattHours('lifetime', $json_data['wattHoursLifetime']);
	}

	private function updateWattHours($key, $value) {
		$this->checkAndUpdateCmd($key, $value);
	}

	private function formatWattHours($value) {
		$unit = array(
			0 => '',
			3 => 'k',
			6 => 'M',
			9 => 'G',
			12 => 'T',
			15 => 'P',
			18 => 'E',
			21 => 'Z',
			24 => 'Y',
		);

		for ($u=0; $u<=24; $u+=3) {
			$result = $value / pow(10, $u);

			if (!isset($best) || ($result >= 1 && $result < $best['converted'])) {
				$best = array(
					'initial' => $value,
					'converted' => $result,
					'rounded' => round($result, 2),
					'prefixUnit' => $unit[$u],
				);
			}
		}

		$wattHours = $best['rounded'];

		return $wattHours . ' ' . $best['prefixUnit'].'Wh';
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