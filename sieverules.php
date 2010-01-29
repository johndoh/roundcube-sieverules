<?php

/**
 * SieveRules
 *
 * Plugin to allow the user to manage their Sieve filters using the managesieve protocol
 *
 * @version 1.6
 * @author Philip Weir
 * @url http://roundcube.net/plugins/sieverules
 */
class sieverules extends rcube_plugin
{
	public $task = 'settings';
	private $sieve;
	private $sieve_error;
	private $script;
	private $action;
	private $examples = array();
	private $force_vacto = false;
	private $current_ruleset;

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
		$rcmail = rcmail::get_instance();
		$this->load_config();

		if ($rcmail->config->get('sieverules_multiplerules') && get_input_value('_ruleset', RCUBE_INPUT_GET))
			$this->current_ruleset = get_input_value('_ruleset', RCUBE_INPUT_GET);
		elseif ($rcmail->config->get('sieverules_multiplerules') && $_SESSION['sieverules_current_ruleset'])
			$this->current_ruleset = $_SESSION['sieverules_current_ruleset'];
		elseif ($rcmail->config->get('sieverules_multiplerules'))
			$this->current_ruleset = false;
		else
			$this->current_ruleset = $rcmail->config->get('sieverules_ruleset_name');

		// override default values
		if ($rcmail->config->get('sieverules_default_headers'))
			$this->headers = $rcmail->config->get('sieverules_default_headers');

		if ($rcmail->config->get('sieverules_default_operators'))
			$this->operators = $rcmail->config->get('sieverules_default_operators');

		if ($rcmail->config->get('sieverules_default_flags'))
			$this->flags = $rcmail->config->get('sieverules_default_flags');

		$this->action = $rcmail->action;

		$this->add_texts('localization/', array('filters'));
		$this->include_script('sieverules.js');

		$this->register_action('plugin.sieverules', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.add', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.edit', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.setup', array($this, 'init_setup'));
		$this->register_action('plugin.sieverules.advanced', array($this, 'init_html'));
		$this->register_action('plugin.sieverules.move', array($this, 'move'));
		$this->register_action('plugin.sieverules.save', array($this, 'save'));
		$this->register_action('plugin.sieverules.delete', array($this, 'delete'));
		$this->register_action('plugin.sieverules.import', array($this, 'import'));
		$this->register_action('plugin.sieverules.update_list', array($this, 'gen_js_list'));
		$this->register_action('plugin.sieverules.del_ruleset', array($this, 'delete_ruleset'));
		$this->register_action('plugin.sieverules.rename_ruleset', array($this, 'rename_ruleset'));
		$this->register_action('plugin.sieverules.enable_ruleset', array($this, 'enable_ruleset'));
		$this->register_action('plugin.sieverules.copy_filter', array($this, 'copy_filter'));
	}

