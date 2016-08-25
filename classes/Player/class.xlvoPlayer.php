<?php

require_once('./Services/ActiveRecord/class.ActiveRecord.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/classes/Voter/class.xlvoVoter.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/classes/Vote/class.xlvoVote.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/classes/Player/class.xlvoPlayerGUI.php');

/**
 * Class xlvoPlayer
 *
 * @author  Daniel Aemmer <daniel.aemmer@phbern.ch>
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 * @version 1.0.0
 */
class xlvoPlayer extends ActiveRecord {

	const STAT_STOPPED = 0;
	const STAT_RUNNING = 1;
	const STAT_START_VOTING = 2;
	const STAT_END_VOTING = 3;
	const STAT_FROZEN = 4;
	const SECONDS_ACTIVE = 4;
	const SECONDS_TO_SLEEP = 30;
	/**
	 * @var array
	 */
	protected static $instance_cache = array();


	/**
	 * @return string
	 */
	public static function returnDbTableName() {
		return 'rep_robj_xlvo_player_n';
	}


	/**
	 * @param $obj_id
	 * @return xlvoPlayer
	 */
	public static function getInstanceForObjId($obj_id) {
		if (!empty(self::$instance_cache[$obj_id])) {
			return self::$instance_cache[$obj_id];
		}
		$obj = self::where(array( 'obj_id' => $obj_id ))->first();
		if (!$obj instanceof self) {
			$obj = new self();
			$obj->setObjId($obj_id);
		}
		self::$instance_cache[$obj_id] = $obj;

		return self::$instance_cache[$obj_id];
	}


	/**
	 * @param bool $simulate_user
	 * @return int
	 */
	public function getStatus($simulate_user = false) {
		if ($simulate_user && $this->isFrozenOrUnattended()) {
			return self::STAT_FROZEN;
		}

		return $this->status;
	}


	public function freeze() {
		$this->setFrozen(true);
		$this->setStatus(self::STAT_RUNNING);
		$this->resetCountDown(false);
		$this->update();
	}


	public function unfreeze() {
		$this->setFrozen(false);
		$this->setStatus(self::STAT_RUNNING);
		$this->resetCountDown(false);
		$this->update();
	}


	public function toggleFreeze() {
		$this->setFrozen(!$this->isFrozen());
		if ($this->isFrozen()) {
			$this->setCountdown(0);
		}
		$this->setStatus(self::STAT_RUNNING);
		$this->update();
	}


	/**
	 * @return int
	 */
	public function remainingCountDown() {
		return $this->getCountdownStart() - time() + $this->getCountdown();
	}


	/**
	 * @param $seconds
	 */
	public function startCountDown($seconds) {
		$this->setFrozen(false);
		$this->setCountdown($seconds);
		$this->setCountdownStart(time());
		$this->update();
	}


	/**
	 * @param bool $save
	 */
	public function resetCountDown($save = true) {
		$this->setCountdown(0);
		$this->setCountdownStart(0);
		if ($save) {
			$this->update();
		}
	}


	public function show() {
		$this->setShowResults(true);
		$this->update();
	}


	public function hide() {
		$this->setShowResults(false);
		$this->update();
	}


	public function toggleResults() {
		$this->setShowResults(!$this->isShowResults());
		$this->update();
	}


	public function terminate() {
		$this->setStatus(xlvoPlayer::STAT_END_VOTING);
		$this->setFrozen(true);
		$this->resetCountDown();
		$this->setShowResults(false);
		$this->setButtonStates(array());
		$this->update();
	}


	/**
	 * @return stdClass
	 */
	public function getStdClassForVoter() {
		$obj = new stdClass();
		$obj->status = (int)$this->getStatus(true);
		$obj->force_reload = false;
		$obj->active_voting_id = (int)$this->getActiveVotingId();
		$obj->countdown = (int)$this->remainingCountDown();
		$obj->has_countdown = (bool)$this->isCountDownRunning();
		$obj->countdown_classname = $this->getCountdownClassname();
		$obj->frozen = (bool)$this->isFrozen();

		return $obj;
	}


