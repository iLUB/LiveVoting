<?php

namespace LiveVoting\Exceptions;

/**
 * Class xlvoPlayerException
 *
 * @package LiveVoting\Exceptions
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 */
class xlvoPlayerException extends xlvoException {

	const OBJ_OFFLINE = 1;
	const NO_VTOTINGS = 2;


	/**
	 * xlvoPlayerException constructor.
	 *
	 * @param string $a_message
	 * @param int    $a_code
	 */
	public function __construct($a_message, $a_code) {
		parent::__construct($a_message, $a_code);
	}
}
