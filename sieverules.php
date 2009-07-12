<?php

/**
 * SieveRules
 *
 * Plugin to allow the user to manage their Sieve filters using the managesieve protocol
 *
 * @version 1.0-BETA
 * @author Philip Weir
 * @url http://roundcube.net/plugins/sieverules
 */
class sieverules extends rcube_plugin
{
	public $task = 'settings';
	private $config;
	private $sieve;
	private $sieve_error;
	private $script;
	private $action;
	private $examples = array();

	// default values: label => value
	private $headers = array('subject' => 'header::Subject',
  					'from' => 'address::Sender',
  					'to' => 'address::To',
  					'cc' => 'address::Cc',
  					'bcc' => 'address::Bcc',
  					'envelopeto' => 'envelope::To',
  					'envelopefrom' => 'envelope::From'
  					);

	private $operators = array('filtercontains' => 'contains',
  					'filternotcontains' => 'notcontains',
  					'filteris' => 'is',
  					'filterisnot' => 'notis',
  					'filterexists' => 'exists',
  					'filternotexists' => 'notexists'
  					);

	private $flags = array('flagread' => '\\\\Seen',
  					'flagdeleted' => '\\\\Deleted',
  					'flaganswered' => '\\\\Answered',
  					'flagdraft' => '\\\\Draft',
  					'flagflagged' => '\\\\Flagged'
  					);

	function init()
	{
		$this->action = rcmail::get_instance()->action;
		$this->add_texts('localization/', array('filters', 'norulename', 'ruleexists', 'noheader', 'headerbadchars','noheadervalue',
		  'sizewrongformat', 'noredirect', 'redirectaddresserror', 'noreject','vacnodays', 'vacdayswrongformat', 'vacnosubject',
		  'vacnomsg', 'notifynomothod', 'notifynomsg', 'filterdeleteconfirm', 'ruledeleteconfirm', 'actiondeleteconfirm',
		  'movingfilter', 'switchtoadveditor', 'notifyinvalidmothod', 'nobodycontentpart','badoperator'));
		$this->register_action('plugin.sieverules', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.add', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.edit', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.setup', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.advanced', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.move', array($this, 'move'));
		$this->register_action('plugin.sieverules.save', array($this, 'save'));
		$this->register_action('plugin.sieverules.delete', array($this, 'delete'));
		$this->register_action('plugin.sieverules.import', array($this, 'import'));
		$this->include_script('sieverules.js');
	}

	function init_html()
	{
		$this->_load_config();
		$this->_startup();

		if ($this->config['adveditor'] == '2' && get_input_value('_override', RCUBE_INPUT_GET) != '1' && $this->action == 'plugin.sieverules') {
			rcmail_overwrite_action('plugin.sieverules.advanced');
			$this->action = 'plugin.sieverules.advanced';
		}

		$this->api->output->add_handlers(array(
		'sieveruleslist' => array($this, 'gen_list'),
		'sieverulesexamplelist' => array($this, 'gen_examples'),
		'sieverulessetup' => array($this, 'gen_setup'),
		'sieveruleform' => array($this, 'gen_form'),
		'advancededitor' => array($this, 'gen_advanced'),
		'advswitch' => array($this, 'gen_advswitch'),
		));

		if ($this->action != 'plugin.sieverules.advanced')
			$this->api->output->include_script('list.js');

		if (sizeof($this->examples) > 0)
			$this->api->output->set_env('examples', 'true');

		if($this->action == 'plugin.sieverules.add') {
			$this->api->output->set_pagetitle($this->gettext('newfilter'));
			$this->api->output->send('sieverules.editsieverule');
		}
		elseif ($this->action == 'plugin.sieverules.edit') {
			$this->api->output->set_pagetitle($this->gettext('edititem'));
			$this->api->output->send('sieverules.editsieverule');
		}
		elseif ($this->action == 'plugin.sieverules.setup') {
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->send('sieverules.setupsieverules');
		}
		elseif ($this->action == 'plugin.sieverules.advanced') {
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->send('sieverules.advancededitor');
		}
		else {
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->send('sieverules.sieverules');
		}
	}

	function gen_advanced()
	{
		list($form_start, $form_end) = get_form_tags(null, 'plugin.sieverules.save');
		$out = $form_start;

		$input_script = new html_textarea(array('id' => 'sieverules_adv', 'name' => '_script'));
		$out .= $input_script->show(htmlspecialchars($this->sieve->script->raw));

		$out .= $form_end;

		return $out;
	}

	function gen_list($attrib)
	{
		$this->api->output->add_gui_object('sieverules_list', 'sieverules-table');

		$table = new html_table(array('id' => 'sieverules-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
		$table->add_header(array('colspan' => 2), $this->gettext('filters'));

		if (sizeof($this->script) == 0) {
			$table->add(array('colspan' => '2'), rep_specialchars_output($this->gettext('nosieverules')));
			$table->add_row();
		}
		else foreach($this->script as $idx => $filter) {
			$table->set_row_attribs(array('id' => 'rcmrow' . $idx));

			if ($filter['disabled'] == 1)
				$table->add(null, $filter['name'] . ' (' . $this->gettext('disabled') . ')');
			else
				$table->add(null, $filter['name']);

			$dst = $idx - 1;
			$up_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $idx .','. $dst, 'type' => 'image', 'image' => $attrib['upicon'], 'alt' => 'sieverules.moveup', 'title' => 'sieverules.moveup'));
			$dst = $idx + 2;
			$down_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $idx .','. $dst, 'type' => 'image', 'image' => $attrib['downicon'], 'alt' => 'sieverules.movedown', 'title' => 'sieverules.movedown'));

			$table->add('control', $up_link . '&nbsp;' . $down_link);
		}

