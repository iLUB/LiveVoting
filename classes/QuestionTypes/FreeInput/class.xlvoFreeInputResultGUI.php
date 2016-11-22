<?php

/**
 * Class xlvoFreeInputResultGUI
 *
 * @author Oskar Truffer <ot@studer-raimann.ch>
 */
class xlvoFreeInputResultGUI extends xlvoResultGUI {

	/**
	 * @param xlvoVote[] $votes
	 * @return string
	 */
	public function getTextRepresentation($votes) {
		$strings = array();
		foreach ($votes as $vote) {
			$strings[] = $vote->getFreeInput();
		}

		return implode(", ", $strings);
	}
}