<?php

/**
 * SieveRules import filter for INGO
 *
 * The class should be named 'srimport_[filename]'
 * Each import filter must have:
 *   An attribute called name
 *   A pubic function called detector
 *   A pubic function called importer
 * The importer function can return either a string to be parsed by the SieveRules parser
 * or an array, similar to the one created by the SieveRules parser
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * This import filter is part of the SieveRules plugin for Roundcube.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see http://www.gnu.org/licenses/.
 */
class srimport_ingo
{
	public $name = 'Horde (INGO)';

	public function detector($script, $name)
	{
		return preg_match('/# [a-z0-9\ ]+/i', $script) ? True : False;
	}

	public function importer($script)
	{
		$i = 0;
		$name = array();
		// tokenize rules
		if ($tokens = preg_split('/(# .+)\r?\n/i', $script, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			// unset first token, its the ingo header
			$tokens[1] = "";

			foreach($tokens as $token) {
				if (preg_match('/^# (.+)/i', $token, $matches)) {
					$name[$i] = $matches[1];
					$content .= "# rule:[" . $name[$i] . "]\n";
				}
				elseif (isset($name[$i])) {
					$token = str_replace(":comparator \"i;ascii-casemap\" ", "", $token);
					$content .= $token . "\n";
					$i++;
				}
				elseif (preg_match('/^\r?\n?require/i', $token)) {
					$content .= $token . "\n";
				}
			}
		}

		return $content;
	}
}

?>