<?php
use LiveVoting\Option\xlvoOption;
use LiveVoting\QuestionTypes\xlvoQuestionTypes;
use LiveVoting\Vote\xlvoVote;
use LiveVoting\Voting\xlvoVoting;

/**
 * Class xlvoResultGUI
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
abstract class xlvoResultGUI {

	/**
	 * @var xlvoVoting
	 */
	protected $voting;
	/**
	 * @var xlvoOption[]
	 */
	protected $options;
	/**
	 * @var ilLiveVotingPlugin
	 */
	protected $pl;


	/**
	 * xlvoResultGUI constructor.
	 *
	 * @param $voting xlvoVoting
	 */
	public function __construct($voting) {
		$this->voting = $voting;
		$this->options = $voting->getVotingOptions();
		$this->pl = ilLiveVotingPlugin::getInstance();
	}


	/**
	 * @param $votes xlvoVote[]
	 * @return string
	 */
	public abstract function getTextRepresentation($votes);


	/**
	 * @param $votes xlvoVote[]
	 * @return string
	 */
	public abstract function getAPIRepresentation($votes);


	/**
	 * Creates an instance of the result gui.
	 *
	 * @param $voting               xlvoVoting    Finished or ongoing voting.
	 * @return xlvoResultGUI        Result GUI to display the voting results.
	 * @throws \ilException         Throws an \ilException if no result gui class was found for the
	 *                              given voting type.
	 */
	public static function getInstance(xlvoVoting $voting) {
		$class = xlvoQuestionTypes::getClassName($voting->getVotingType());

		switch ($class) {
			case xlvoQuestionTypes::CORRECT_ORDER:
				return new xlvoCorrectOrderResultGUI($voting);
			case xlvoQuestionTypes::FREE_INPUT:
				return new xlvoFreeInputResultGUI($voting);
			case xlvoQuestionTypes::FREE_ORDER:
				return new xlvoFreeOrderResultGUI($voting);
			case xlvoQuestionTypes::SINGLE_VOTE:
				return new xlvoSingleVoteResultGUI($voting);
			case xlvoQuestionTypes::NUMBER_RANGE:
				return new xlvoNumberRangeResultGUI($voting);
			default:
				throw new \ilException('Could not find the result gui for the given voting.');
		}
	}
}