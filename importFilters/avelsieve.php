<?php

/**
 * SieveRules import filter for Avelsieve
 *
 * The class should be named 'srimport_[filename]'
 * Each import filter must have:
 *   An attribute called name
 *   A pubic function called detector
 *   A pubic function called importer
 * The importer function can return either a string to be parsed by the SieveRules parser
 * or an array, similar to the one created by the SieveRules parser
 */
class srimport_avelsieve
{
	public $name = 'Squirrelmail (Avelsieve)';

	public function detector($script)
	{
		return preg_match('/#AVELSIEVE_VERSION.*/', $script) ? True : False;
	}

	public function importer($script)
	{
		$i = 0;
		$name = array();
		// tokenize rules
		if ($tokens = preg_split('/(#START_SIEVE_RULE.*END_SIEVE_RULE)\n/', $script, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			foreach($tokens as $token) {
				if (preg_match('/^#START_SIEVE_RULE.*/', $token, $matches)) {
					$name[$i] = "Unnamed Rule " . ($i + 1);
					$content .= "# rule:[" . $name[$i] . "]\n";
				}
				elseif (isset($name[$i])) {
					if (trim($token)) {
						$content .= "if " . $token . "\n";
						$i++;
					}
				}
				elseif (preg_match('/(require\s+\[.*\];)/i', $token, $matches)) {
					$content .= $matches[1] . "\n";
				}
			}
		}

		return $content;
	}
}
?>