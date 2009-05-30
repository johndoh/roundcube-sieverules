<?php

/*
 +-----------------------------------------------------------------------+
 | plugins/sieverules/rcube_sieve_script.inc                             |
 |                                                                       |
 | This file is part of the RoundCube Webmail client                     |
 | Copyright (C) 2008-2009, RoundCube Dev. - Switzerland                 |
 | Licensed under the GNU GPL                                            |
 |                                                                       |
 | PURPOSE:                                                              |
 |	 rcube_sieve_script class for sieverules operations                  |
 |   (using PEAR::Net_Sieve)                                             |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 | Modifications by: Philip Weir                                         |
 |   * Changed name of keys in script array	                             |
 |   * Added support for address and envelope                            |
 |   * Added support for vacation                                        |
 |   * Added support for disabled rules (written to file as comment)     |
 |   * Added support for regex tests                                     |
 |   * Added support for imapflags                                       |
 |   * Added support for relational operators and comparators            |
 |   * Added support for subaddress tests                                |
 |   * Added support for notify action                                   |
 |   * Added support for stop action                                     |
 +-----------------------------------------------------------------------+

 $Id: $

*/

class rcube_sieve_script {
	private $elsif = true;
	private $content = array();
	private $supported = array(
						'fileinto',
						'reject',
						'ereject',
						'redirect',
						'keep',
						'discard',
						'vacation',
						'imapflags',
						'notify',
						'stop'
						);
	public $raw = '';

	public function __construct($script) {
		$this->raw = $script;
		$this->content = $this->parse_text($script);
	}

	public function add_text($script) {
		$content = $this->parse_text($script);
		$result = false;

		// check existsing script rules names
		foreach ($this->content as $idx => $elem)
			$names[$elem['name']] = $idx;

		foreach ($content as $elem) {
			if (!isset($names[$elem['name']])) {
				array_push($this->content, $elem);
				$result = true;
			}
		}

		return $result;
	}

	public function import_filters($content) {
		if (is_array($content)) {
			$result = false;

			// check existsing script rules names
			foreach ($this->content as $idx => $elem)
				$names[$elem['name']] = $idx;

			foreach ($content as $elem) {
				if (!isset($names[$elem['name']])) {
					array_push($this->content, $elem);
					$result = true;
				}
			}
		}
		else {
			$this->add_text($content);
		}
	}

	public function add_rule($content) {
		foreach ($content['actions'] as $action) {
			if (!in_array($action['type'], $this->supported))
				return false;
		}

		array_push($this->content, $content);
		return sizeof($this->content)-1;
	}

	public function delete_rule($index) {
		if(isset($this->content[$index])) {
			unset($this->content[$index]);
			return true;
		}

		return false;
	}

	public function size() {
		return sizeof($this->content);
	}

	public function update_rule($index, $content) {
		foreach ($content['actions'] as $action) {
			if (!in_array($action['type'], $this->supported))
				return false;
		}

		if ($this->content[$index]) {
			$this->content[$index] = $content;
			return $index;
		}

		return false;
	}