	/**
	 * @return stdClass
	 */
	public function getStdClassForPlayer() {
		$obj = new stdClass();
		$obj->is_first = (bool)$this->getCurrentVotingObject()->isFirst();
		$obj->is_last = (bool)$this->getCurrentVotingObject()->isLast();
		$obj->status = (int)$this->getStatus(true);
		$obj->active_voting_id = (int)$this->getActiveVotingId();
		$obj->show_results = (bool)$this->isShowResults();
		$obj->frozen = (bool)$this->isFrozen();
		$obj->votes = (int)xlvoVote::where(array(
			'voting_id' => $this->getCurrentVotingObject()->getId(),
			'status'    => xlvoVote::STAT_ACTIVE,
		))->count();

		$last_update = xlvoVote::where(array(
			'voting_id' => $this->getActiveVotingId(),
			'status'    => xlvoVote::STAT_ACTIVE,
		))->orderBy('last_update', 'DESC')->getArray('last_update', 'last_update');
		$last_update = array_shift(array_values($last_update));
		$obj->last_update = (int)$last_update;
		$obj->attendees = (int)xlvoVoter::countVoters($this->getId());;
		$obj->qtype = $this->getQuestionTypeClassName();
		$obj->countdown = $this->remainingCountDown();
		$obj->has_countdown = $this->isCountDownRunning();

		return $obj;
	}


	/**
	 * @return string
	 */
	public function getQuestionTypeClassName() {
		return xlvoQuestionTypes::getClassName($this->getActiveVotingId());
	}


	/**
	 * @return bool
	 */
	public function isFrozenOrUnattended() {
		if ($this->getStatus() == self::STAT_RUNNING) {
			return (bool)($this->isFrozen() || $this->isUnattended());
		} else {
			return false;
		}
	}


	/**
	 * @return bool
	 */
	public function isCountDownRunning() {
		return ($this->remainingCountDown() > 0 || $this->getCountdownStart() > 0);
	}


	/**
	 * @return string
	 */
	public function getCountdownClassname() {
		$cd = $this->remainingCountDown();

		return $cd > 10 ? 'running' : ($cd > 5 ? 'warning' : 'danger');
	}


	/**
	 * @param $voting_id
	 */
	public function prepareStart($voting_id) {
		$this->setStatus(self::STAT_START_VOTING);
		$this->resetCountDown(false);
		$this->setFrozen(true);
		$this->setShowResults(false);
		$this->setTimestampRefresh(time() + self::SECONDS_TO_SLEEP);
		$this->setActiveVoting($voting_id);
		$this->store();
	}


	public function store() {
		if (self::where(array( 'id' => $this->getId() ))->hasSets()) {
			$this->update();
		} else {
			$this->create();
		}
	}


	/**
	 * @return bool
	 */
	public function isUnattended() {
		if ($this->getStatus() != self::STAT_STOPPED AND ($this->getTimestampRefresh() < (time() - self::SECONDS_TO_SLEEP))) {
			$this->setStatus(self::STAT_STOPPED);
			$this->update();
		}
		if ($this->getStatus() == self::STAT_START_VOTING) {
			return false;
		}
		if ($this->getStatus() == self::STAT_STOPPED) {
			return false;
		}

		return (bool)($this->getTimestampRefresh() < (time() - self::SECONDS_ACTIVE));
	}


	public function attend() {
		$this->setStatus(self::STAT_RUNNING);
		$this->setTimestampRefresh(time());
		if ($this->remainingCountDown() <= 0 && $this->getCountdownStart() > 0) {
			$this->resetCountDown(false);
			$this->setFrozen(true);
		}
		$this->update();
	}