		return html::tag('div', array('id' => 'sieverules-list-filters'), $table->show());
	}

	function gen_examples()
	{
		if (sizeof($this->examples) > 0) {
			$this->api->output->add_gui_object('sieverules_examples', 'sieverules-examples');

			$examples = new html_table(array('id' => 'sieverules-examples', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1));
			$examples->add_header(null, $this->gettext('examplefilters'));

			foreach($this->examples as $idx => $filter) {
				$examples->set_row_attribs(array('id' => 'rcmrowex' . $idx));
				$examples->add(null, $filter['name']);
			}

			return html::tag('div', array('id' => 'sieverules-list-examples'), $examples->show());
		}
		else {
			return '';
		}

	}

	function gen_advswitch()
	{
		if ($this->config['adveditor'] == '1' or $this->config['adveditor'] == '2') {
			$input_adv = new html_checkbox(array('id' => 'adveditor', 'onclick' => JS_OBJECT_NAME . '.sieverules_adveditor(this);', 'value' => '1'));
			$out = html::label('adveditor', Q($this->gettext('adveditor'))) . $input_adv->show($this->action == 'plugin.sieverules.advanced' ? '1' : '');
			return html::tag('div', array('id' => 'advancedmode'), $out);
		}

		return '';
	}

	function gen_setup()
	{
		$text = '';
		$buttons = '';

		if (!empty($this->config['default_file']) && is_readable($this->config['default_file'])) {
			$text .= "<br /><br />" . $this->gettext('importdefault');
			$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_ruleset=_default_', 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.usedefaultfilter'));
		}

		$type = '';
		$ruleset = '';
		if (sizeof($this->sieve->list) > 0) {
			if ($result = $this->sieve->check_import()) {
				list($type, $name, $ruleset) = $result;
				$text .= "<br /><br />" . str_replace('%s', $name, $this->gettext('importother'));
				$buttons .= (strlen($buttons) > 0) ? '&nbsp;&nbsp;' : '';
				$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_type=' . $type . '&_ruleset=' . $ruleset, 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.importfilter'));
			}
		}

		if ($this->config['auto_load_default'] && strlen($text) > 0 && strlen($buttons) > 0 && $type == '' && $ruleset == '') {
			$this->sieve->script->add_text(file_get_contents($this->config['default_file']));
			$this->sieve->save();

			// update rule list
			if ($this->sieve_error)
				$this->script = array();
			else
				$this->script = $this->sieve->script->as_array();

			$this->api->output->send('sieverules.sieverules');
		}
		else if (strlen($text) > 0 && strlen($buttons) > 0) {
			$out = "<br />". $this->gettext('noexistingfilters') . $text . "<br /><br /><br />\n";
			$out .= $buttons;
			$out .= "&nbsp;&nbsp;" . $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_ruleset=_none_', 'type' => 'input', 'class' => 'button', 'label' => 'cancel'));

			$out = html::tag('p', array('style' => 'text-align: center; padding: 10px;'), "\n" . $out);
			$out = html::tag('div', array('id' => 'identity-title'), Q($this->gettext('importfilters'))) . $out;

			return $out;
		}
		else {
			$this->sieve->save();
			$this->api->output->send('sieverules.sieverules');
		}
	}

	function gen_form($attrib)
	{
		$ext = $this->sieve->get_extensions();
		$iid = get_input_value('_iid', RCUBE_INPUT_GPC);
		if ($iid == '')
			$iid = sizeof($this->script);

		if (substr($iid, 0, 2) == 'ex') {
			$cur_script = $this->examples[substr($iid, 2)];
			$this->api->output->set_env('eid', $iid);
			$iid = sizeof($this->script);
			$this->api->output->set_env('iid', $iid);
			$example = true;
		}
		else {
			$cur_script = $this->script[$iid];
			$this->api->output->set_env('iid', $iid);
			$example = false;
		}

		if (sizeof($this->config['predefined_rules']) > 0) {
			$predefined = array();
			foreach($this->config['predefined_rules'] as $idx => $data)
				array_push($predefined, array($data['type'], $data['header'], $data['operator'], $data['extra'], $data['target']));

			$this->api->output->set_env('predefined_rules', $predefined);
		}

		list($form_start, $form_end) = get_form_tags(null, 'plugin.sieverules.save');

		$out = $form_start;

		$hidden_iid = new html_hiddenfield(array('name' => '_iid', 'value' =>  $iid));
		$out .= $hidden_iid->show();

		// 'any' flag
		if (sizeof($cur_script['tests']) == 1 && $cur_script['tests'][0]['type'] == 'true' && !$cur_script['tests'][0]['not'])
			$any = true;

	    // filter disable
		$field_id = 'rcmfd_disable';
		$input_disable = new html_checkbox(array('name' => '_disable', 'id' => $field_id, 'value' => 1));

		$out .= html::span('disableLink', html::label($field_id, Q($this->gettext('disablerule')))
				 . "&nbsp;" . $input_disable->show($cur_script['disabled']));

		// filter name input
		$field_id = 'rcmfd_name';
		$input_name = new html_inputfield(array('name' => '_name', 'id' => $field_id));

	    $out .= html::label($field_id, Q($this->gettext('filtername')));
	    $out .= "&nbsp;" . $input_name->show($cur_script['name']);

		$out .= "<br /><br />";

		if (sizeof($cur_script['tests']) == 1 && $cur_script['tests'][0]['type'] == 'true' && !$cur_script['tests'][0]['not'])
			$join_any = true;

		$field_id = 'rcmfd_join_all';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'allof', 'onclick' => JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'allof\')'));
		$join_type = $input_join->show($cur_script['join'] && !$join_any ? 'allof' : '');
		$join_type .= "&nbsp;" . html::label($field_id, Q($this->gettext('filterallof')));

		$field_id = 'rcmfd_join_anyof';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'anyof', 'onclick' => JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'anyof\')'));
		$join_type .= "&nbsp;" . $input_join->show($cur_script['join'] && !$join_any ? '' : 'anyof');
		$join_type .= "&nbsp;" . html::label($field_id, Q($this->gettext('filteranyof')));

		$field_id = 'rcmfd_join_any';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'any', 'onclick' => JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'any\')'));
		$join_type .= "&nbsp;" . $input_join->show($join_any ? 'any' : '');
		$join_type .= "&nbsp;" . html::label($field_id, Q($this->gettext('filterany')));

		$rules_table = new html_table(array('id' => 'rules-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 5));
	    $rules_table = $this->_rule_row($ext, $rules_table, null, $this->config['predefined_rules'], $attrib);

	    if (!$join_any) {
		    if (!isset($cur_script))
		    	$rules_table = $this->_rule_row($ext, $rules_table, array(), $this->config['predefined_rules'], $attrib);
		    else foreach ($cur_script['tests'] as $rules)
		    	$rules_table = $this->_rule_row($ext, $rules_table, $rules, $this->config['predefined_rules'], $attrib);
		}

		$out .= html::tag('fieldset', null, html::tag('legend', null, Q($this->gettext('messagesrules')))
				 . Q($this->gettext('sieveruleexp')) . "<br /><br />"
				 . $join_type . "<br /><br />"
				 . $rules_table->show());

		rcmail::get_instance()->imap_init(TRUE);
		$actions_table = new html_table(array('id' => 'actions-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
	    $actions_table = $this->_action_row($ext, $actions_table, 'rowid', null, $attrib);

	    if (!isset($cur_script))
	    	$actions_table = $this->_action_row($ext, $actions_table, 0, array(), $attrib);
	    else foreach ($cur_script['actions'] as $idx => $actions)
	    	$actions_table = $this->_action_row($ext, $actions_table, $idx, $actions, $attrib);

		$out .= html::tag('fieldset', null, html::tag('legend', null, Q($this->gettext('messagesactions')))
				. Q($this->gettext('sieveactexp')). "<br /><br />"
				. $actions_table->show());

		$out .= $form_end;

		return $out;
	}

	function move()
	{
		$this->_load_config();
		$this->_startup();

		$src = get_input_value('_src', RCUBE_INPUT_GET);
		$dst = get_input_value('_dst', RCUBE_INPUT_GET);

		$result = $this->sieve->script->move_rule($src, $dst);
		$result = $this->sieve->save();

		if ($result)
			$this->api->output->command('sieverules_update_list', $src , $dst);
		else
			$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');

		$this->api->output->send();
	}

	function save()
	{
		$this->_load_config();
		$this->_startup();

		$script = trim(get_input_value('_script', RCUBE_INPUT_POST));
		if ($script != '' && ($this->config['adveditor'] == '1' || $this->config['adveditor'] == '2')) {
			$script = $this->_strip_val($script);
			$save = $this->sieve->save($script);

			if ($save) {
				$this->api->output->command('display_message', $this->gettext('filtersaved'), 'confirmation');
				$this->sieve->get_script();
			}
			else {
				$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');
			}

			// go to next step
			rcmail_overwrite_action('plugin.sieverules.advanced');
			$this->action = 'plugin.sieverules.advanced';
			$this->init_html();
		}
		else {
			$name = trim(get_input_value('_name', RCUBE_INPUT_POST));
			$fid = trim(get_input_value('_iid', RCUBE_INPUT_POST));
			$join = trim(get_input_value('_join', RCUBE_INPUT_POST));
			$disabled = trim(get_input_value('_disable', RCUBE_INPUT_POST));

			$tests = $_POST['_test'];
			$headers = $_POST['_header'];
			$bodyparts = $_POST['_bodypart'];
			$ops = $_POST['_operator'];
			$sizeops = $_POST['_size_operator'];
			$targets = $_POST['_target'];
			$sizeunits = $_POST['_units'];
			$contentparts = $_POST['_body_contentpart'];
			$comparators = $_POST['_comparator'];
			$advops = $_POST['_advoperator'];
			$advtargets = $_POST['_advtarget'];
			$actions = $_POST['_act'];
			$folders = $_POST['_folder'];
			$addresses = $_POST['_redirect'];
			$rejects = $_POST['_reject'];
			$vacfroms = $_POST['_vacfrom'];
			$vactos = $_POST['_vacto'];
			$days = $_POST['_day'];
			$handles = $_POST['_handle'];
			$subjects = $_POST['_subject'];
			$origsubjects = $_POST['_orig_subject'];
			$msgs = $_POST['_msg'];
			$charsets = $_POST['_charset'];
			$flags = $_POST['_imapflags'];
			$nfroms = $_POST['_nfrom'];
			$nimpts = $_POST['_nimpt'];
			$nmethods = $_POST['_nmethod'];
			$noptions = $_POST['_noption'];
			$nmsgs = $_POST['_nmsg'];

			$script = array();
			$script['join'] = ($join == 'allof') ? true : false;
			$script['name'] = $name;
			$script['disabled'] = $disabled;
			$script['tests'] = array();
			$script['actions'] = array();

			// rules
			$i = 0;
			if ($join == 'any') {
				$script['tests'][0]['type'] = 'true';
			}
			else foreach($tests as $idx => $type) {
				// ignore the first (default) row
				if ($idx == 0)
					continue;

				$header = $this->_strip_val($headers[$idx]);
				$op = $this->_strip_val($ops[$idx]);
				$bodypart = $this->_strip_val($bodyparts[$idx]);
				$advop = $this->_strip_val($advops[$idx]);
				$contentpart = $this->_strip_val($contentparts[$idx]);
				$target = $this->_strip_val($targets[$idx]);
				$advtarget = $this->_strip_val($advtargets[$idx]);
				$comparator = $this->_strip_val($comparators[$idx]);

				switch ($type) {
					case 'size':
						$sizeop = $this->_strip_val($sizeops[$idx]);
						$sizeunit = $this->_strip_val($sizeunits[$idx]);

						$script['tests'][$i]['type'] = 'size';
						$script['tests'][$i]['operator'] = $sizeop;
						$script['tests'][$i]['target'] = $target.$sizeunit;
						break;
					case 'body':
						$script['tests'][$i]['bodypart'] = $bodypart;

						if ($bodypart == 'content')
							$script['tests'][$i]['contentpart'] = $contentpart;
						else
							$script['tests'][$i]['contentpart'] = '';
					case 'exists':
					case 'header':
					case 'address':
					case 'envelope':
						if(preg_match('/^not/', $op) || preg_match('/^not/', $advop))
							$script['tests'][$i]['not'] = true;
						else
							$script['tests'][$i]['not'] = '';

						$op = preg_replace('/^not/', '', $op);
						$advop = preg_replace('/^not/', '', $advop);
						$header = preg_match('/[\s,]+/', $header) ? preg_split('/[\s,]+/', $header, -1, PREG_SPLIT_NO_EMPTY) : $header;

						if ($op == 'exists') {
							$script['tests'][$i]['type'] = 'exists';
							$script['tests'][$i]['operator'] = 'exists';
							$script['tests'][$i]['header'] = $header;
						}
						elseif ($op == 'advoptions') {
							$script['tests'][$i]['type'] = $type;
							$script['tests'][$i]['operator'] = $advop;
							$script['tests'][$i]['header'] = $header;
							$script['tests'][$i]['target'] = $advtarget;

							if (substr($advop, 0, 5) == 'count' || substr($advop, 0, 5) == 'value')
								$script['tests'][$i]['comparator'] = $comparator;
							else
								$script['tests'][$i]['comparator'] = '';
						}
						else {
							$script['tests'][$i]['type'] = $type;
							$script['tests'][$i]['operator'] = $op;
							$script['tests'][$i]['header'] = $header;
							$script['tests'][$i]['target'] = $target;
						}
						break;
				}
				$i++;
			}

			// actions
			$i = 0;
			foreach($actions as $idx => $type) {
				// ignore the first (default) row
				if ($idx == 0)
					continue;

				$type = $this->_strip_val($type);

				$script['actions'][$i]['type'] = $type;

				switch ($type) {
					case 'fileinto':
					case 'fileinto_copy':
						$folder = $this->_strip_val($folders[$idx]);
						$rcmail = rcmail::get_instance();
						$rcmail->imap_init(TRUE);
						$script['actions'][$i]['target'] = $this->config['include_imap_root'] ? $rcmail->imap->mod_mailbox($folder) : $folder;
						if (!empty($this->config['folder_delimiter']))
							$script['actions'][$i]['target'] = str_replace($rcmail->imap->get_hierarchy_delimiter(), $this->config['folder_delimiter'], $script['actions'][$i]['target']);
						break;
					case 'redirect':
					case 'redirect_copy':
						$address = $this->_strip_val($addresses[$idx]);
						$script['actions'][$i]['target'] = $address;
						break;
					case 'reject':
					case 'ereject':
						$rejects = $this->_strip_val($rejects[$idx]);
						$script['actions'][$i]['target'] = $rejects;
						break;
					case 'vacation':
						$from = $this->_strip_val($vacfroms[$idx]);
						$to = $this->_strip_val($vactos[$idx]);
						$day = $this->_strip_val($days[$idx]);
						$handle = $this->_strip_val($handles[$idx]);
						$subject = $this->_strip_val($subjects[$idx]);
						$origsubject = $this->_strip_val($origsubjects[$idx]);
						$msg = $this->_strip_val($msgs[$idx]);
						$charset = $this->_strip_val($charsets[$idx]);
						$script['actions'][$i]['days'] = $day;
						$script['actions'][$i]['subject'] = $subject;
						$script['actions'][$i]['origsubject'] = $origsubject;
						$script['actions'][$i]['from'] = $from;
						$script['actions'][$i]['addresses'] = $to;
						$script['actions'][$i]['handle'] = $handle;
						$script['actions'][$i]['msg'] = $msg;
						$script['actions'][$i]['charset'] = $charset;
						break;
					case 'imapflags':
					case 'imap4flags':
						$flag = $this->_strip_val($flags[$idx]);
						$script['actions'][$i]['target'] = $flag;
						break;
					case 'notify':
					case 'enotify':
						$from = $this->_strip_val($nfroms[$idx]);
						$importance = $this->_strip_val($nimpts[$idx]);
						$method = $this->_strip_val($nmethods[$idx]);
						$option = $this->_strip_val($noptions[$idx]);
						$msg = $this->_strip_val($nmsgs[$idx]);
						$script['actions'][$i]['from'] = $from;
						$script['actions'][$i]['importance'] = $importance;
						$script['actions'][$i]['method'] = $method;
						$script['actions'][$i]['options'] = $option;
						$script['actions'][$i]['msg'] = $msg;
						break;
			    }

			    $i++;
			}

			if (!isset($this->script[$fid]))
				$fid = $this->sieve->script->add_rule($script);
			else
				$fid = $this->sieve->script->update_rule($fid, $script);

			if ($fid === true)
				$save = $this->sieve->save();

			if ($save && $fid === true) {
				$this->api->output->command('display_message', $this->gettext('filtersaved'), 'confirmation');
			}
			else {
				if ($fid == SIEVE_ERROR_BAD_ACTION)
					$this->api->output->command('display_message', $this->gettext('filteractionerror'), 'error');
				elseif ($fid == SIEVE_ERROR_NOT_FOUND)
					$this->api->output->command('display_message', $this->gettext('filtermissingerror'), 'error');
				else
					$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');
			}

			// update rule list
			if ($this->sieve_error)
				$this->script = array();
			else
				$this->script = $this->sieve->script->as_array();

			// go to next step
			rcmail_overwrite_action('plugin.sieverules.edit');
			$this->action = 'plugin.sieverules.edit';
			$this->init_html();
		}
	}

	function delete()
	{
		$this->_load_config();
		$this->_startup();

		$result = false;
		$ids = get_input_value('_iid', RCUBE_INPUT_GET);
		if (is_numeric($ids) && isset($this->script[$ids]) && !$this->sieve_error) {
			$result = $this->sieve->script->delete_rule($ids);
			if ($result === true)
				$result = $this->sieve->save();
		}

		if ($result === true)
			$this->api->output->command('display_message', $this->gettext('filterdeleted'), 'confirmation');
		elseif ($result == SIEVE_ERROR_NOT_FOUND)
			$this->api->output->command('display_message', $this->gettext('filtermissingerror'), 'error');
		else
			$this->api->output->command('display_message', $this->gettext('filterdeleteerror'), 'error');

		// update rule list
		if ($this->sieve_error)
			$this->script = array();
		else
			$this->script = $this->sieve->script->as_array();

		// go to sieverules page
		rcmail_overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	function import()
	{
		$this->_load_config();
		$this->_startup();

		$type = get_input_value('_type', RCUBE_INPUT_GET);
		$ruleset = get_input_value('_ruleset', RCUBE_INPUT_GET);
		if ($ruleset == '_default_') {
			if (!empty($this->config['default_file']) && is_readable($this->config['default_file'])) {
				$this->sieve->script->add_text(file_get_contents($this->config['default_file']));
				$save = $this->sieve->save();

				if ($save)
					$this->api->output->command('display_message', $this->gettext('filterimported'), 'confirmation');
				else
					$this->api->output->command('display_message', $this->gettext('filterimporterror'), 'error');

				// update rule list
				if ($this->sieve_error)
					$this->script = array();
				else
					$this->script = $this->sieve->script->as_array();
			}
		} elseif ($ruleset == '_example_') {
			if (get_input_value('_eids', RCUBE_INPUT_GET)) {
				$pos = get_input_value('_pos', RCUBE_INPUT_GET);
				$eids = explode(",", get_input_value('_eids', RCUBE_INPUT_GET));

				if ($pos == 'end')
					$pos = null;
				else
					$pos = substr($pos, 6);

				foreach ($eids as $eid) {
					$this->sieve->script->add_rule($this->examples[substr($eid, 2)], $pos);
					if ($pos) $pos++;
				}

				$this->sieve->save();

				// update rule list
				if ($this->sieve_error)
					$this->script = array();
				else
					$this->script = $this->sieve->script->as_array();
			}
		} elseif ($ruleset == '_none_') {
			$this->sieve->save();
		} elseif ($type != '' && $ruleset != '') {
			$import = $this->sieve->do_import($type, $ruleset);

			if ($import) {
				$this->script = $this->sieve->script->as_array();
				$this->api->output->command('display_message', $this->gettext('filterimported'), 'confirmation');
			}
			else {
				$this->script = array();
				$this->api->output->command('display_message', $this->gettext('filterimporterror'), 'error');
			}
		}

		// go to sieverules page
		rcmail_overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	private function _load_config()
	{
		ob_start();
		include('config.inc.php');
		$this->config = (array)$sieverules_config;
		ob_end_clean();
	}

	private function _startup()
	{
		if (!$this->sieve) {
			include('Net_Sieve.php');
			include('rcube_sieve.php');
			include('rcube_sieve_script.php');
			$rcmail = rcmail::get_instance();

			// try to connect to managesieve server and to fetch the script
			$this->sieve = new rcube_sieve($_SESSION['username'],
						$rcmail->decrypt($_SESSION['password']),
						$this->config['managesieve_host'],
						$this->config['managesieve_port'],
						$this->config['usetls'],
						$this->config['ruleset_name'], $this->home);

			$this->sieve_error = $this->sieve->error();

			if ($this->sieve_error == SIEVE_ERROR_NOT_EXISTS) {
				// load default rule set
				if ((!empty($this->config['default_file']) && is_readable($this->config['default_file'])) || sizeof($this->sieve->list) > 0) {
					rcmail_overwrite_action('plugin.sieverules.setup');
					$this->action = 'plugin.sieverules.setup';
				}

				// that's not exactly an error
				$this->sieve_error = false;
			}
			elseif ($this->sieve_error) {
				switch ($this->sieve_error) {
					case SIEVE_ERROR_CONNECTION:
					case SIEVE_ERROR_LOGIN:
						$this->api->output->command('display_message', $this->gettext('filterconnerror'), 'error');
					break;
					default:
						$this->api->output->command('display_message', $this->gettext('filterunknownerror'), 'error');
					break;
				}

				$this->api->output->set_env('sieveruleserror', true);
			}

			// finally set script objects
			if ($this->sieve_error)
				$this->script = array();
			else
				$this->script = $this->sieve->script->as_array();

			// load example filters
			if (!empty($this->config['example_file']) && is_readable($this->config['example_file']))
				$this->examples = $this->sieve->script->parse_text(file_get_contents($this->config['example_file']));
		}
	}

	private function _rule_row($ext, $rules_table, $rule, $predefined_rules, $attrib) {
		$imgclass = null;

		if (!isset($rule)) {
			$rules_table->set_row_attribs(array('style' => 'display: none;'));
			$imgclass = 'nohtc';
		}

	  	if (in_array('regex', $ext) || in_array('relational', $ext) || in_array('subaddress', $ext))
	  		$this->operators['filteradvoptions'] = 'advoptions';

		$header_style = 'visibility: hidden;';
		$op_style = '';
		$sizeop_style = 'display: none;';
		$target_style = '' ;
		$units_style = 'display: none;';
		$bodypart_style = 'display: none;';
		$advcontentpart_style = 'display: none;';

		$test = 'header';
		$selheader = 'Subject';
		$header = 'Subject';
		$op = 'contains';
		$sizeop = 'under';
		$target = '';
		$target_size = 150;
		$units = 'KB';
		$bodypart = '';
		$advcontentpart = '';

		$predefined = -1;
		foreach($predefined_rules as $idx => $data) {
			if (($data['type'] == $rule['type'] || $rule['type'] == 'exists')
				&& $data['header'] == $rule['header']
				&& $data['operator'] == ($rule['not'] ? 'not' : '') . $rule['operator']
				&& $data['target'] == $rule['target']) {
					$predefined = $idx;
					break;
			}
		}

		if ($predefined > -1) {
			$op_style = 'display: none;';
			$target_style = 'display: none;' ;
			$selheader = $rule['type'] . '::predefined_' . $predefined;
			$test = $rule['type'];

			if ($rule['type'] == 'size') {
				$header = 'size';
				$sizeop = $rule['operator'];
				preg_match('/^([0-9]+)(K|M|G)*$/', $rule['target'], $matches);
				$target = $matches[1];
				$target_size = 100;
				$units = $matches[2];
			}
			elseif ($rule['type'] == 'exists') {
				$selheader = $predefined_rules[$predefined]['type'] . '::predefined_' . $predefined;
				$header = $rule['header'];
				$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
			}
			else {
				$header = $rule['header'];
				$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
				$target = htmlspecialchars($rule['target']);
			}
		}
		elseif ((isset($rule['type']) && $rule['type'] != 'exists') && in_array($rule['type'] . '::' . $rule['header'], $this->headers)) {
			$target_style = $rule['operator'] == 'exists' ? 'display: none;' : '' ;

			$selheader = $rule['type'] . '::' . $rule['header'];
			$test = $rule['type'];
			$header = $rule['header'];
			$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$target = htmlspecialchars($rule['target']);
		}
		elseif ((isset($rule['type']) && $rule['type'] == 'exists') && $this->_in_headerarray($rule['header'], $this->headers) != false) {
			$target_style = $rule['operator'] == 'exists' ? 'display: none;' : '' ;

			$selheader = $this->_in_headerarray($rule['header'], $this->headers) . '::' . $rule['header'];
			$test = $rule['type'];
			$header = $rule['header'];
			$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'size') {
			$op_style = 'display: none;';
			$sizeop_style = '';
			$units_style = '';

			$selheader = 'size::size';
			$header = 'size';
			$test = 'size';
			$sizeop = $rule['operator'];
			preg_match('/^([0-9]+)(K|M|G)*$/', $rule['target'], $matches);
			$target = $matches[1];
			$target_size = 100;
			$units = $matches[2];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'body') {
			$bodypart_style = '';
			$header_style = 'display: none;';

			$selheader = 'body::body';
			$header = 'body';
			$test = 'body';
			$bodypart = $rule['bodypart'];
			$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$target = htmlspecialchars($rule['target']);

			if ($rule['contentpart'] != '') {
				$advcontentpart = $rule['contentpart'];
				$advcontentpart_style = '';
			}
		}
		elseif (isset($rule['type']) && $rule['type'] != 'true') {
			$header_style = '';
			$target_style = $rule['operator'] == 'exists' ? 'display: none;' : '' ;

			$selheader = 'header::other';
			$test = 'header';
			$header = is_array($rule['header']) ? join(', ', $rule['header']) : $rule['header'];
			$op = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$target = htmlspecialchars($rule['target']);
		}

		// check for advanced options
		$showadvanced = false;
		if (!in_array($op, $this->operators) || $rule['comparator'] != '' || $rule['contentpart'] != '') {
			$showadvanced = true;
			$target_style = 'display: none;';
		}

		$select_header = new html_select(array('name' => "_selheader[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_header_select(this)'));
		foreach($this->headers as $name => $val) {
			if (($val == 'envelope' && in_array('envelope', $ext)) || $val != 'envelope')
				$select_header->add(Q($this->gettext($name)), Q($val));
		}

		if (in_array('body', $ext))
			$select_header->add(Q($this->gettext('body')), Q('body::body'));

		foreach($predefined_rules as $idx => $data) {
			if (($data['type'] == 'envelope' && in_array('envelope', $ext)) || $data['type'] != 'envelope')
				$select_header->add(Q($data['name']), Q($data['type'] . '::predefined_' . $idx));
		}

		$select_header->add(Q($this->gettext('size')), Q('size::size'));
		$select_header->add(Q($this->gettext('otherheader')), Q('header::other'));
		$input_test = new html_hiddenfield(array('name' => '_test[]', 'value' => $test));
		$rules_table->add('selheader', $select_header->show($selheader) . $input_test->show());

		$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
		$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. JS_OBJECT_NAME .'.sieverules_xheaders(this);', 'title' => $this->gettext('sieveruleheaders'), 'style' => $header_style), $help_button);

		$input_header = new html_inputfield(array('name' => '_header[]', 'size' => 15, 'style' => $header_style));
		$select_bodypart = new html_select(array('name' => '_bodypart[]', 'onchange' => JS_OBJECT_NAME . '.sieverules_bodypart_select(this)', 'style' => 'width: 113px;' . $bodypart_style));
		$select_bodypart->add(Q($this->gettext('auto')), Q(''));
		$select_bodypart->add(Q($this->gettext('raw')), Q('raw'));
		$select_bodypart->add(Q($this->gettext('text')), Q('text'));
		$select_bodypart->add(Q($this->gettext('other')), Q('content'));
		$rules_table->add('header', $input_header->show($header) . $help_button . $select_bodypart->show($bodypart));

		$select_op = new html_select(array('name' => "_operator[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_rule_op_select(this)', 'style' => $op_style . ' width: 123px;'));
		foreach($this->operators as $name => $val)
			$select_op->add(Q($this->gettext($name)), $val);

		$select_size_op = new html_select(array('name' => "_size_operator[]", 'style' => $sizeop_style . ' width: 123px;'));
		$select_size_op->add(Q($this->gettext('filterunder')), 'under');
		$select_size_op->add(Q($this->gettext('filterover')), 'over');

		if ($showadvanced)
			$rules_table->add('op', $select_op->show('advoptions') . $select_size_op->show($sizeop));
		else
			$rules_table->add('op', $select_op->show($op) . $select_size_op->show($sizeop));

		$input_target = new html_inputfield(array('name' => '_target[]', 'style' => $target_style . ' width: ' . $target_size . 'px'));

		$select_units = new html_select(array('name' => "_units[]", 'style' => $units_style));
		$select_units->add(Q($this->gettext('B')), '');
		$select_units->add(Q($this->gettext('KB')), 'K');
		$select_units->add(Q($this->gettext('MB')), 'M');

		$rules_table->add('target', $input_target->show($target) . "&nbsp;" . $select_units->show($units));

		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_rule', 'type' => 'image', 'image' => $attrib['addicon'], 'alt' => 'sieverules.addsieverule', 'title' => 'sieverules.addsieverule'));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_rule', 'type' => 'image', 'image' => $attrib['deleteicon'], 'alt' => 'sieverules.deletesieverule', 'title' => 'sieverules.deletesieverule'));
		$rules_table->add('control', $add_button . "&nbsp;" . $delete_button);

		if (isset($rule))
			$rowid = $rules_table->size();
		else
			$rowid = 'rowid';

		$headers_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'style' => 'width: 100%;', 'cols' => 4));
		$headers_table->add(array('colspan' => 4, 'style' => 'white-space: normal;'), Q($this->gettext('sieveheadershlp')));
		$headers_table->add_row();

		$col1 = '';
		$col2 = '';
		$col3 = '';
		$col4 = '';
		sort($this->config['other_headers']);
		$col_length = sizeof($this->config['other_headers']) / 4;
		$col_length = ceil($col_length);
		foreach ($this->config['other_headers'] as $idx => $xheader) {
			$input_xheader = new html_radiobutton(array('id' => $xheader . '_' . $rowid, 'name' => '_xheaders_' . $rowid  . '[]', 'value' => $xheader, 'onclick' => JS_OBJECT_NAME . '.sieverules_set_xheader(this)'));
			$xheader_show = $input_xheader->show($header) . "&nbsp;" . html::label($xheader . '_' . $rowid, Q($xheader));

			if ($idx < $col_length)
				$col1 .= $xheader_show . "<br />";
			elseif ($idx < $col_length * 2)
				$col2 .= $xheader_show . "<br />";
	    	elseif ($idx < $col_length * 3)
	    		$col3 .= $xheader_show . "<br />";
	    	elseif ($idx < $col_length * 4)
	    		$col4 .= $xheader_show . "<br />";
		}

		$headers_table->add(array('style' => 'vertical-align: top; width: 25%;'), $col1);
		$headers_table->add(array('style' => 'vertical-align: top; width: 25%;'), $col2);
		$headers_table->add(array('style' => 'vertical-align: top; width: 25%;'), $col3);
		$headers_table->add(array('style' => 'vertical-align: top; width: 25%;'), $col4);

		$rules_table->set_row_attribs(array('style' => 'display: none;'));
		$rules_table->add(array('colspan' => 5), $headers_table->show());
		$rules_table->add_row();

		$advanced_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'style' => 'width: 100%;', 'cols' => 2));
		$advanced_table->add(array('colspan' => 2, 'style' => 'white-space: normal;'), Q($this->gettext('advancedoptions')));
		$advanced_table->add_row();

		$field_id = 'rcmfd_advcontentpart_'. $rowid;
		$advanced_table->set_row_attribs(array('style' => $advcontentpart_style));
		$input_advcontentpart = new html_inputfield(array('id' => $field_id, 'name' => '_body_contentpart[]', 'style' => 'width: 260px'));
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('bodycontentpart'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advcontentpart->show($advcontentpart));

		$field_id = 'rcmfd_advoperator_'. $rowid;
		$select_advop = new html_select(array('id' => $field_id, 'name' => "_advoperator[]", 'style' => 'width: 268px', 'onchange' => JS_OBJECT_NAME . '.sieverules_rule_advop_select(this)'));

	  	if (in_array('regex', $ext)) {
		  	$select_advop->add(Q($this->gettext('filterregex')), 'regex');
		  	$select_advop->add(Q($this->gettext('filternotregex')), 'notregex');
		}

	  	if (in_array('relational', $ext)) {
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('isgreaterthan')), 'count "gt"');
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('isgreaterthanequal')), 'count "ge"');
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('islessthan')), 'count "lt"');
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('islessthanequal')), 'count "le"');
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('equals')), 'count "eq"');
		  	$select_advop->add(Q($this->gettext('count') . ' ' . $this->gettext('notequals')), 'count "ne"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('isgreaterthan')), 'value "gt"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('isgreaterthanequal')), 'value "ge"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('islessthan')), 'value "lt"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('islessthanequal')), 'value "le"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('equals')), 'value "eq"');
		  	$select_advop->add(Q($this->gettext('value') . ' ' . $this->gettext('notequals')), 'value "ne"');
		}

		if (in_array('subaddress', $ext)) {
		  	$select_advop->add(Q($this->gettext('userpart')), 'user');
		  	$select_advop->add(Q($this->gettext('notuserpart')), 'notuser');
		  	$select_advop->add(Q($this->gettext('detailpart')), 'detail');
		  	$select_advop->add(Q($this->gettext('notdetailpart')), 'notdetail');
		  	$select_advop->add(Q($this->gettext('domainpart')), 'domain');
		  	$select_advop->add(Q($this->gettext('notdomainpart')), 'notdomain');
		}

		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('operator'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $select_advop->show($op));

		$field_id = 'rcmfd_comparator_'. $rowid;
		if (substr($op, 0, 5) == 'count' || substr($op, 0, 5) == 'value')
			$select_comparator = new html_select(array('id' => $field_id, 'name' => "_comparator[]", 'style' => 'width: 268px'));
		else
			$select_comparator = new html_select(array('id' => $field_id, 'name' => "_comparator[]", 'style' => 'width: 268px', 'disabled' => 'disabled'));
		$select_comparator->add(Q('i;ascii-casemap'), '');
		$select_comparator->add(Q('i;octet'), 'i;octet');

		foreach ($ext as $extension) {
			if (substr($extension, 0, 11) == 'comparator-')
				$select_comparator->add(Q(substr($extension, 11)), substr($extension, 11));
		}

		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('comparator'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $select_comparator->show($rule['comparator']));

		$field_id = 'rcmfd_advtarget_'. $rowid;
		$input_advtarget = new html_inputfield(array('id' => $field_id, 'name' => '_advtarget[]', 'style' => 'width: 260px'));
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('teststring'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advtarget->show($target));

		if (!($showadvanced && $predefined == -1))
			$rules_table->set_row_attribs(array('style' => 'display: none;'));
		$rules_table->add(array('colspan' => 5), $advanced_table->show());
		$rules_table->add_row();

		return $rules_table;
	}

	private function _action_row($ext, $actions_table, $rowid, $action, $attrib) {
		$rcmail = rcmail::get_instance();
		static $a_mailboxes;
		$imgclass = null;

		if (!isset($action)) {
			$actions_table->set_row_attribs(array('style' => 'display: none;'));
			$imgclass = 'nohtc';
		}

		$help_icon = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('messagehelp'), 'border' => 0));

		$folder_style = '';
		$redirect_style = 'display: none;';
		$reject_style = 'display: none;';
		$vac_style = 'display: none;';
		$imapflags_style = 'display: none;';
		$notify_style = 'display: none;';
		$vacadvstyle = 'display: none;';
		$vacshowadv = '';
		$noteadvstyle = 'display: none;';
		$noteshowadv = '';

		$method = 'fileinto';
		$folder = 'INBOX';
		$reject = '';
		$vacfrom = null;
		$vacto = null;
		$address = '';
		$days = '';
		$handle = '';
		$subject = '';
		$origsubject = '';
		$msg = '';
		$charset = RCMAIL_CHARSET;
		$flags = '';
		$nfrom = '';
		$nimpt = '';
		$nmethod = '';
		$noptions = '';
		$nmsg = '';

		if ($action['type'] == 'fileinto' || $action['type'] == 'fileinto_copy') {
			$method = $action['type'];
			$folder = $this->config['include_imap_root'] ? $rcmail->imap->mod_mailbox($action['target'], 'out') : $action['target'];
			if (!empty($this->config['folder_delimiter']))
				$folder = str_replace($rcmail->imap->get_hierarchy_delimiter(), $this->config['folder_delimiter'], $folder);
		}
		elseif ($action['type'] == 'reject' || $action['type'] == 'ereject') {
			$folder_style = 'display: none;';
			$reject_style = '';

			$method = $action['type'];
			$reject = htmlspecialchars($action['target']);
		}
		elseif ($action['type'] == 'vacation') {
			$folder_style = 'display: none;';
			$vac_style = '';

			$method = 'vacation';
			$days = $action['days'];
			$vacfrom = $action['from'];
			$vacto = $action['addresses'];
			$handle = htmlspecialchars($action['handle']);
			$subject = htmlspecialchars($action['subject']);
			$origsubject = $action['origsubject'];
			$msg = htmlspecialchars($action['msg']);
			$charset = $action['charset'];

			// check advanced enabled
			if (!empty($vacfrom) || !empty($vacto) || !empty($handle) || $charset != RCMAIL_CHARSET) {
				$vacadvstyle = '';
				$vacshowadv = '1';
			}
		}
		elseif ($action['type'] == 'redirect' || $action['type'] == 'redirect_copy') {
			$folder_style = 'display: none;';
			$redirect_style = '';

			$method = $action['type'];
			$address = $action['target'];
		}
		elseif ($action['type'] == 'imapflags' || $action['type'] == 'imap4flags') {
			$folder_style = 'display: none;';
			$imapflags_style = '';

			$method = $action['type'];
			$flags = $action['target'];
		}
		elseif ($action['type'] == 'notify' || $action['type'] == 'enotify') {
			$folder_style = 'display: none;';
			$notify_style = '';

			$method = $action['type'];
			$nfrom = htmlspecialchars($action['from']);
			$nimpt = htmlspecialchars($action['importance']);
			$nmethod = $action['method'];
			$noptions = $action['options'];
			$nmsg = $action['msg'];

			// check advanced enabled
			if (!empty($nfrom) || !empty($nimpt)) {
				$noteadvstyle = '';
				$noteshowadv = '1';
			}
		}
		elseif ($action['type'] == 'discard' || $action['type'] == 'keep' || $action['type'] == 'stop') {
			$folder_style = 'display: none;';
			$method = $action['type'];
		}

		$select_action = new html_select(array('name' => "_act[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_action_select(this)'));

		if (in_array('fileinto', $ext) && $this->config['allowed_actions']['fileinto'])
			$select_action->add(Q($this->gettext('messagemoveto')), 'fileinto');
		if (in_array('fileinto', $ext) && in_array('copy', $ext) && $this->config['allowed_actions']['fileinto'])
			$select_action->add(Q($this->gettext('messagecopyto')), 'fileinto_copy');
		if (in_array('vacation', $ext) && $this->config['allowed_actions']['vacation'])
			$select_action->add(Q($this->gettext('messagevacation')), 'vacation');
		if (in_array('reject', $ext) && $this->config['allowed_actions']['reject'])
			$select_action->add(Q($this->gettext('messagereject')), 'reject');
		elseif (in_array('ereject', $ext) && $this->config['allowed_actions']['reject'])
			$select_action->add(Q($this->gettext('messagereject')), 'ereject');
		if (in_array('imapflags', $ext) && $this->config['allowed_actions']['imapflags'])
			$select_action->add(Q($this->gettext('messageimapflags')), 'imapflags');
		elseif (in_array('imap4flags', $ext) && $this->config['allowed_actions']['imapflags'])
			$select_action->add(Q($this->gettext('messageimapflags')), 'imap4flags');
		if (in_array('notify', $ext) && $this->config['allowed_actions']['notify'])
			$select_action->add(Q($this->gettext('messagenotify')), 'notify');
		elseif (in_array('enotify', $ext) && $this->config['allowed_actions']['notify'])
			$select_action->add(Q($this->gettext('messagenotify')), 'enotify');
		if ($this->config['allowed_actions']['redirect'])
			$select_action->add(Q($this->gettext('messageredirect')), 'redirect');
		if (in_array('copy', $ext) && $this->config['allowed_actions']['redirect'])
			$select_action->add(Q($this->gettext('messageredirectcopy')), 'redirect_copy');
		if ($this->config['allowed_actions']['keep'])
			$select_action->add(Q($this->gettext('messagekeep')), 'keep');
		if ($this->config['allowed_actions']['discard'])
			$select_action->add(Q($this->gettext('messagediscard')), 'discard');
		if ($this->config['allowed_actions']['stop'])
			$select_action->add(Q($this->gettext('messagestop')), 'stop');

		$actions_table->add('action', $select_action->show($method));

		$vacs_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => $vac_style));

		$to_addresses = "";
		$vacto_arr = explode(",", $vacto);
		$user_identities = $rcmail->user->list_identities();
		if (count($user_identities)) {
			$field_id = 'rcmfd_sievevacfrom_'. $rowid;
		    $select_id = new html_select(array('id' => $field_id, 'name' => "_vacfrom[]", 'style' => 'width: 337px'));
			$select_id->add(Q($this->gettext('autodetect')), "");

		    foreach ($user_identities as $sql_arr) {
				$select_id->add($sql_arr['email'], $sql_arr['email']);

				$ffield_id = 'rcmfd_vac_' . $rowid . '_' . $sql_arr['identity_id'];
				$curaddress = in_array($sql_arr['email'], $vacto_arr) ? $sql_arr['email'] : "";
				$input_address = new html_checkbox(array('id' => $ffield_id, 'name' => '_vacto_check_' . $rowid . '[]', 'value' => $sql_arr['email'], 'onclick' => JS_OBJECT_NAME . '.sieverules_toggle_vac_to(this, '. $rowid .')'));
				$to_addresses .= $input_address->show($curaddress) . "&nbsp;" . html::label($ffield_id, Q($sql_arr['email'])) . "<br />";
			}

			$vacs_table->set_row_attribs(array('class' => 'disabled', 'style' => 'display: none')); // 'style' => $vacadvstyle
			$vacs_table->add(null, html::label($field_id, Q($this->gettext('from'))));
			$vacs_table->add(array('colspan' => 2), $select_id->show($vacfrom));
			$vacs_table->add_row();

			$field_id = 'rcmfd_sievevacto_'. $rowid;
			$input_vacto = new html_hiddenfield(array('id' => $field_id, 'name' => '_vacto[]', 'value' => $vacto));
			$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $vacadvstyle));
			$vacs_table->add(array('style' => 'vertical-align: top;'), Q($this->gettext('sieveto')));
			$vacs_table->add(null, $to_addresses . $input_vacto->show());
			$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
			$vacs_table->add(array('style' => 'vertical-align: top;'), $help_button);

			$vacs_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
			$vacs_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('vactoexp'));
			$vacs_table->add_row();
		}

		$field_id = 'rcmfd_sievevacdays_'. $rowid;
		$input_day = new html_inputfield(array('id' => $field_id, 'name' => '_day[]', 'style' => 'width: 310px'));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('days'))));
		$vacs_table->add(null, $input_day->show($days));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		$vacs_table->set_row_attribs(array('style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('vacdaysexp'));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievevachandle_'. $rowid;
		$input_handle = new html_inputfield(array('id' => $field_id, 'name' => '_handle[]', 'style' => 'width: 310px'));
		$vacs_table->set_row_attribs(array('class' => 'disabled', 'style' => 'display: none')); // 'style' =>  $vacadvstyle
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('sievevachandle'))));
		$vacs_table->add(null, $input_handle->show($handle));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		$vacs_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('vachandleexp'));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievevacsubject_'. $rowid;
		$input_subject = new html_inputfield(array('id' => $field_id, 'name' => '_subject[]', 'style' => 'width: 330px'));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('subject'))));
		$field_id = 'rcmfd_sievevacsubject_orig_'. $rowid;
		$input_origsubject = new html_checkbox(array('id' => $field_id, 'value' => '1', 'onclick' => JS_OBJECT_NAME . '.sieverules_toggle_vac_osubj(this, '. $rowid .')'));
		$input_vacosubj = new html_hiddenfield(array('id' => 'rcmfd_sievevactoh_'. $rowid, 'name' => '_orig_subject[]', 'value' => $origsubject));
		$vacs_table->add(array('colspan' => 2), $input_subject->show($subject)); // . "<br />" . $input_origsubject->show($origsubject) . "&nbsp;" . html::label($field_id, Q($this->gettext('sieveorigsubj'))) . $input_vacosubj->show()
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievevacmag_'. $rowid;
		$input_msg = new html_textarea(array('id' => $field_id, 'name' => '_msg[]', 'rows' => '5', 'cols' => '40', 'style' => 'width: 330px'));
		$vacs_table->add('msg', html::label($field_id, Q($this->gettext('message'))));
		$vacs_table->add(array('colspan' => 2), $input_msg->show($msg));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievecharset_'. $rowid;
		$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $vacadvstyle));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('charset'))));
		$vacs_table->add(array('colspan' => 2), $this->_charset_selector(array('id' => $field_id, 'name' => '_charset[]', 'style' => 'width: 337px'), $charset));
		$vacs_table->add_row();

		$input_advopts = new html_checkbox(array('id' => 'vadvopts' . $rowid, 'name' => '_vadv_opts[]', 'onclick' => JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1'));
		$vacs_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('vadvopts' . $rowid, Q($this->gettext('advancedoptions'))) . $input_advopts->show($vacshowadv));

		$notify_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => $notify_style));

		$user_identities = $rcmail->user->list_identities();
		if (count($user_identities)) {
			$field_id = 'rcmfd_sievenotifyfrom_'. $rowid;
		    $select_id = new html_select(array('id' => $field_id, 'name' => "_nfrom[]", 'style' => 'width: 337px'));
			$select_id->add(Q($this->gettext('autodetect')), "");

		    foreach ($user_identities as $sql_arr)
				$select_id->add($sql_arr['email'], $sql_arr['email']);

	 		$notify_table->set_row_attribs(array('class' => 'advanced', 'style' => $noteadvstyle));
 			$notify_table->add(null, html::label($field_id, Q($this->gettext('sievefrom'))));
 			$notify_table->add(array('colspan' => 2), $select_id->show($nfrom));
 			$notify_table->add_row();
		}

		$field_id = 'rcmfd_nmethod_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_nmethod[]', 'style' => 'width: 330px'));
		$notify_table->add(null, html::label($field_id, Q($this->gettext('method'))));
		$notify_table->add(array('colspan' => 2), $input_method->show($nmethod));
		$notify_table->add_row();

		$field_id = 'rcmfd_noption_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_noption[]', 'style' => 'width: 330px'));
		$notify_table->add(null, html::label($field_id, Q($this->gettext('options'))));
		$notify_table->add(array('colspan' => 2), $input_method->show($noptions));
		$notify_table->add_row();

		$notify_table->set_row_attribs(array('style' => 'display: none;'));
		$notify_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('nmethodexp'));
		$notify_table->add_row();

		$field_id = 'rcmfd_nimpt_'. $rowid;
		$input_importance = new html_radiobutton(array('id' => $field_id . '_none', 'name' => '_notify_radio_' . $rowid, 'value' => 'none', 'onclick' => JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')'));
		$importance_show = $input_importance->show($nimpt) . "&nbsp;" . html::label($field_id . '_none', Q($this->gettext('importancen')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_1', 'name' => '_notify_radio_' . $rowid, 'value' => '1', 'onclick' => JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($nimpt) . "&nbsp;" . html::label($field_id . '_1', Q($this->gettext('importance1')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_2', 'name' => '_notify_radio_' . $rowid, 'value' => '2', 'onclick' => JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($nimpt) . "&nbsp;" . html::label($field_id . '_2', Q($this->gettext('importance2')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_3', 'name' => '_notify_radio_' . $rowid, 'value' => '3', 'onclick' => JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($nimpt) . "&nbsp;" . html::label($field_id . '_3', Q($this->gettext('importance3')));
		$input_importance = new html_hiddenfield(array('id' => 'rcmfd_sievenimpt_'. $rowid, 'name' => '_nimpt[]'));

		$notify_table->set_row_attribs(array('class' => 'advanced', 'style' => $noteadvstyle));
		$notify_table->add(null, Q($this->gettext('flag')));
		$notify_table->add(array('colspan' => 2), $importance_show . $input_importance->show($nimpt));
		$notify_table->add_row();

		$field_id = 'rcmfd_nmsg_'. $rowid;
		$input_msg = new html_inputfield(array('id' => $field_id, 'name' => '_nmsg[]', 'style' => 'width: 330px'));
		$notify_table->add(null, html::label($field_id, Q($this->gettext('message'))));
		$notify_table->add(array('colspan' => 2), $input_msg->show($nmsg));
		$notify_table->add_row();

		if (in_array('enotify', $ext)) {
 			$input_advopts = new html_checkbox(array('id' => 'nadvopts' . $rowid, 'name' => '_nadv_opts[]', 'onclick' => JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1'));
 			$notify_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('nadvopts' . $rowid, Q($this->gettext('advancedoptions'))) . $input_advopts->show($noteshowadv));
		}

		// get mailbox list
		$mbox_name = $rcmail->imap->get_mailbox_name();

		// build the folders tree
		if (empty($a_mailboxes)) {
			// get mailbox list
			$a_folders = $rcmail->imap->list_mailboxes();
			$delimiter = $rcmail->imap->get_hierarchy_delimiter();
			$a_mailboxes = array();

			foreach ($a_folders as $ifolder) {
				if (!empty($this->config['folder_delimiter']))
					rcmail_build_folder_tree($a_mailboxes, str_replace($delimiter, $this->config['folder_delimiter'], $ifolder), $this->config['folder_delimiter']);
				else
					rcmail_build_folder_tree($a_mailboxes, $ifolder, $delimiter);
			}
		}

		$input_folderlist = new html_select(array('name' => '_folder[]', 'style' => $folder_style . ' width: 398px;'));
		rcmail_render_folder_tree_select($a_mailboxes, $mbox_name, 100, $input_folderlist, false);

		$input_address = new html_inputfield(array('name' => '_redirect[]', 'style' => $redirect_style . ' width: 391px;'));
		$input_reject = new html_textarea(array('name' => '_reject[]', 'rows' => '5', 'cols' => '40', 'style' => $reject_style . ' width: 391px;'));
		$input_imapflags = new html_select(array('name' => '_imapflags[]', 'style' => $imapflags_style . ' width: 398px;'));
		foreach($this->flags as $name => $val)
			$input_imapflags->add(Q($this->gettext($name)), Q($val));

		$actions_table->add('folder', $input_folderlist->show($folder) . $input_address->show($address) . $vacs_table->show() . $notify_table->show() . $input_imapflags->show($flags) . $input_reject->show($reject));

		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_action', 'type' => 'image', 'image' => $attrib['addicon'], 'alt' => 'sieverules.addsieveact', 'title' => 'sieverules.addsieveact'));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_action', 'type' => 'image', 'image' => $attrib['deleteicon'], 'alt' => 'sieverules.deletesieveact', 'title' => 'sieverules.deletesieveact'));

		if ($this->config['multiple_actions'])
			$actions_table->add('control', $add_button . "&nbsp;" . $delete_button);
		else
			$actions_table->add('control', "&nbsp;");

		return $actions_table;
	}

	private function _in_headerarray($needle, $haystack) {
		foreach ($haystack as $data) {
			$args = explode("::", $data);
			if ($args[1] == $needle)
				return $args[0];
		}

		return false;
	}

	// coppied from rcube_template.php
	private function _charset_selector($attrib, $charset) {
		$charsets = array(
			'US-ASCII'     => 'ASCII (English)',
			'EUC-JP'       => 'EUC-JP (Japanese)',
			'EUC-KR'       => 'EUC-KR (Korean)',
			'BIG5'         => 'BIG5 (Chinese)',
			'GB2312'       => 'GB2312 (Chinese)',
			'ISO-2022-JP'  => 'ISO-2022-JP (Japanese)',
			'ISO-8859-1'   => 'ISO-8859-1 (Latin-1)',
			'ISO-8859-2'   => 'ISO-8895-2 (Central European)',
			'ISO-8859-7'   => 'ISO-8859-7 (Greek)',
			'ISO-8859-9'   => 'ISO-8859-9 (Turkish)',
			'Windows-1251' => 'Windows-1251 (Cyrillic)',
			'Windows-1252' => 'Windows-1252 (Western)',
			'Windows-1255' => 'Windows-1255 (Hebrew)',
			'Windows-1256' => 'Windows-1256 (Arabic)',
			'Windows-1257' => 'Windows-1257 (Baltic)',
			'UTF-8'        => 'UTF-8'
			);

		$select = new html_select($attrib);
		$select->add(array_values($charsets), array_keys($charsets));

		return $select->show($charset);
	}

	private function _strip_val($str) {
		return trim(htmlspecialchars_decode($str));
	}
}

?>