	public function as_text() {
		$script = '';
		$exts = array();

		// rules
		$activeRules = 0;
		foreach ($this->content as $rule) {
			$tests = array();
			$i = 0;

			if ($rule['disabled'] == 1) {
				$script .= '# rule:[' . $rule['name'] . "]\r\n";
				$script .= '# disabledRule:[' . $this->_safe_serial(serialize($rule)) . "]\r\n";
			}
			else {
				// header
				$script .= '# rule:[' . $rule['name'] . "]\r\n";

				// constraints expressions
				foreach ($rule['tests'] as $test) {
					$tests[$i] = '';

					switch ($test['type']) {
						case 'size':
							$tests[$i] .= ($test['not'] ? 'not ' : '');
							$tests[$i] .= 'size :' . ($test['operator']=='under' ? 'under ' : 'over ') . $test['target'];
							break;
						case 'true':
							$tests[$i] .= ($test['not'] ? 'not true' : 'true');
							break;
						case 'exists':
							$tests[$i] .= ($test['not'] ? 'not ' : '');

							if (is_array($test['header']))
								$tests[$i] .= 'exists ["' . implode('", "', $this->_escape_string($test['header'])) . '"]';
							else
								$tests[$i] .= 'exists "' . $this->_escape_string($test['header']) . '"';

							break;
						case 'envelope':
							array_push($exts, 'envelope');
						case 'header':
						case 'address':
							if ($test['operator'] == 'regex')
								array_push($exts, 'regex');
							elseif (substr($test['operator'], 0, 5) == 'count' || substr($test['operator'], 0, 5) == 'value')
								array_push($exts, 'relational');
							elseif ($test['operator'] == 'user' || $test['operator'] == 'detail' || $test['operator'] == 'domain')
								array_push($exts, 'subaddress');

							$tests[$i] .= ($test['not'] ? 'not ' : '');
							$tests[$i] .= $test['type']. ' :' . $test['operator'];

							if ($test['comparator'] != '') {
								if ($test['comparator'] != 'i;ascii-casemap' && $test['comparator'] != 'i;octet')
									array_push($exts, 'comparator-' . $test['comparator']);

								$tests[$i] .= ' :comparator "' . $test['comparator'] . '"';
							}

							if (is_array($test['header']))
								$tests[$i] .= ' ["' . implode('", "', $this->_escape_string($test['header'])) . '"]';
							else
								$tests[$i] .= ' "' . $this->_escape_string($test['header']) . '"';

							if (is_array($test['target']))
								$tests[$i] .= ' ["' . implode('", "', $this->_escape_string($test['target'])) . '"]';
							else
								$tests[$i] .= ' "' . $this->_escape_string($test['target']) . '"';

							break;
					}

					$i++;
				}

				$script .= ($activeRules > 0 && $this->elsif ? 'els' : '') . ($rule['join'] ? 'if allof (' : 'if anyof (');
				$activeRules++;

				if (sizeof($tests) > 1)
					$script .= implode(",\r\n\t", $tests);
				elseif (sizeof($tests))
					$script .= $tests[0];
				else
					$script .= 'true';

				$script .= ")\r\n{\r\n";

				// action(s)
				$actions = '';
				foreach ($rule['actions'] as $action) {
					switch ($action['type']) {
						case 'fileinto':
							array_push($exts, 'fileinto');
							$actions .= "\tfileinto \"" . $this->_escape_string($action['target']) . "\";\r\n";
							break;
						case 'redirect':
							$actions .= "\tredirect \"" . $this->_escape_string($action['target']) . "\";\r\n";
							break;
						case 'reject':
						case 'ereject':
							array_push($exts, $action['type']);

							if (strpos($action['target'], "\n")!==false)
								$actions .= "\t".$action['type']." text:\r\n" . $action['target'] . "\r\n.\r\n;\r\n";
							else
								$actions .= "\t".$action['type']." \"" . $this->_escape_string($action['target']) . "\";\r\n";

							break;
						case 'vacation':
							array_push($exts, 'vacation');
							$action['subject'] = $this->_escape_string($action['subject']);
							if ($action['origsubject'] == '1') $action['subject'] .= " \${1}";

// 							// encoding subject header with mb_encode provides better results with asian characters
// 							if (function_exists("mb_encode_mimeheader"))
// 							{
// 								mb_internal_encoding($action['charset']);
// 								$action['subject'] = mb_encode_mimeheader($action['subject'], $action['charset'], 'Q');
// 								mb_internal_encoding(RCMAIL_CHARSET);
// 							}

							$actions .= "\tvacation\r\n";
							$actions .= "\t\t:days ". $action['days'] ."\r\n";
							if (!empty($action['addresses'])) $actions .= "\t\t:addresses [\"". str_replace(",", "\",\"", $this->_escape_string($action['addresses'])) ."\"]\r\n";
							if (!empty($action['subject'])) $actions .= "\t\t:subject \"". $action['subject'] ."\"\r\n";
							if (!empty($action['handle'])) $actions .= "\t\t:handle \"". $this->_escape_string($action['handle']) ."\"\r\n";
							if (!empty($action['from'])) $actions .= "\t\t:from \"". $this->_escape_string($action['from']) ."\"\r\n";

							if ($action['charset'] != "UTF-8")
								$actions .= "\t\t:mime text:\r\nContent-Type: text/plain; charset=". $action['charset'] ."\r\n\r\n" . $action['msg'] . "\r\n.\r\n;\r\n";
							elseif (strpos($action['msg'], "\n")!==false)
								$actions .= "\t\ttext:\r\n" . $action['msg'] . "\r\n.\r\n;\r\n";
							else
								$actions .= "\t\t\"" . $this->_escape_string($action['msg']) . "\";\r\n";

							break;
						case 'imapflags':
							array_push($exts, 'imapflags');

							if (strpos($actions, "setflag") !== false)
								$actions .= "\taddflag \"" . $this->_escape_string($action['target']) . "\";\r\n";
							else
								$actions .= "\tsetflag \"" . $this->_escape_string($action['target']) . "\";\r\n";

							break;
						case 'notify':
							array_push($exts, 'notify');
							$actions .= "\tnotify\r\n";
							$actions .= "\t\t:method \"" . $this->_escape_string($action['method']) . "\"\r\n";
							if (!empty($action['options'])) $actions .= "\t\t:options [\"" . str_replace(",", "\",\"", $this->_escape_string($action['options'])) . "\"]\r\n";
							if (!empty($action['from'])) $actions .= "\t\t:from \"" . $this->_escape_string($action['from']) . "\"\r\n";
							if (!empty($action['importance'])) $actions .= "\t\t:importance \"" . $this->_escape_string($action['importance']) . "\"\r\n";
							$actions .= "\t\t:message \"". $this->_escape_string($action['msg']) ."\";\r\n";
							break;
						case 'keep':
						case 'discard':
						case 'stop':
							$actions .= "\t" . $action['type'] .";\r\n";
							break;
					}
				}

				$script .= $actions . "}\r\n";
			}
		}

		// requires
		$exts = array_unique($exts);
		if (sizeof($exts))
			$script = 'require ["' . implode('","', $exts) . "\"];\r\n" . $script;

		// author
		if ($script)
			$script = "## Generated by RoundCube Webmail SieveRules Plugin ##\r\n" . $script;

		return $script;
	}