	/**
	 * @return xlvoVoting
	 */
	protected function getCurrentVotingObject() {
		return xlvoVoting::find($this->getActiveVotingId());
	}


	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 * @db_is_primary       true
	 * @con_sequence        true
	 */
	protected $id;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 */
	protected $obj_id;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 */
	protected $active_voting;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 */
	protected $status;
	/**
	 * @var bool
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           1
	 */
	protected $frozen = true;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 */
	protected $timestamp_refresh;
	/**
	 * @var bool
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           1
	 */
	protected $show_results = false;
	/**
	 * @var array
	 *
	 * @db_has_field        true
	 * @db_fieldtype        text
	 * @db_length           1024
	 */
	protected $button_states = array();
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           2
	 */
	protected $countdown = 0;
	/**
	 * @var int
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           8
	 */
	protected $countdown_start = 0;
	/**
	 * @var bool
	 *
	 * @db_has_field        true
	 * @db_fieldtype        integer
	 * @db_length           1
	 */
	protected $force_reload = false;


	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}


	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}


	/**
	 * @return int
	 */
	public function getObjId() {
		return $this->obj_id;
	}


	/**
	 * @param int $obj_id
	 */
	public function setObjId($obj_id) {
		$this->obj_id = $obj_id;
	}


	/**
	 * @return int
	 */
	public function getActiveVotingId() {
		return $this->active_voting;
	}


	/**
	 * @param int $active_voting
	 */
	public function setActiveVoting($active_voting) {
		$this->active_voting = $active_voting;
	}


	/**
	 * @param int $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}


	/**
	 * @return boolean
	 */
	public function isFrozen() {
		return $this->frozen;
	}


	/**
	 * @param boolean $frozen
	 */
	public function setFrozen($frozen) {
		$this->frozen = $frozen;
	}


	/**
	 * @return int
	 */
	public function getTimestampRefresh() {
		return $this->timestamp_refresh;
	}


	/**
	 * @param int $timestamp_refresh
	 */
	public function setTimestampRefresh($timestamp_refresh) {
		$this->timestamp_refresh = $timestamp_refresh;
	}


	/**
	 * @return boolean
	 */
	public function isShowResults() {
		return $this->show_results;
	}


	/**
	 * @param boolean $show_results
	 */
	public function setShowResults($show_results) {
		$this->show_results = $show_results;
	}


	/**
	 * @return array
	 */
	public function getButtonStates() {
		return $this->button_states;
	}


	/**
	 * @param array $button_states
	 */
	public function setButtonStates($button_states) {
		$this->button_states = $button_states;
	}


	/**
	 * @return int
	 */
	public function getCountdown() {
		return $this->countdown;
	}


	/**
	 * @param int $countdown
	 */
	public function setCountdown($countdown) {
		$this->countdown = $countdown;
	}


	/**
	 * @return boolean
	 */
	public function isForceReload() {
		return $this->force_reload;
	}


	/**
	 * @param boolean $force_reload
	 */
	public function setForceReload($force_reload) {
		$this->force_reload = $force_reload;
	}


	/**
	 * @return int
	 */
	public function getCountdownStart() {
		return $this->countdown_start;
	}


	/**
	 * @param int $countdown_start
	 */
	public function setCountdownStart($countdown_start) {
		$this->countdown_start = $countdown_start;
	}


	/**
	 * @param $field_name
	 * @return mixed|string
	 */
	public function sleep($field_name) {
		switch ($field_name) {
			case 'button_states':
				$var = $this->{$field_name};
				if (!is_array($var)) {
					$var = array();
				}

				return json_encode($var);
		}

		return null;
	}


	/**
	 * @param $field_name
	 * @param $field_value
	 * @return mixed|null
	 */
	public function wakeUp($field_name, $field_value) {
		switch ($field_name) {
			case 'button_states':
				$var = json_decode($field_value, true);
				if (!is_array($var)) {
					$var = array();
				}

				return $var;
		}

		return null;
	}
}