	function init_html()
	{
		$rcmail = rcmail::get_instance();

		// always include all identities when creating vacation messages
		if ($rcmail->config->get('sieverules_force_vacto'))
			$this->force_vacto = $rcmail->config->get('sieverules_force_vacto');

		$this->_startup();

		if ($rcmail->config->get('sieverules_multiplerules') && $this->current_ruleset === false) {
			if ($ruleset = $this->sieve->get_active()) {
				$this->current_ruleset = $this->sieve->get_active();
			}
			else {
				$this->current_ruleset = $rcmail->config->get('sieverules_ruleset_name');
				$this->_startup();
				rcmail_overwrite_action('plugin.sieverules.setup');
				$this->action = 'plugin.sieverules.setup';
			}
		}

		if ($rcmail->config->get('sieverules_multiplerules'))
			$_SESSION['sieverules_current_ruleset'] = $this->current_ruleset;

		$this->api->output->set_env('ruleset', $this->current_ruleset);
		if ($rcmail->config->get('sieverules_adveditor') == 2 && get_input_value('_override', RCUBE_INPUT_GET) != '1' && $this->action == 'plugin.sieverules') {
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
		'rulelist' => array($this, 'gen_rulelist'),
		'sieverulesframe' => array($this, 'sieverules_frame'),
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
			$this->api->output->add_script(JS_OBJECT_NAME .".add_onload('". JS_OBJECT_NAME .".sieverules_load_setup()');");
			$this->api->output->send('sieverules.sieverules');
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

	function init_setup()
	{
		$this->_startup();

		$this->api->output->add_handlers(array(
		'sieverulessetup' => array($this, 'gen_setup'),
		));

		$this->api->output->set_pagetitle($this->gettext('filters'));
		$this->api->output->send('sieverules.setupsieverules');
	}

	function sieverules_frame($attrib)
	{
		if (!$attrib['id'])
			$attrib['id'] = 'rcmprefsframe';

		$attrib['name'] = $attrib['id'];

		$this->api->output->set_env('contentframe', $attrib['name']);
		$this->api->output->set_env('blankpage', $attrib['src'] ? $this->api->output->abs_url($attrib['src']) : 'program/blank.gif');

		return html::iframe($attrib);
	}

	function gen_advanced($attrib)
	{
		list($form_start, $form_end) = get_form_tags($attrib, 'plugin.sieverules.save');
		$out = $form_start;

		$input_script = new html_textarea(array('id' => 'sieverules_adv', 'name' => '_script'));
		$out .= $input_script->show(htmlspecialchars($this->sieve->script->raw));

		$out .= $form_end;

		return $out;
	}

	function gen_list($attrib)
	{
		$this->api->output->add_label('sieverules.movingfilter', 'loading');
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
			$up_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $attrib['upicon'], 'alt' => 'sieverules.moveup', 'title' => 'sieverules.moveup'));
			$dst = $idx + 2;
			$down_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $attrib['downicon'], 'alt' => 'sieverules.movedown', 'title' => 'sieverules.movedown'));

			// set imagesin sesstion for JS refresh
			$_SESSION['sieverules_upicon'] = $attrib['upicon'];
			$_SESSION['sieverules_downicon'] = $attrib['downicon'];

			$table->add('control', $up_link . '&nbsp;' . $down_link);
		}

		return html::tag('div', array('id' => 'sieverules-list-filters'), $table->show());
	}

	function gen_js_list()
	{
		$this->_startup();

		if (sizeof($this->script) == 0) {
			$this->api->output->command('sieverules_update_list', 'add-first', -1, rep_specialchars_output($this->gettext('nosieverules')));
		}
		else foreach($this->script as $idx => $filter) {
			if ($filter['disabled'] == 1)
				$filter_name = $filter['name'] . ' (' . $this->gettext('disabled') . ')';
			else
				$filter_name = $filter['name'];

			$tmp_output = new rcube_template('settings');
			$dst = $idx - 1;
			$up_link = $tmp_output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $_SESSION['sieverules_upicon'], 'alt' => 'sieverules.moveup', 'title' => 'sieverules.moveup'));
			$up_link = str_replace("'", "\'", $up_link);
			$dst = $idx + 2;
			$down_link = $tmp_output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $_SESSION['sieverules_downicon'], 'alt' => 'sieverules.movedown', 'title' => 'sieverules.movedown'));
			$down_link = str_replace("'", "\'", $down_link);

			$this->api->output->command('sieverules_update_list', $idx == 0 ? 'add-first' : 'add', 'rcmrow' . $idx, $filter_name, $up_link, $down_link);
		}

		$this->api->output->send();
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
		$this->api->output->add_label('sieverules.switchtoadveditor');
		$input_adv = new html_checkbox(array('id' => 'adveditor', 'onclick' => JS_OBJECT_NAME . '.sieverules_adveditor(this);', 'value' => '1'));
		$out = html::label('adveditor', Q($this->gettext('adveditor'))) . $input_adv->show($this->action == 'plugin.sieverules.advanced' ? '1' : '');
		return html::tag('div', array('id' => 'advancedmode'), $out);
	}

	function gen_rulelist($attrib)
	{
		$this->api->output->add_label('sieverules.delrulesetconf', 'sieverules.rulesetexists');

		$rulesets = array();
		foreach ($this->sieve->list as $ruleset) {
			array_push($rulesets, $ruleset);
		}
		sort($rulesets);
		$activeruleset = $this->sieve->get_active();

		// new/rename ruleset dialog
		$out = '';
		$table = new html_table(array('cols' => 2));
		$table->set_row_attribs(array('id' => 'sieverulesrsdialog_input'));
		$table->add('title', html::label('sieverulesrsdialog_name', Q($this->gettext('name'))));
		$table->add(null, html::tag('input', array('type' => 'text', 'id' => 'sieverulesrsdialog_name', 'name' => '_name', 'value' => '')));

		$select_ruleset = new html_select(array('id' => 'sieverulesrsdialog_ruleset'));
		if (sizeof($this->sieve->list) == 1) {
			$select_ruleset->add(Q($this->gettext('nosieverulesets')), '');
		}
		else foreach ($rulesets as $ruleset) {
			if ($ruleset !== $this->current_ruleset)
				$select_ruleset->add(Q($ruleset), Q($ruleset));
		}

		$table->set_row_attribs(array('id' => 'sieverulesrsdialog_select'));
		$table->add('title', html::label('sieverulesrsdialog_ruleset', Q($this->gettext('selectruleset'))));
		$table->add(null, $select_ruleset->show());

		$buttons = html::tag('input', array('type' => 'hidden', 'id' => 'sieverulesrsdialog_action', 'value' => ''));
		$buttons .= html::tag('input', array('type' => 'button', 'class' => 'button mainaction', 'value' => $this->gettext('save'), 'onclick' => JS_OBJECT_NAME . '.sieverulesdialog_submit();')) . '&nbsp;';
		$buttons .= html::tag('input', array('type' => 'button', 'class' => 'button', 'value' => $this->gettext('cancel'), 'onclick' => 'rcube_find_object(\'sieverulesrsdialog\').style.display = \'none\';'));

		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_add'), Q($this->gettext('newruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_edit', 'style' => 'display: none;'), Q($this->gettext('renameruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_copyto', 'style' => 'display: none;'), Q($this->gettext('copytoruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_copyfrom', 'style' => 'display: none;'), Q($this->gettext('copyfromruleset')));
		$out .= $table->show();
		$out .= html::p(array('class' => 'formbuttons'), $buttons);
		$out = html::tag('form', array(), $out);
		$out = html::div(array('id' => 'sieverulesrsdialog', 'style' => 'display: none;'), $out);

		// add overlay input box to html page
		$this->api->output->add_footer($out);
		$action = ($this->action == 'plugin.sieverules.advanced') ? 'plugin.sieverules.advanced' : 'plugin.sieverules';
		$select_ruleset = new html_select(array('id' => 'rulelist', 'onchange' => JS_OBJECT_NAME . '.sieverules_select_ruleset(this, \''. $action .'\');'));

		if (sizeof($this->sieve->list) == 0) {
			$select_ruleset->add(Q($this->gettext('nosieverulesets')), '');
		}
		else foreach ($rulesets as $ruleset) {
			if ($ruleset === $activeruleset)
				$select_ruleset->add(Q(str_replace('%s', $ruleset, $this->gettext('activeruleset'))), Q($ruleset));
			else
				$select_ruleset->add(Q($ruleset), Q($ruleset));
		}

		return html::label('rulelist', Q($this->gettext('selectruleset'))) . $select_ruleset->show($this->current_ruleset);
	}

	function gen_setup()
	{
		$rcmail = rcmail::get_instance();
		$text = '';
		$buttons = '';

		if ($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) {
			$text .= "<br /><br />" . $this->gettext('importdefault');
			$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_import=_default_', 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.usedefaultfilter'));
		}

		$type = '';
		$ruleset = '';
		if (sizeof($this->sieve->list) > 1) {
			if ($result = $this->sieve->check_import()) {
				list($type, $name, $ruleset) = $result;
				$text .= "<br /><br />" . str_replace('%s', $name, $this->gettext('importother'));
				$buttons .= (strlen($buttons) > 0) ? '&nbsp;&nbsp;' : '';
				$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_type=' . $type . '&_import=' . $ruleset, 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.importfilter'));
			}

			if ($rcmail->config->get('sieverules_multiplerules', false)) {
				$text .= "<br /><br />" . $this->gettext('copyexisting');
				$buttons .= (strlen($buttons) > 0) ? '&nbsp;&nbsp;' : '';
				$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.ruleset_dialog_setup', 'prop' => 'copyfrom_ruleset', 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.copyexistingfilter'));
			}
		}

		if ($rcmail->config->get('sieverules_auto_load_default') && is_readable($rcmail->config->get('sieverules_default_file')) && strlen($text) > 0 && strlen($buttons) > 0 && $type == '' && $ruleset == '') {
			$this->sieve->script->add_text(file_get_contents($rcmail->config->get('sieverules_default_file')));
			$this->sieve->save();
			if (!$rcmail->config->get('sieverules_multiplerules', false)) $this->sieve->set_active($this->current_ruleset);

			// update rule list
			if ($this->sieve_error)
				$this->script = array();
			else
				$this->script = $this->sieve->script->as_array();

			if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
				$this->api->output->add_script("parent.location.reload();");
			}
			else {
				// go to sieverules page
				rcmail_overwrite_action('plugin.sieverules');
				$this->api->output->send('sieverules.sieverules');
			}
		}
		else if (strlen($text) > 0 && strlen($buttons) > 0) {
			$out = "<br />". $this->gettext('noexistingfilters') . $text . "<br /><br /><br />\n";
			$out .= $buttons;
			$out .= "&nbsp;&nbsp;" . $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_import=_none_', 'type' => 'input', 'class' => 'button', 'label' => 'cancel'));

			$out = html::tag('p', array('style' => 'text-align: center; padding: 10px;'), "\n" . $out);
			$out = html::tag('div', array('id' => 'prefs-title', 'class' => 'boxtitle'), Q($this->gettext('importfilters'))) . $out;

			return $out;
		}
		else {
			$this->sieve->save();
			if (!$rcmail->config->get('sieverules_multiplerules', false)) $this->sieve->set_active($this->current_ruleset);

			if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
				$this->api->output->add_script("parent.location.reload();");
			}
			else {
				// go to sieverules page
				rcmail_overwrite_action('plugin.sieverules');
				$this->api->output->send('sieverules.sieverules');
			}
		}
	}

	function gen_form($attrib)
	{
		$rcmail = rcmail::get_instance();
		$this->api->output->add_label(
			'sieverules.norulename', 'sieverules.ruleexists', 'sieverules.noheader',
			'sieverules.headerbadchars', 'sieverules.noheadervalue', 'sieverules.sizewrongformat',
			'sieverules.noredirect', 'sieverules.redirectaddresserror', 'sieverules.noreject', 'sieverules.vacnodays',
			'sieverules.vacdayswrongformat', 'sieverules.vacnomsg', 'sieverules.notifynomethod',
			'sieverules.notifynomsg', 'sieverules.filterdeleteconfirm', 'sieverules.ruledeleteconfirm',
			'sieverules.actiondeleteconfirm', 'sieverules.notifyinvalidmethod', 'sieverules.nobodycontentpart',
			'sieverules.badoperator');

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

			if (isset($this->script[$iid])) {
				$this->api->output->add_script("if (parent.". JS_OBJECT_NAME .".sieverules_examples) parent.". JS_OBJECT_NAME .".sieverules_examples.clear_selection();");
				$this->api->output->add_script("parent.". JS_OBJECT_NAME .".sieverules_list.highlight_row(".$iid.");");
			}
		}

		if (sizeof($rcmail->config->get('sieverules_predefined_rules')) > 0) {
			$predefined = array();
			foreach($rcmail->config->get('sieverules_predefined_rules') as $idx => $data)
				array_push($predefined, array($data['type'], $data['header'], $data['operator'], $data['extra'], $data['target']));

			$this->api->output->set_env('predefined_rules', $predefined);
		}

		list($form_start, $form_end) = get_form_tags($attrib, 'plugin.sieverules.save');

		$out = $form_start;

		$hidden_iid = new html_hiddenfield(array('name' => '_iid', 'value' => $iid));
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
		$rules_table = $this->_rule_row($ext, $rules_table, null, $rcmail->config->get('sieverules_predefined_rules'), $attrib);

		if (!$join_any) {
			if (!isset($cur_script))
				$rules_table = $this->_rule_row($ext, $rules_table, array(), $rcmail->config->get('sieverules_predefined_rules'), $attrib);
			else foreach ($cur_script['tests'] as $rules)
				$rules_table = $this->_rule_row($ext, $rules_table, $rules, $rcmail->config->get('sieverules_predefined_rules'), $attrib);
		}

		$out .= html::tag('fieldset', null, html::tag('legend', null, Q($this->gettext('messagesrules')))
				 . Q((!$rcmail->config->get('sieverules_use_elsif', true)) ? $this->gettext('sieveruleexp_stop'): $this->gettext('sieveruleexp')) . "<br /><br />"
				 . $join_type . "<br /><br />"
				 . $rules_table->show());

		rcmail::get_instance()->imap_init(TRUE);
		$actions_table = new html_table(array('id' => 'actions-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$actions_table = $this->_action_row($ext, $actions_table, 'rowid', null, $attrib, $example);

		if (!isset($cur_script))
			$actions_table = $this->_action_row($ext, $actions_table, 0, array(), $attrib, $example);
		else foreach ($cur_script['actions'] as $idx => $actions)
			$actions_table = $this->_action_row($ext, $actions_table, $idx, $actions, $attrib, $example);

		$out .= html::tag('fieldset', null, html::tag('legend', null, Q($this->gettext('messagesactions')))
				. Q($this->gettext('sieveactexp')). "<br /><br />"
				. $actions_table->show());

		$out .= $form_end;

		return $out;
	}

	function move()
	{
		$this->_startup();

		$src = get_input_value('_src', RCUBE_INPUT_GET);
		$dst = get_input_value('_dst', RCUBE_INPUT_GET);

		$result = $this->sieve->script->move_rule($src, $dst);
		$result = $this->sieve->save();

		if ($result)
			$this->api->output->command('sieverules_update_list', 'move', $src , $dst);
		else
			$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');

		$this->api->output->send();
	}

	function save()
	{
		$rcmail = rcmail::get_instance();
		$this->_startup();

		$script = trim(get_input_value('_script', RCUBE_INPUT_POST, true));
		if ($script != '' && ($rcmail->config->get('sieverules_adveditor') == 1 || $rcmail->config->get('sieverules_adveditor') == 2)) {
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
			$iid = trim(get_input_value('_iid', RCUBE_INPUT_POST));
			$join = trim(get_input_value('_join', RCUBE_INPUT_POST));
			$disabled = trim(get_input_value('_disable', RCUBE_INPUT_POST));

			$tests = $_POST['_test'];
			$headers = $_POST['_header'];
			$bodyparts = $_POST['_bodypart'];
			$ops = $_POST['_operator'];
			$sizeops = $_POST['_size_operator'];
			$spamtestops = $_POST['_spamtest_operator'];
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
					case 'spamtest':
						$spamtestop = $this->_strip_val($spamtestops[$idx]);

						$script['tests'][$i]['type'] = 'spamtest';
						$script['tests'][$i]['operator'] = $spamtestop;
						$script['tests'][$i]['target'] = $target;
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
						$script['actions'][$i]['target'] = $rcmail->config->get('sieverules_include_imap_root') ? $rcmail->imap->mod_mailbox($folder) : $folder;
						if ($rcmail->config->get('sieverules_folder_delimiter', false))
							$script['actions'][$i]['target'] = str_replace($rcmail->imap->get_hierarchy_delimiter(), $rcmail->config->get('sieverules_folder_delimiter'), $script['actions'][$i]['target']);
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

			if (!isset($this->script[$iid]))
				$result = $this->sieve->script->add_rule($script);
			else
				$result = $this->sieve->script->update_rule($iid, $script);

			if ($result === true)
				$save = $this->sieve->save();

			if ($save && $result === true && !$rcmail->config->get('sieverules_multiplerules', false))
				$save = $this->sieve->set_active($this->current_ruleset);

			if ($save && $result === true) {
				$this->api->output->command('display_message', $this->gettext('filtersaved'), 'confirmation');

				if ($script['disabled'] == 1)
					$filter_name = $script['name'] . ' (' . $this->gettext('disabled') . ')';
				else
					$filter_name = $script['name'];

				$dst = $iid - 1;
				$up_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $_SESSION['sieverules_upicon'], 'alt' => 'sieverules.moveup', 'title' => 'sieverules.moveup'));
				$up_link = str_replace("'", "\'", $up_link);
				$dst = $iid + 2;
				$down_link = $this->api->output->button(array('command' => 'plugin.sieverules.move', 'prop' => $dst, 'type' => 'image', 'image' => $_SESSION['sieverules_downicon'], 'alt' => 'sieverules.movedown', 'title' => 'sieverules.movedown'));
				$down_link = str_replace("'", "\'", $down_link);

				if (!isset($this->script[$iid]) && sizeof($this->script) == 0)
					$this->api->output->add_script("parent.". JS_OBJECT_NAME .".sieverules_update_list('add-first', 'rcmrow". $iid ."', '". $filter_name ."', '". $up_link ."', '". $down_link ."');");
				elseif (!isset($this->script[$iid]))
					$this->api->output->add_script("parent.". JS_OBJECT_NAME .".sieverules_update_list('add', 'rcmrow". $iid ."', '". $filter_name ."', '". $up_link ."', '". $down_link ."');");
				else
					$this->api->output->add_script("parent.". JS_OBJECT_NAME .".sieverules_update_list('update', ". $iid .", '". $filter_name ."');");
			}
			else {
				if ($result == SIEVE_ERROR_BAD_ACTION)
					$this->api->output->command('display_message', $this->gettext('filteractionerror'), 'error');
				elseif ($result == SIEVE_ERROR_NOT_FOUND)
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
		$this->_startup();

		$result = false;
		$ids = get_input_value('_iid', RCUBE_INPUT_GET);
		if (is_numeric($ids) && isset($this->script[$ids]) && !$this->sieve_error) {
			$result = $this->sieve->script->delete_rule($ids);
			if ($result === true)
				$result = $this->sieve->save();
		}

		if ($result === true) {
			$this->api->output->command('display_message', $this->gettext('filterdeleted'), 'confirmation');
			$this->api->output->add_script("parent.". JS_OBJECT_NAME .".sieverules_update_list('delete', ". $ids .");");
		}
		elseif ($result == SIEVE_ERROR_NOT_FOUND)
			$this->api->output->command('display_message', $this->gettext('filtermissingerror'), 'error');
		else
			$this->api->output->command('display_message', $this->gettext('filterdeleteerror'), 'error');

		// update rule list
		if ($this->sieve_error)
			$this->script = array();
		else
			$this->script = $this->sieve->script->as_array();

		if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
			$this->api->output->add_script("parent.". JS_OBJECT_NAME .".show_contentframe(false);");
		}
		else {
			// go to sieverules page
			rcmail_overwrite_action('plugin.sieverules.edit');
			$this->action = 'plugin.sieverules.edit';
			$this->init_html();
		}
	}

	function import()
	{
		$rcmail = rcmail::get_instance();
		$this->_startup();

		$type = get_input_value('_type', RCUBE_INPUT_GET);
		$ruleset = get_input_value('_import', RCUBE_INPUT_GET);
		if ($ruleset == '_default_') {
			if ($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) {
				$this->sieve->script->add_text(file_get_contents($rcmail->config->get('sieverules_default_file')));
				$save = $this->sieve->save();

				if ($save && !$rcmail->config->get('sieverules_multiplerules', false))
					$save = $this->sieve->set_active($this->current_ruleset);

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
				if (!$rcmail->config->get('sieverules_multiplerules', false)) $this->sieve->set_active($this->current_ruleset);

				// update rule list
				if ($this->sieve_error)
					$this->script = array();
				else
					$this->script = $this->sieve->script->as_array();
			}
		} elseif ($ruleset == '_none_') {
			$this->sieve->save();
			if (!$rcmail->config->get('sieverules_multiplerules', false)) $this->sieve->set_active($this->current_ruleset);
		} elseif ($ruleset == '_copy_') {
			$this->rename_ruleset(true);
			return;
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

	function delete_ruleset()
	{
		$this->_startup();
		$this->sieve->del_script($this->current_ruleset);

		$this->current_ruleset = get_input_value('_next', RCUBE_INPUT_GET);

		rcmail_overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	function rename_ruleset($makeCopy = false)
	{
		$this->_startup();
		$script = $this->sieve->script->as_text();
		$active = $this->sieve->get_active() == $this->current_ruleset ? true : false;
		if (!$makeCopy) $this->sieve->del_script($this->current_ruleset);

		$this->current_ruleset = get_input_value('_new', RCUBE_INPUT_GET);
		$this->sieve->set_ruleset($this->current_ruleset);
		$this->sieve->save($script);
		if ($active) $this->sieve->set_active($this->current_ruleset);

		rcmail_overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	function enable_ruleset()
	{
		$this->_startup();
		$activeruleset = get_input_value('_ruleset', RCUBE_INPUT_GET);
		$this->sieve->set_active($activeruleset);

		$rulesets = array();
		foreach ($this->sieve->list as $ruleset) {
			array_push($rulesets, $ruleset);
		}
		sort($rulesets);

		foreach ($rulesets as $ruleset) {
			if ($ruleset === $activeruleset)
				$this->api->output->command('sieverules_add_ruleset', Q($ruleset), Q(str_replace('%s', $ruleset, $this->gettext('activeruleset'))));
			else
				$this->api->output->command('sieverules_add_ruleset', Q($ruleset), Q($ruleset));
		}

		$this->api->output->send();
	}

	function copy_filter()
	{
		$this->_startup();
		$script = $this->script[get_input_value('_iid', RCUBE_INPUT_GET)];
		$this->current_ruleset = get_input_value('_dest', RCUBE_INPUT_GET);
		$this->_startup();
		$this->sieve->script->add_rule($script);
		$this->sieve->save();

		$this->api->output->command('display_message', $this->gettext('filtercopied'), 'confirmation');
		$this->api->output->send();
	}

	private function _startup()
	{
		$rcmail = rcmail::get_instance();

		if (!$this->sieve) {
			include('Net_Sieve.php');
			include('rcube_sieve.php');
			include('rcube_sieve_script.php');
			$rcmail = rcmail::get_instance();

			// try to connect to managesieve server and to fetch the script
			$this->sieve = new rcube_sieve($_SESSION['username'],
						$rcmail->decrypt($_SESSION['password']),
						str_replace('%h', $_SESSION['imap_host'], $rcmail->config->get('sieverules_host')),
						$rcmail->config->get('sieverules_port'), $rcmail->config->get('sieverules_usetls'),
						$this->current_ruleset, $this->home, $rcmail->config->get('sieverules_use_elsif', true));

			$this->sieve_error = $this->sieve->error();

			if ($this->sieve_error == SIEVE_ERROR_NOT_EXISTS) {
				// load default rule set
				if (($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) || sizeof($this->sieve->list) > 0) {
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
			if ($rcmail->config->get('sieverules_example_file', false) && is_readable($rcmail->config->get('sieverules_example_file')))
				$this->examples = $this->sieve->script->parse_text(file_get_contents($rcmail->config->get('sieverules_example_file')));
		}
		else {
			$this->sieve->set_ruleset($this->current_ruleset);
			$this->script = $this->sieve->script->as_array();
		}
	}

	private function _rule_row($ext, $rules_table, $rule, $predefined_rules, $attrib)
	{
		$rcmail = rcmail::get_instance();
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
		$spamtestop_style = 'display: none;';
		$target_style = '';
		$units_style = 'display: none;';
		$bodypart_style = 'display: none;';
		$advcontentpart_style = 'display: none;';
		$spam_prob_style = 'display: none;';

		$test = 'header';
		$selheader = 'Subject';
		$header = 'Subject';
		$op = 'contains';
		$sizeop = 'under';
		$spamtestop = 'ge';
		$target = '';
		$target_size = '';
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
				$target_size = 'short';
				$units = $matches[2];
			}
			elseif ($rule['type'] == 'spamtest') {
				$header = 'spamtest';
				$spamtestop = $rule['operator'];
				$target = $rule['target'];
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
			$target_size = 'short';
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
		elseif (isset($rule['type']) && $rule['type'] == 'spamtest') {
			$op_style = 'display: none;';
			$target_style = 'display: none;' ;
			$spamtestop_style = '';
			$spam_prob_style = '';

			$test = $rule['type'];
			$selheader = 'spamtest::spamtest';
			$header = 'spamtest';
			$spamtestop = $rule['operator'];
			$target = $rule['target'];
			$spam_probability = $rule['target'];
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

		if (in_array('spamtest', $ext))
			$select_header->add(Q($this->gettext('spamtest')), Q('spamtest::spamtest'));

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

		$input_header = new html_inputfield(array('name' => '_header[]', 'style' => $header_style, 'class' => 'short'));
		$select_bodypart = new html_select(array('name' => '_bodypart[]', 'onchange' => JS_OBJECT_NAME . '.sieverules_bodypart_select(this)', 'style' => $bodypart_style));
		$select_bodypart->add(Q($this->gettext('auto')), Q(''));
		$select_bodypart->add(Q($this->gettext('raw')), Q('raw'));
		$select_bodypart->add(Q($this->gettext('text')), Q('text'));
		$select_bodypart->add(Q($this->gettext('other')), Q('content'));
		$rules_table->add('header', $input_header->show($header) . $help_button . $select_bodypart->show($bodypart));

		$select_op = new html_select(array('name' => "_operator[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_rule_op_select(this)', 'style' => $op_style));
		foreach($this->operators as $name => $val)
			$select_op->add(Q($this->gettext($name)), $val);

		$select_size_op = new html_select(array('name' => "_size_operator[]", 'style' => $sizeop_style));
		$select_size_op->add(Q($this->gettext('filterunder')), 'under');
		$select_size_op->add(Q($this->gettext('filterover')), 'over');

		$select_spamtest_op = new html_select(array('name' => "_spamtest_operator[]", 'style' => $spamtestop_style));
		$select_spamtest_op->add(Q($this->gettext('spamlevelequals')), 'eq');
		$select_spamtest_op->add(Q($this->gettext('spamlevelislessthanequal')), 'le');
		$select_spamtest_op->add(Q($this->gettext('spamlevelisgreaterthanequal')), 'ge');

		if ($showadvanced)
			$rules_table->add('op', $select_op->show('advoptions') . $select_size_op->show($sizeop) . $select_spamtest_op->show($spamtestop));
		else
			$rules_table->add('op', $select_op->show($op) . $select_size_op->show($sizeop) . $select_spamtest_op->show($spamtestop));

		$input_target = new html_inputfield(array('name' => '_target[]', 'style' => $target_style, 'class' => $target_size));

		$select_units = new html_select(array('name' => "_units[]", 'style' => $units_style, 'class' => 'short'));
		$select_units->add(Q($this->gettext('B')), '');
		$select_units->add(Q($this->gettext('KB')), 'K');
		$select_units->add(Q($this->gettext('MB')), 'M');

		$select_spam_probability = new html_select(array('name' => "_spam_probability[]", 'style' => $spam_prob_style, 'class' => 'long'));
		$select_spam_probability->add(Q($this->gettext('spamnotchecked')), '0');
		$select_spam_probability->add(Q("0%"), '1');
		$select_spam_probability->add(Q("5%"), '2');
		$select_spam_probability->add(Q("20%"), '3');
		$select_spam_probability->add(Q("40%"), '4');
		$select_spam_probability->add(Q("50%"), '5');
		$select_spam_probability->add(Q("60%"), '6');
		$select_spam_probability->add(Q("70%"), '7');
		$select_spam_probability->add(Q("80%"), '8');
		$select_spam_probability->add(Q("90%"), '9');
		$select_spam_probability->add(Q("100%"), '10');

		$rules_table->add('target', $select_spam_probability->show($spam_probability) . $input_target->show($target) . "&nbsp;" . $select_units->show($units));

		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_rule', 'type' => 'image', 'image' => $attrib['addicon'], 'alt' => 'sieverules.addsieverule', 'title' => 'sieverules.addsieverule'));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_rule', 'type' => 'image', 'imageact' => $attrib['deleteicon'], 'imagepas' => $attrib['deleteiconpas'], 'alt' => 'sieverules.deletesieverule', 'title' => 'sieverules.deletesieverule'));
		$rules_table->add('control', $add_button . "&nbsp;" . $delete_button);

		if (isset($rule))
			$rowid = $rules_table->size();
		else
			$rowid = 'rowid';

		$headers_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 4));
		$headers_table->add(array('colspan' => 4, 'style' => 'white-space: normal;'), Q($this->gettext('sieveheadershlp')));
		$headers_table->add_row();

		$col1 = '';
		$col2 = '';
		$col3 = '';
		$col4 = '';
		sort($rcmail->config->get('sieverules_other_headers'));
		$col_length = sizeof($rcmail->config->get('sieverules_other_headers')) / 4;
		$col_length = ceil($col_length);
		foreach ($rcmail->config->get('sieverules_other_headers') as $idx => $xheader) {
			$input_xheader = new html_radiobutton(array('id' => $xheader . '_' . $rowid, 'name' => '_xheaders_' . $rowid . '[]', 'value' => $xheader, 'onclick' => JS_OBJECT_NAME . '.sieverules_set_xheader(this)', 'class' => 'radio'));
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

		$advanced_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
		$advanced_table->add(array('colspan' => 2, 'style' => 'white-space: normal;'), Q($this->gettext('advancedoptions')));
		$advanced_table->add_row();

		$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('contentpart'), 'border' => 0, 'style' => 'margin-left: 4px;'));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return '. JS_OBJECT_NAME .'.sieverules_help(this, ' . $advanced_table->size() . ');', 'title' => $this->gettext('contentpart')), $help_button);

		$field_id = 'rcmfd_advcontentpart_'. $rowid;
		$advanced_table->set_row_attribs(array('style' => $advcontentpart_style));
		$input_advcontentpart = new html_inputfield(array('id' => $field_id, 'name' => '_body_contentpart[]', 'class' => 'short'));
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('bodycontentpart'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advcontentpart->show($advcontentpart) . $help_button);

		$advanced_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$advanced_table->add(array('colspan' => 2, 'class' => 'vacdaysexp'), $this->gettext('contentpartexp'));
		$advanced_table->add_row();

		$field_id = 'rcmfd_advoperator_'. $rowid;
		$select_advop = new html_select(array('id' => $field_id, 'name' => "_advoperator[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_rule_advop_select(this)'));

		if (in_array('regex', $ext)) {
			$select_advop->add(Q($this->gettext('filterregex')), 'regex');
			$select_advop->add(Q($this->gettext('filternotregex')), 'notregex');
		}

		if (in_array('relational', $ext)) {
			$select_advop->add(Q($this->gettext('countisgreaterthan')), 'count "gt"');
			$select_advop->add(Q($this->gettext('countisgreaterthanequal')), 'count "ge"');
			$select_advop->add(Q($this->gettext('countislessthan')), 'count "lt"');
			$select_advop->add(Q($this->gettext('countislessthanequal')), 'count "le"');
			$select_advop->add(Q($this->gettext('countequals')), 'count "eq"');
			$select_advop->add(Q($this->gettext('countnotequals')), 'count "ne"');
			$select_advop->add(Q($this->gettext('valueisgreaterthan')), 'value "gt"');
			$select_advop->add(Q($this->gettext('valueisgreaterthanequal')), 'value "ge"');
			$select_advop->add(Q($this->gettext('valueislessthan')), 'value "lt"');
			$select_advop->add(Q($this->gettext('valueislessthanequal')), 'value "le"');
			$select_advop->add(Q($this->gettext('valueequals')), 'value "eq"');
			$select_advop->add(Q($this->gettext('valuenotequals')), 'value "ne"');
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
			$select_comparator = new html_select(array('id' => $field_id, 'name' => "_comparator[]"));
		else
 			$select_comparator = new html_select(array('id' => $field_id, 'name' => "_comparator[]", 'disabled' => 'disabled'));

 		$select_comparator->add(Q($this->gettext('i;ascii-casemap')), '');
 		$select_comparator->add(Q($this->gettext('i;octet')), 'i;octet');

 		foreach ($ext as $extension) {
 			if (substr($extension, 0, 11) == 'comparator-')
 				$select_comparator->add(Q($this->gettext(substr($extension, 11))), substr($extension, 11));
 		}

		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('comparator'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $select_comparator->show($rule['comparator']));

		$field_id = 'rcmfd_advtarget_'. $rowid;
		$input_advtarget = new html_inputfield(array('id' => $field_id, 'name' => '_advtarget[]'));
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, Q($this->gettext('teststring'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advtarget->show($target));

		if (!($showadvanced && $predefined == -1))
			$rules_table->set_row_attribs(array('style' => 'display: none;'));
		$rules_table->add(array('colspan' => 5), $advanced_table->show());
		$rules_table->add_row();

		return $rules_table;
	}

	private function _action_row($ext, $actions_table, $rowid, $action, $attrib, $example)
	{
		$rcmail = rcmail::get_instance();
		static $a_mailboxes;
		$imgclass = null;

		if (!isset($action)) {
			$actions_table->set_row_attribs(array('style' => 'display: none;'));
			$imgclass = 'nohtc';
		}

		$help_icon = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('messagehelp'), 'border' => 0));

		$vacadvstyle = ($action['type'] != 'vacation' && $this->force_vacto) ? '' : 'display: none;';
		$vacshowadv = ($action['type'] != 'vacation' && $this->force_vacto) ? '1' : '';
		$noteadvstyle = 'display: none;';
		$noteshowadv = '';

		// setup allowed actions
		$allowed_actions = array();
		$config_actions = $rcmail->config->get('sieverules_allowed_actions', array());
		if (in_array('fileinto', $ext) && ($config_actions['fileinto'] || $action['type'] == 'fileinto'))
			$allowed_actions['fileinto'] = $this->gettext('messagemoveto');
		if (in_array('fileinto', $ext) && in_array('copy', $ext) && ($config_actions['fileinto'] || $action['type'] == 'fileinto'))
			$allowed_actions['fileinto_copy'] = $this->gettext('messagecopyto');
		if (in_array('vacation', $ext) && ($config_actions['vacation'] || $action['type'] == 'vacation'))
			$allowed_actions['vacation'] = $this->gettext('messagevacation');
		if (in_array('reject', $ext) && ($config_actions['reject'] || $action['type'] == 'reject'))
			$allowed_actions['reject'] =  $this->gettext('messagereject');
		elseif (in_array('ereject', $ext) && ($config_actions['reject'] || $action['type'] == 'ereject'))
			$allowed_actions['ereject'] = $this->gettext('messagereject');
		if (in_array('imapflags', $ext) && ($config_actions['imapflags'] || $action['type'] == 'imapflags'))
			$allowed_actions['imapflags'] = $this->gettext('messageimapflags');
		elseif (in_array('imap4flags', $ext) && ($config_actions['imapflags'] || $action['type'] == 'imap4flags'))
			$allowed_actions['imap4flags'] = $this->gettext('messageimapflags');
		if (in_array('notify', $ext) && ($config_actions['notify'] || $action['type'] == 'notify'))
			$allowed_actions['notify'] = $this->gettext('messagenotify');
		elseif (in_array('enotify', $ext) && ($config_actions['notify'] || $action['type'] == 'enotify'))
			$allowed_actions['enotify'] = $this->gettext('messagenotify');
		if ($config_actions['redirect'] || $action['type'] == 'redirect')
			$allowed_actions['redirect'] = $this->gettext('messageredirect');
		if (in_array('copy', $ext) && ($config_actions['redirect'] || $action['type'] == 'redirect_copy'))
			$allowed_actions['redirect_copy'] = $this->gettext('messageredirectcopy');
		if ($config_actions['keep'] || $action['type'] == 'keep')
			$allowed_actions['keep'] = $this->gettext('messagekeep');
		if ($config_actions['discard'] || $action['type'] == 'discard')
			$allowed_actions['discard'] = $this->gettext('messagediscard');
		if ($config_actions['stop'] || $action['type'] == 'stop')
			$allowed_actions['stop'] = $this->gettext('messagestop');

		// set the default action
		reset($allowed_actions);
		$method = key($allowed_actions);

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
			$folder = $rcmail->config->get('sieverules_include_imap_root') ? $rcmail->imap->mod_mailbox($action['target'], 'out') : $action['target'];

			if ($rcmail->config->get('sieverules_folder_delimiter', false))
				$folder = str_replace($rcmail->imap->get_hierarchy_delimiter(), $rcmail->config->get('sieverules_folder_delimiter'), $folder);
		}
		elseif ($action['type'] == 'reject' || $action['type'] == 'ereject') {
			$method = $action['type'];
			$reject = htmlspecialchars($action['target']);
		}
		elseif ($action['type'] == 'vacation') {
			$method = 'vacation';
			$days = $action['days'];
			$vacfrom = $action['from'];
			$vacto = $action['addresses'];
			$handle = htmlspecialchars($action['handle']);
			$subject = htmlspecialchars($action['subject']);
			$origsubject = $action['origsubject'];
			$msg = htmlspecialchars($action['msg']);
			$charset = $action['charset'];
			if (!$example) $this->force_vacto = false;

			// check advanced enabled
			if (!empty($vacfrom) || !empty($vacto) || !empty($handle) || $charset != RCMAIL_CHARSET || $this->force_vacto) {
				$vacadvstyle = '';
				$vacshowadv = '1';
			}
		}
		elseif ($action['type'] == 'redirect' || $action['type'] == 'redirect_copy') {
			$method = $action['type'];
			$address = $action['target'];
		}
		elseif ($action['type'] == 'imapflags' || $action['type'] == 'imap4flags') {
			$method = $action['type'];
			$flags = $action['target'];
		}
		elseif ($action['type'] == 'notify' || $action['type'] == 'enotify') {
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
			$method = $action['type'];
		}

		$select_action = new html_select(array('name' => "_act[]", 'onchange' => JS_OBJECT_NAME . '.sieverules_action_select(this)'));
		foreach ($allowed_actions as $value => $text)
			$select_action->add(Q($text), $value);
		$actions_table->add('action', $select_action->show($method));

		$vacs_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => ($method == 'vacation') ? '' : 'display: none;'));

		$to_addresses = "";
		$vacto_arr = explode(",", $vacto);
		$user_identities = $rcmail->user->list_identities();
		if (count($user_identities)) {
			$field_id = 'rcmfd_sievevacfrom_'. $rowid;
			$select_id = new html_select(array('id' => $field_id, 'name' => "_vacfrom[]"));
			$select_id->add(Q($this->gettext('autodetect')), "");

			foreach ($user_identities as $sql_arr) {
				$select_id->add($sql_arr['email'], $sql_arr['email']);

				$ffield_id = 'rcmfd_vac_' . $rowid . '_' . $sql_arr['identity_id'];

				if ($this->force_vacto) {
					$curaddress = $sql_arr['email'];
					$vacto .= (!empty($vacto) ? ',' : '') . $sql_arr['email'];
				}
				else {
					$curaddress = in_array($sql_arr['email'], $vacto_arr) ? $sql_arr['email'] : "";
				}

				$input_address = new html_checkbox(array('id' => $ffield_id, 'name' => '_vacto_check_' . $rowid . '[]', 'value' => $sql_arr['email'], 'onclick' => JS_OBJECT_NAME . '.sieverules_toggle_vac_to(this, '. $rowid .')', 'class' => 'checkbox'));
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
		$input_day = new html_inputfield(array('id' => $field_id, 'name' => '_day[]', 'class' => 'short'));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('days'))));
		$vacs_table->add(null, $input_day->show($days));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		$vacs_table->set_row_attribs(array('style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('vacdaysexp'));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievevachandle_'. $rowid;
		$input_handle = new html_inputfield(array('id' => $field_id, 'name' => '_handle[]', 'class' => 'short'));
		$vacs_table->set_row_attribs(array('class' => 'disabled', 'style' => 'display: none')); // 'style' => $vacadvstyle
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('sievevachandle'))));
		$vacs_table->add(null, $input_handle->show($handle));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		$vacs_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'vacdaysexp'), $this->gettext('vachandleexp'));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievevacsubject_'. $rowid;
		$input_subject = new html_inputfield(array('id' => $field_id, 'name' => '_subject[]'));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('subject'))));
		$vacs_table->add(array('colspan' => 2), $input_subject->show($subject));
		$vacs_table->add_row();

		if (in_array('variables', $ext)) {
			$field_id = 'rcmfd_sievevacsubject_orig_'. $rowid;
			$input_origsubject = new html_checkbox(array('id' => $field_id, 'value' => '1', 'onclick' => JS_OBJECT_NAME . '.sieverules_toggle_vac_osubj(this, '. $rowid .')', 'class' => 'checkbox'));
			$input_vacosubj = new html_hiddenfield(array('id' => 'rcmfd_sievevactoh_'. $rowid, 'name' => '_orig_subject[]', 'value' => $origsubject));
			$vacs_table->add(null, '&nbsp;');
			$vacs_table->add(array('colspan' => 2), $input_origsubject->show($origsubject) . "&nbsp;" . html::label($field_id, Q($this->gettext('sieveorigsubj'))) . $input_vacosubj->show());
			$vacs_table->add_row();
		}

		$field_id = 'rcmfd_sievevacmag_'. $rowid;
		$input_msg = new html_textarea(array('id' => $field_id, 'name' => '_msg[]', 'rows' => '5', 'cols' => '40'));
		$vacs_table->add('msg', html::label($field_id, Q($this->gettext('message'))));
		$vacs_table->add(array('colspan' => 2), $input_msg->show($msg));
		$vacs_table->add_row();

		$field_id = 'rcmfd_sievecharset_'. $rowid;
		$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $vacadvstyle));
		$vacs_table->add(null, html::label($field_id, Q($this->gettext('charset'))));
		$vacs_table->add(array('colspan' => 2), $this->_charset_selector(array('id' => $field_id, 'name' => '_charset[]'), $charset));
		$vacs_table->add_row();

		$input_advopts = new html_checkbox(array('id' => 'vadvopts' . $rowid, 'name' => '_vadv_opts[]', 'onclick' => JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1', 'class' => 'checkbox'));
		$vacs_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('vadvopts' . $rowid, Q($this->gettext('advancedoptions'))) . $input_advopts->show($vacshowadv));

		$notify_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => ($method == 'notify' || $method == 'enotify') ? '' : 'display: none;'));

		$user_identities = $rcmail->user->list_identities();
		if (count($user_identities)) {
			$field_id = 'rcmfd_sievenotifyfrom_'. $rowid;
			$select_id = new html_select(array('id' => $field_id, 'name' => "_nfrom[]"));
			$select_id->add(Q($this->gettext('autodetect')), "");

			foreach ($user_identities as $sql_arr)
				$select_id->add($sql_arr['email'], $sql_arr['email']);

	 		$notify_table->set_row_attribs(array('class' => 'advanced', 'style' => $noteadvstyle));
 			$notify_table->add(null, html::label($field_id, Q($this->gettext('sievefrom'))));
 			$notify_table->add(array('colspan' => 2), $select_id->show($nfrom));
 			$notify_table->add_row();
		}

		$field_id = 'rcmfd_nmethod_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_nmethod[]'));
		$notify_table->add(null, html::label($field_id, Q($this->gettext('method'))));
		$notify_table->add(array('colspan' => 2), $input_method->show($nmethod));
		$notify_table->add_row();

		$field_id = 'rcmfd_noption_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_noption[]'));
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
		$input_msg = new html_inputfield(array('id' => $field_id, 'name' => '_nmsg[]'));
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
				if ($rcmail->config->get('sieverules_folder_encoding'))
					$ifolder = $this->_mbox_encode($ifolder, $rcmail->config->get('sieverules_folder_encoding'));

				if ($rcmail->config->get('sieverules_folder_delimiter', false))
					rcmail_build_folder_tree($a_mailboxes, str_replace($delimiter, $rcmail->config->get('sieverules_folder_delimiter'), $ifolder), $rcmail->config->get('sieverules_folder_delimiter'));
				else
					rcmail_build_folder_tree($a_mailboxes, $ifolder, $delimiter);
			}
		}

		$input_folderlist = new html_select(array('name' => '_folder[]', 'style' => ($method == 'fileinto' || $method == 'fileinto_copy') ? '' : 'display: none;'));
		rcmail_render_folder_tree_select($a_mailboxes, $mbox_name, 100, $input_folderlist, false);

		$input_address = new html_inputfield(array('name' => '_redirect[]', 'style' => ($method == 'redirect' || $method == 'redirect_copy') ? '' : 'display: none;'));
		$input_reject = new html_textarea(array('name' => '_reject[]', 'rows' => '5', 'cols' => '40', 'style' => ($method == 'reject' || $method == 'ereject') ? '' : 'display: none;'));
		$input_imapflags = new html_select(array('name' => '_imapflags[]', 'style' => ($method == 'imapflags' || $method == 'imap4flags') ? '' : 'display: none;'));
		foreach($this->flags as $name => $val)
			$input_imapflags->add(Q($this->gettext($name)), Q($val));

		$actions_table->add('folder', $input_folderlist->show($folder) . $input_address->show($address) . $vacs_table->show() . $notify_table->show() . $input_imapflags->show($flags) . $input_reject->show($reject));

		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_action', 'type' => 'image', 'image' => $attrib['addicon'], 'alt' => 'sieverules.addsieveact', 'title' => 'sieverules.addsieveact'));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_action', 'type' => 'image', 'imageact' => $attrib['deleteicon'], 'imagepas' => $attrib['deleteiconpas'], 'alt' => 'sieverules.deletesieveact', 'title' => 'sieverules.deletesieveact'));

		if ($rcmail->config->get('sieverules_multiple_actions'))
			$actions_table->add('control', $add_button . "&nbsp;" . $delete_button);
		else
			$actions_table->add('control', "&nbsp;");

		return $actions_table;
	}

	private function _in_headerarray($needle, $haystack)
	{
		foreach ($haystack as $data) {
			$args = explode("::", $data);
			if ($args[1] == $needle)
				return $args[0];
		}

		return false;
	}

	// coppied from rcube_template.php
	private function _charset_selector($attrib, $charset)
	{
		$charsets = array(
			'US-ASCII'		=> 'ASCII (English)',
			'EUC-JP'		=> 'EUC-JP (Japanese)',
			'EUC-KR'		=> 'EUC-KR (Korean)',
			'BIG5'			=> 'BIG5 (Chinese)',
			'GB2312'		=> 'GB2312 (Chinese)',
			'ISO-2022-JP'	=> 'ISO-2022-JP (Japanese)',
			'ISO-8859-1'	=> 'ISO-8859-1 (Latin-1)',
			'ISO-8859-2'	=> 'ISO-8859-2 (Central European)',
			'ISO-8859-7'	=> 'ISO-8859-7 (Greek)',
			'ISO-8859-9'	=> 'ISO-8859-9 (Turkish)',
			'Windows-1251'	=> 'Windows-1251 (Cyrillic)',
			'Windows-1252'	=> 'Windows-1252 (Western)',
			'Windows-1255'	=> 'Windows-1255 (Hebrew)',
			'Windows-1256'	=> 'Windows-1256 (Arabic)',
			'Windows-1257'	=> 'Windows-1257 (Baltic)',
			'UTF-8'			=> 'UTF-8'
			);

		$select = new html_select($attrib);
		$select->add(array_values($charsets), array_keys($charsets));

		return $select->show($charset);
	}

	private function _strip_val($str)
	{
		return trim(htmlspecialchars_decode($str));
	}

	private function _mbox_encode($text, $encoding)
	{
		return rcube_charset_convert($text, 'UTF7-IMAP', $encoding);
	}
}

?>