	public function as_array() {
		return $this->content;
	}

	public function parse_text($script) {
		$i = 0;
		$content = array();

		// remove C comments
		$script = preg_replace('|/\*.*?\*/|sm', '', $script);

		// tokenize rules - \r is optional for backward compatibility (added 20090413)
		if ($tokens = preg_split('/(# rule:\[.*\])\r?\n/', $script, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			foreach($tokens as $token) {
				if (preg_match('/^# rule:\[(.*)\]/', $token, $matches)) {
					$content[$i]['name'] = $matches[1];
				}
				elseif (isset($content[$i]['name']) && sizeof($content[$i]) == 1 && preg_match('/^# disabledRule:\[(.*)\]/', $token, $matches)) {
					$content[$i] = unserialize($this->_regular_serial($matches[1]));
					$i++;
				}
				elseif (isset($content[$i]['name']) && sizeof($content[$i]) == 1) {
					if ($rule = $this->_tokenize_rule($token)) {
						$content[$i] = array_merge($content[$i], $rule);
						$i++;
					}
					else {
						unset($content[$i]);
					}
				}
			}
		}

		return $content;
	}

	private function _tokenize_rule($content) {
		$result = NULL;

		if (preg_match('/^(if|elsif|else)\s+((allof|anyof|exists|header|not|size|envelope|address)\s+(.*))\s+\{(.*)\}$/sm', trim($content), $matches)) {
			list($tests, $join) = $this->_parse_tests(trim($matches[2]));
			$actions = $this->_parse_actions(trim($matches[5]));

			if ($tests && $actions) {
				$result = array(
							'tests' => $tests,
							'actions' => $actions,
							'join' => $join,
							);
			}
		}

		return $result;
	}

	private function _parse_actions($content) {
		$content = str_replace("\r\n", "\n", $content);
		$result = NULL;

		// supported actions
		$patterns[] = '^\s*discard;';
		$patterns[] = '^\s*keep;';
		$patterns[] = '^\s*stop;';
		$patterns[] = '^\s*fileinto\s+(.*?[^\\\]);';
		$patterns[] = '^\s*redirect\s+(.*?[^\\\]);';
		$patterns[] = '^\s*setflag\s+(.*?[^\\\]);';
		$patterns[] = '^\s*addflag\s+(.*?[^\\\]);';
		$patterns[] = '^\s*reject\s+text:(.*)\n\.\n;';
		$patterns[] = '^\s*ereject\s+text:(.*)\n\.\n;';
		$patterns[] = '^\s*reject\s+(.*?[^\\\]);';
		$patterns[] = '^\s*ereject\s+(.*?[^\\\]);';
		$patterns[] = '^\s*vacation\s+:days\s+([0-9]+)\s+(:addresses\s+\[(.*?[^\\\])\]\s+)?(:subject\s+(".*?[^"\\\]")\s+)?(:handle\s+(".*?[^"\\\]")\s+)?(:from\s+(".*?[^"\\\]")\s+)?(:mime\s+)?text:(.*)\n\.\n;';
		$patterns[] = '^\s*vacation\s+:days\s+([0-9]+)\s+(:addresses\s+\[(.*?[^\\\])\]\s+)?(:subject\s+(".*?[^"\\\]")\s+)?(:handle\s+(".*?[^"\\\]")\s+)?(:from\s+(".*?[^"\\\]")\s+)?(.*?[^\\\]);';
		$patterns[] = '^\s*notify\s+:method\s+(".*?[^"\\\]")\s+(:options\s+\[(.*?[^\\\])\]\s+)?(:from\s+(".*?[^"\\\]")\s+)?(:importance\s+(".*?[^"\\\]")\s+)?:message\s+(".*?[^"\\\]");';

		$pattern = '/(' . implode('$)|(', $patterns) . '$)/ms';

		// parse actions body
		if (preg_match_all($pattern, $content, $mm, PREG_SET_ORDER)) {
    		foreach ($mm as $m) {
				$content = trim($m[0]);

				if(preg_match('/^(discard|keep|stop)/', $content, $matches)) {
					$result[] = array('type' => $matches[1]);
				}
				elseif(preg_match('/^fileinto/', $content)) {
					$result[] = array('type' => 'fileinto', 'target' => $this->_parse_string($m[sizeof($m)-1]));
				}
				elseif(preg_match('/^redirect/', $content)) {
					$result[] = array('type' => 'redirect', 'target' => $this->_parse_string($m[sizeof($m)-1]));
				}
				elseif(preg_match('/^(reject|ereject)\s+(.*);$/sm', $content, $matches)) {
					$result[] = array('type' => $matches[1], 'target' => $this->_parse_string($matches[2]));
				}
				elseif(preg_match('/^(setflag|addflag)/', $content)) {
					$result[] = array('type' => 'imapflags', 'target' => $this->_parse_string($m[sizeof($m)-1]));
				}
				elseif(preg_match('/^vacation\s+:days\s+([0-9]+)\s+(:addresses\s+\[(.*?[^\\\])\]\s+)?(:subject\s+(".*?[^"\\\]")\s+)?(:handle\s+(".*?[^"\\\]")\s+)?(:from\s+(".*?[^"\\\]")\s+)?(.*);$/sm', $content, $matches)) {
					$origsubject = "";
					if (substr($matches[5], -5, 4) == "\${1}") {
						$matches[5] = trim(substr($matches[5], 0, -5)) . "\"";
						$origsubject = "1";
					}

					if (function_exists("mb_decode_mimeheader")) $matches[5] = mb_decode_mimeheader($matches[5]);

					$result[] = array('type' => 'vacation',
									'days' => $matches[1],
									'subject' => $this->_parse_string($matches[5]),
									'origsubject' => $origsubject,
									'from' => $this->_parse_string($matches[9]),
									'addresses' => $this->_parse_string(str_replace("\",\"", ",", $matches[3])),
									'handle' => $this->_parse_string($matches[7]),
									'msg' => $this->_parse_string($matches[10]),
									'charset' => $this->_parse_charset($matches[10]));
				}
				elseif(preg_match('/^notify\s+:method\s+(".*?[^"\\\]")\s+(:options\s+\[(.*?[^\\\])\]\s+)?(:from\s+(".*?[^"\\\]")\s+)?(:importance\s+(".*?[^"\\\]")\s+)?:message\s+(".*?[^"\\\]");$/sm', $content, $matches)) {
					$result[] = array('type' => 'notify',
									'method' => $this->_parse_string($matches[1]),
									'options' => $this->_parse_string($matches[3]),
									'from' => $this->_parse_string($matches[5]),
									'importance' => $this->_parse_string($matches[7]),
									'msg' => $this->_parse_string($matches[8]));
				}
			}
		}

		return $result;
	}

	private function _parse_tests($content) {
		$result = NULL;

		// lists
		if (preg_match('/^(allof|anyof)\s+\((.*)\)$/sm', $content, $matches)) {
			$content = $matches[2];
			$join = $matches[1]=='allof' ? true : false;
		}
		else {
			$join = false;
		}

		// supported tests regular expressions
		$patterns[] = '(not\s+)?(exists)\s+\[(.*?[^\\\])\]';
		$patterns[] = '(not\s+)?(exists)\s+(".*?[^\\\]")';
		$patterns[] = '(not\s+)?(true)';
		$patterns[] = '(not\s+)?(size)\s+:(under|over)\s+([0-9]+[KGM]{0,1})';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(contains|is|matches|regex|user|detail|domain)((\s+))\[(.*?[^\\\]")\]\s+\[(.*?[^\\\]")\]';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(contains|is|matches|regex|user|detail|domain)((\s+))(".*?[^\\\]")\s+(".*?[^\\\]")';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(contains|is|matches|regex|user|detail|domain)((\s+))\[(.*?[^\\\]")\]\s+(".*?[^\\\]")';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(contains|is|matches|regex|user|detail|domain)((\s+))(".*?[^\\\]")\s+\[(.*?[^\\\]")\]';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(count\s+".*?[^\\\]"|value\s+".*?[^\\\]")(\s+:comparator\s+"(.*?[^\\\])")?\s+\[(.*?[^\\\]")\]\s+\[(.*?[^\\\]")\]';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(count\s+".*?[^\\\]"|value\s+".*?[^\\\]")(\s+:comparator\s+"(.*?[^\\\])")?\s+(".*?[^\\\]")\s+(".*?[^\\\]")';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(count\s+".*?[^\\\]"|value\s+".*?[^\\\]")(\s+:comparator\s+"(.*?[^\\\])")?\s+\[(.*?[^\\\]")\]\s+(".*?[^\\\]")';
		$patterns[] = '(not\s+)?(header|address|envelope)\s+:(count\s+".*?[^\\\]"|value\s+".*?[^\\\]")(\s+:comparator\s+"(.*?[^\\\])")?\s+(".*?[^\\\]")\s+\[(.*?[^\\\]")\]';

		// join patterns...
		$pattern = '/(' . implode(')|(', $patterns) . ')/';

		// ...and parse tests list
		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$size = sizeof($match);

				if (preg_match('/^(not\s+)?size/', $match[0])) {
					$result[] = array(
									'type' 		=> 'size',
									'not' 		=> $match[$size-4] ? true : false,
									'operator' 	=> $match[$size-2], // under/over
									'target'	=> $match[$size-1], // value
								);
				}
				elseif (preg_match('/^(not\s+)?(header|address|envelope)/', $match[0])) {
					$result[] = array(
									'type'		=> $match[$size-6],
									'not' 		=> $match[$size-7] ? true : false,
									'operator'	=> $match[$size-5], // is/contains/matches
									'header' 	=> $this->_parse_list($match[$size-2]), // header(s)
									'target'	=> $this->_parse_list($match[$size-1]), // string(s)
									'comparator' => trim($match[$size-3])
								);
				}
				elseif (preg_match('/^(not\s+)?exists/', $match[0])) {
					$result[] = array(
									'type'	 	=> 'exists',
									'not' 		=> $match[$size-3] ? true : false,
									'operator'	=> 'exists',
									'header' 	=> $this->_parse_list($match[$size-1]), // header(s)
								);
				}
				elseif (preg_match('/^(not\s+)?true/', $match[0])) {
					$result[] = array(
									'type' 	=> 'true',
									'not' 	=> $match[$size-2] ? true : false,
								);
				}
			}
		}

		return array($result, $join);
	}

	private function _parse_string($content) {
		$text = '';
		$content = trim($content);

		if (preg_match('/^:mime\s+text:(.*)\.$/sm', $content, $matches)) {
			$parts = split("\r?\n", $matches[1], 4);
			$text = trim($parts[3]);
		}
		elseif (preg_match('/^text:(.*)\.$/sm', $content, $matches))
			$text = trim($matches[1]);
		elseif (preg_match('/^"(.*)"$/', $content, $matches))
			$text = str_replace('\"', '"', $matches[1]);

		return $text;
	}

	private function _parse_charset($content) {
		$charset = RCMAIL_CHARSET;
		$content = trim($content);

		if (preg_match('/^:mime\s+text:(.*)\.$/sm', $content, $matches)) {
			$parts = split("\r?\n", $matches[1], 4);
			$charset = trim(substr($parts[1], stripos($parts[1], "charset=") + 8));
		}

		return $charset;
	}

	private function _escape_string($content) {
		$replace['/"/'] = '\\"';

		if (is_array($content)) {
			for ($x=0, $y=sizeof($content); $x<$y; $x++)
				$content[$x] = preg_replace(array_keys($replace), array_values($replace), $content[$x]);

			return $content;
		}
		else {
			return preg_replace(array_keys($replace), array_values($replace), $content);
		}
	}

	private function _parse_list($content) {
		$result = array();

		for ($x=0, $len=strlen($content); $x<$len; $x++) {
			switch ($content[$x]) {
				case '\\':
					$str .= $content[++$x];
					break;
				case '"':
					if (isset($str)) {
						$result[] = $str;
						unset($str);
					}
					else {
						$str = '';
					}

					break;
				default:
					if(isset($str))
						$str .= $content[$x];

					break;
			}
		}

		if (sizeof($result)>1)
			return $result;
		elseif (sizeof($result) == 1)
			return $result[0];
		else
			return NULL;
	}

	private function _safe_serial($data) {
		$data = str_replace("\r", "[!r]", $data);
		$data = str_replace("\n", "[!n]", $data);
		return $data;
	}

	private function _regular_serial($data) {
		$data = str_replace("[!r]", "\r", $data);
		$data = str_replace("[!n]", "\n", $data);
		return $data;
	}
}

?>