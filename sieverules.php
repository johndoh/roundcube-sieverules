<?php

/**
 * SieveRules
 *
 * Plugin to allow the user to manage their Sieve filters using the managesieve protocol
 *
 * @version @package_version@
 * @requires jQueryUI plugin
 * @author Philip Weir
 * Based on the Managesieve plugin by Aleksander Machniak
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
 * For more information see README.md.
 * For configuration see config.inc.php.dist.
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
class sieverules extends rcube_plugin
{
	public $task = 'mail|settings';

	protected $sieve;
	protected $sieve_error;
	protected $script;
	protected $action;
	protected $current_ruleset;

	private $examples = array();
	private $force_vacto = false;
	private $show_vacfrom = false;
	private $show_vachandle = false;
	private $standardops = array();
	private $additional_headers;
	private $vacation_ui = false;
	private $vacation_rule_position = 0;
	private $vacation_rule_name = '{{_automatic_reply_}}';

	// default headers
	private $headers = array(
					array('text' => 'subject', 'value' => 'header::Subject', 'ext' => null),
					array('text' => 'from', 'value' => 'header::From', 'ext' => null),
					array('text' => 'to', 'value' => 'header::To', 'ext' => null),
					array('text' => 'cc', 'value' => 'header::Cc', 'ext' => null),
					array('text' => 'envelopeto', 'value' => 'envelope::To', 'ext' => 'envelope'),
					array('text' => 'envelopefrom', 'value' => 'envelope::From', 'ext' => 'envelope'),
					array('text' => 'body', 'value' => 'body::body', 'ext' => 'body'),
					array('text' => 'spamtest', 'value' => 'spamtest::spamtest', 'ext' => 'spamtest'),
					array('text' => 'virustest', 'value' => 'virustest::virustest', 'ext' => 'virustest'),
					array('text' => 'arrival', 'value' => 'date::currentdate', 'ext' => 'date'),
					array('text' => 'size', 'value' => 'size::size', 'ext' => null),
					array('text' => 'otherheader', 'value' => 'header::other', 'ext' => null)
					);

	// default bodyparts
	private $bodyparts = array(
					array('text' => 'auto', 'value' => '', 'ext' => null),
					array('text' => 'raw', 'value' => 'raw', 'ext' => null),
					array('text' => 'text', 'value' => 'text', 'ext' => null),
					array('text' => 'other', 'value' => 'content', 'ext' => null),
					);

	// default dateparts
	private $dateparts = array(
					array('text' => 'date', 'value' => 'date', 'ext' => null),
					array('text' => 'time', 'value' => 'time', 'ext' => null),
					array('text' => 'weekday', 'value' => 'weekday', 'ext' => null)
					);

	// default operators
	private $operators = array(
					array('text' => 'filtercontains', 'value' => 'contains', 'ext' => null),
					array('text' => 'filternotcontains', 'value' => 'notcontains', 'ext' => null),
					array('text' => 'filteris', 'value' => 'is', 'ext' => null),
					array('text' => 'filterisnot', 'value' => 'notis', 'ext' => null),
					array('text' => 'filterexists', 'value' => 'exists', 'ext' => null),
					array('text' => 'filternotexists', 'value' => 'notexists', 'ext' => null)
					);

	// default sizeoperators
	private $sizeoperators = array(
					array('text' => 'filterunder', 'value' => 'under', 'ext' => null),
					array('text' => 'filterover', 'value' => 'over', 'ext' => null)
					);

	// default dateoperators
	private $dateoperators = array(
					array('text' => 'filteris', 'value' => 'is', 'ext' => null),
					array('text' => 'filterisnot', 'value' => 'notis', 'ext' => null),
					array('text' => 'filterbefore', 'value' => 'value "lt"', 'ext' => 'relational'),
					array('text' => 'filterafter', 'value' => 'value "gt"', 'ext' => 'relational')
					);

	// default spamoperators
	private $spamoperators = array(
					array('text' => 'spamlevelequals', 'value' => 'eq', 'ext' => null),
					array('text' => 'spamlevelislessthanequal', 'value' => 'le', 'ext' => null),
					array('text' => 'spamlevelisgreaterthanequal', 'value' => 'ge', 'ext' => null)
					);

	// default sizeunits
	private $sizeunits = array(
					array('text' => 'B', 'value' => '', 'ext' => null),
					array('text' => 'KB', 'value' => 'K', 'ext' => null),
					array('text' => 'MB', 'value' => 'M', 'ext' => null)
					);

	// default spamprobability
	private $spamprobability = array(
					array('text' => 'notchecked', 'value' => '0', 'ext' => null),
					array('text' => '0%', 'value' => '1', 'ext' => null),
					array('text' => '10%', 'value' => '2', 'ext' => null),
					array('text' => '20%', 'value' => '3', 'ext' => null),
					array('text' => '30%', 'value' => '4', 'ext' => null),
					array('text' => '40%', 'value' => '5', 'ext' => null),
					array('text' => '50%', 'value' => '6', 'ext' => null),
					array('text' => '60%', 'value' => '7', 'ext' => null),
					array('text' => '70%', 'value' => '8', 'ext' => null),
					array('text' => '80%', 'value' => '9', 'ext' => null),
					array('text' => '90%', 'value' => '9', 'ext' => null),
					array('text' => '100%', 'value' => '10', 'ext' => null)
					);

	// default virusprobability
	private $virusprobability = array(
					array('text' => 'notchecked', 'value' => '0', 'ext' => null),
					array('text' => 'novirus', 'value' => '1', 'ext' => null),
					array('text' => 'virusremoved', 'value' => '2', 'ext' => null),
					array('text' => 'viruscured', 'value' => '3', 'ext' => null),
					array('text' => 'possiblevirus', 'value' => '4', 'ext' => null),
					array('text' => 'definitevirus', 'value' => '5', 'ext' => null)
					);

	// default weekdays
	private $weekdays = array(
					array('text' => 'sunday', 'value' => '0', 'ext' => null),
					array('text' => 'monday', 'value' => '1', 'ext' => null),
					array('text' => 'tuesday', 'value' => '2', 'ext' => null),
					array('text' => 'wednesday', 'value' => '3', 'ext' => null),
					array('text' => 'thursday', 'value' => '4', 'ext' => null),
					array('text' => 'friday', 'value' => '5', 'ext' => null),
					array('text' => 'saturday', 'value' => '6', 'ext' => null)
					);

	// default advoperators
	private $advoperators = array(
					array('text' => 'filtermatches', 'value' => 'matches', 'ext' => null),
					array('text' => 'filternotmatches', 'value' => 'notmatches', 'ext' => null),
					array('text' => 'filterregex', 'value' => 'regex', 'ext' => 'regex'),
					array('text' => 'filternotregex', 'value' => 'notregex', 'ext' => 'regex'),
					array('text' => 'countisgreaterthan', 'value' => 'count "gt"', 'ext' => 'relational'),
					array('text' => 'countisgreaterthanequal', 'value' => 'count "ge"', 'ext' => 'relational'),
					array('text' => 'countislessthan', 'value' => 'count "lt"', 'ext' => 'relational'),
					array('text' => 'countislessthanequal', 'value' => 'count "le"', 'ext' => 'relational'),
					array('text' => 'countequals', 'value' => 'count "eq"', 'ext' => 'relational'),
					array('text' => 'countnotequals', 'value' => 'count "ne"', 'ext' => 'relational'),
					array('text' => 'valueisgreaterthan', 'value' => 'value "gt"', 'ext' => 'relational'),
					array('text' => 'valueisgreaterthanequal', 'value' => 'value "ge"', 'ext' => 'relational'),
					array('text' => 'valueislessthan', 'value' => 'value "lt"', 'ext' => 'relational'),
					array('text' => 'valueislessthanequal', 'value' => 'value "le"', 'ext' => 'relational'),
					array('text' => 'valueequals', 'value' => 'value "eq"', 'ext' => 'relational'),
					array('text' => 'valuenotequals', 'value' => 'value "ne"', 'ext' => 'relational'),
					array('text' => 'userpart', 'value' => 'user', 'ext' => 'subaddress'),
					array('text' => 'notuserpart', 'value' => 'notuser', 'ext' => 'subaddress'),
					array('text' => 'detailpart', 'value' => 'detail', 'ext' => 'subaddress'),
					array('text' => 'notdetailpart', 'value' => 'notdetail', 'ext' => 'subaddress'),
					array('text' => 'domainpart', 'value' => 'domain', 'ext' => 'subaddress'),
					array('text' => 'notdomainpart', 'value' => 'notdomain', 'ext' => 'subaddress')
					);

	// default comparators
	private $comparators = array(
					array('text' => 'i;ascii-casemap', 'value' => '', 'ext' => null),
					array('text' => 'i;octet', 'value' => 'i;octet', 'ext' => null)
					);

	// default flags
	private $flags = array(
					'flagread' => '\\\\Seen',
					'flagdeleted' => '\\\\Deleted',
					'flaganswered' => '\\\\Answered',
					'flagdraft' => '\\\\Draft',
					'flagflagged' => '\\\\Flagged'
					);

	private $identities = array();
	private $mailboxes = array();

	function init()
	{
		$rcmail = rcube::get_instance();
		$this->load_config();
		$this->add_texts('localization/');
		$this->additional_headers = $rcmail->config->get('sieverules_additional_headers', array('List-Id'));

		if ($rcmail->task == 'mail') {
			if (($rcmail->action == '' || $rcmail->action == 'show') && ($shortcut = $rcmail->config->get('sieverules_shortcut', 0)) > 0) {
				$this->include_stylesheet($this->local_skin_path() . '/mailstyles.css');
				$this->include_script('mail.js');

				if ($shortcut == 1) {
					$this->add_button(array('command' => 'plugin.sieverules.create', 'type' => 'link', 'class' => 'button buttonPas sieverules disabled', 'classact' => 'button sieverules', 'classsel' => 'button sieverulesSel', 'title' => 'sieverules.createfilterbased', 'label' => 'sieverules.createfilter'), 'toolbar');
				}
				else {
					$button = $this->api->output->button(array('command' => 'plugin.sieverules.create', 'label' => 'sieverules.createfilter', 'class' => 'icon sieverules', 'classact' => 'icon sieverules active', 'innerclass' => 'icon sieverules'));
					$this->api->add_content(html::tag('li', array('role' => 'menuitem'), $button), 'messagemenu');
				}
			}

			$this->register_action('plugin.sieverules.add_rule', array($this, 'add_rule'));
		}
		elseif ($rcmail->task == 'settings') {
			// load required plugin
			$this->require_plugin('jqueryui');

			// set options from config file
			if ($rcmail->config->get('sieverules_multiplerules') && rcube_utils::get_input_value('_ruleset', rcube_utils::INPUT_GET, true))
				$this->current_ruleset = rcube_utils::get_input_value('_ruleset', rcube_utils::INPUT_GET, true);
			elseif ($rcmail->config->get('sieverules_multiplerules') && $_SESSION['sieverules_current_ruleset'])
				$this->current_ruleset = $_SESSION['sieverules_current_ruleset'];
			elseif ($rcmail->config->get('sieverules_multiplerules'))
				$this->current_ruleset = false;
			else
				$this->current_ruleset = $rcmail->config->get('sieverules_ruleset_name');

			if (!$rcmail->config->get('sieverules_multiplerules') && $rcmail->config->get('sieverules_autoreply_ui'))
				$this->vacation_ui = true;

			// always include all identities when creating vacation messages
			$this->force_vacto = $rcmail->config->get('sieverules_force_vacto', $this->force_vacto);

			// include the 'from' option when creating vacation messages
			$this->show_vacfrom = $rcmail->config->get('sieverules_show_vacfrom', $this->show_vacfrom);

			// include the 'handle' option when creating vacation messages
			$this->show_vachandle = $rcmail->config->get('sieverules_show_vachandle', $this->show_vachandle);

			// use address command for address tests if configured
			// use address command by default for backwards compatibility
			if ($rcmail->config->get('sieverules_address_rules', true)) {
				$this->headers[1]['value'] = 'address::From';
				$this->headers[2]['value'] = 'address::To';
				$this->headers[3]['value'] = 'address::Cc';
				$this->headers[4]['value'] = 'address::Bcc';
			}

			$this->action = $rcmail->action;

			$this->include_stylesheet($this->local_skin_path() . '/tabstyles.css');
			$this->add_hook('settings_actions', array($this, 'settings_tab'));

			// register internal plugin actions
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
			$this->register_action('plugin.sieverules.init_rule', array($this, 'init_setup'));
			$this->register_action('plugin.sieverules.cancel_rule', array($this, 'cancel_rule'));

			if ($this->vacation_ui)
				$this->register_action('plugin.sieverules.vacation', array($this, 'init_html'));
		}

		if ($_SESSION['plugin.sieverules.rule']) {
			$this->add_hook('storage_init', array($this, 'fetch_headers'));
			$this->add_hook('sieverules_init', array($this, 'create_rule'));

			if ($rcmail->action == 'plugin.sieverules')
				$this->api->output->add_script(rcmail_output::JS_OBJECT_NAME .".add_onload('". rcmail_output::JS_OBJECT_NAME .".sieverules_import_rule(". $rcmail->config->get('sieverules_rule_setup', false) .")');");
		}
	}

	function settings_tab($p)
	{
		if ($this->vacation_ui)
			$p['actions'][] = array('action' => 'plugin.sieverules.vacation', 'class' => 'sieveautoreply', 'label' => 'sieverules.automaticreply', 'title' => 'sieverules.manageautoreply', 'role' => 'button', 'aria-disabled' => 'false', 'tabindex' => '0');

		$p['actions'][] = array('action' => 'plugin.sieverules', 'class' => 'sieverules', 'label' => 'sieverules.filters', 'title' => 'sieverules.managefilters', 'role' => 'button', 'aria-disabled' => 'false', 'tabindex' => '0');

		return $p;
	}

	function init_html()
	{
		// create SieveRules UI
		$rcmail = rcube::get_instance();
		$this->_startup();
		$this->include_script('sieverules.js');

		if ($rcmail->config->get('sieverules_multiplerules') && $this->current_ruleset === false) {
			// multiple rulesets enabled and no ruleset specified
			if ($ruleset = $this->sieve->get_active()) {
				// active ruleset exists on server use it.
				$this->current_ruleset = $this->sieve->get_active();
			}
			else {
				// no active ruleset exists, create one with default name and reinitialise
				$this->current_ruleset = $rcmail->config->get('sieverules_ruleset_name');
				$this->_startup();
				$rcmail->overwrite_action('plugin.sieverules.setup');
				$this->action = 'plugin.sieverules.setup';
			}
		}

		// if multiple rulesets enabled save the name of the active one for later, save looking it up again
		if ($rcmail->config->get('sieverules_multiplerules'))
			$_SESSION['sieverules_current_ruleset'] = $this->current_ruleset;

		$this->api->output->set_env('ruleset', $this->current_ruleset);

		if ($rcmail->config->get('sieverules_adveditor') == 2 && rcube_utils::get_input_value('_override', rcube_utils::INPUT_GET) != '1' && $this->action == 'plugin.sieverules') {
			// force UI to advanced mode, see gen_advanced()
			$rcmail->overwrite_action('plugin.sieverules.advanced');
			$this->action = 'plugin.sieverules.advanced';
		}

		// add handlers for the various UI elements
		$this->api->output->add_handlers(array(
			'sieveruleslisttitle' => array($this, 'gen_list_title'),
			'sieveruleslist' => array($this, 'gen_list'),
			'sieverulesexamplelist' => array($this, 'gen_examples'),
			'sieverulessetup' => array($this, 'gen_setup'),
			'sieveruleform' => array($this, 'gen_form'),
			'advancededitor' => array($this, 'gen_advanced'),
			'advswitch' => array($this, 'gen_advswitch'),
			'rulelist' => array($this, 'gen_rulelist'),
			'sieverulesframe' => array($this, 'sieverules_frame'),
			'vacation' => array($this, 'gen_vacation_form'),
		));

		if ($this->action != 'plugin.sieverules.advanced')
			$this->api->output->include_script('list.js');

		if (sizeof($this->examples) > 0)
			$this->api->output->set_env('examples', 'true');

		if (rcube_utils::get_input_value('_action', rcube_utils::INPUT_GET) == 'plugin.sieverules.vacation' && $this->action == 'plugin.sieverules.setup') {
			// override setup mode for vacation UI
			$rcmail->overwrite_action('plugin.sieverules.vacation');
			$this->action = 'plugin.sieverules.vacation';
			$this->sieve_error = true;
		}

		if ($this->action == 'plugin.sieverules.add' || $this->action == 'plugin.sieverules.edit' || $this->action == 'plugin.sieverules.vacation') {
			// show add/edit rule UI
			$rcmail->html_editor('sieverules');
			$this->api->output->add_script(sprintf("window.rcmail_editor_settings = %s",
				json_encode(array(
				'plugins' => 'autolink charmap code colorpicker hr link paste tabfocus textcolor',
				'toolbar' => 'bold italic underline alignleft aligncenter alignright alignjustify | outdent indent charmap hr | link unlink | code forecolor | fontselect fontsizeselect'
			))), 'head');

			if ($this->action == 'plugin.sieverules.vacation') {
				$this->api->output->set_pagetitle($this->gettext('automaticreply'));
				$this->api->output->send('sieverules.vacation');
			}
			else {
				$this->api->output->set_pagetitle($this->action == 'plugin.sieverules.add' ? $this->gettext('newfilter') : $this->gettext('editfilter'));
				$this->api->output->send('sieverules.editsieverule');
			}
		}
		elseif ($this->action == 'plugin.sieverules.setup') {
			// show setup UI
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->add_script(rcmail_output::JS_OBJECT_NAME .".add_onload('". rcmail_output::JS_OBJECT_NAME .".sieverules_load_setup()');");
			$this->api->output->send('sieverules.sieverules');
		}
		elseif ($this->action == 'plugin.sieverules.advanced') {
			// show "advanced mode" UI
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->send('sieverules.advancededitor');
		}
		else {
			// show main UI
			$this->api->output->set_pagetitle($this->gettext('filters'));
			$this->api->output->send('sieverules.sieverules');
		}
	}

	function init_setup()
	{
		// redirect setup UI, see gen_setup()
		$this->_startup();
		$this->include_script('sieverules.js');

		if (rcube::get_instance()->action == 'plugin.sieverules.init_rule') {
			$this->api->output->add_handlers(array('sieverulessetup' => array($this, 'gen_rule_setup')));
			$this->api->output->set_pagetitle($this->gettext('createfilter'));
		}
		else {
			$this->api->output->add_handlers(array('sieverulessetup' => array($this, 'gen_setup')));
			$this->api->output->set_pagetitle($this->gettext('importfilters'));
		}

		$this->api->output->send('sieverules.setupsieverules');
	}

	function sieverules_frame($attrib)
	{
		if (!$attrib['id'])
			$attrib['id'] = 'rcmprefsframe';

		return $this->api->output->frame($attrib, true);
	}

	function gen_advanced($attrib)
	{
		// create "advanced mode" UI
		list($form_start, $form_end) = get_form_tags($attrib, 'plugin.sieverules.save');
		$out = $form_start;

		$input_script = new html_textarea(array('id' => 'sieverules_adv', 'name' => '_script'));
		$out .= $input_script->show($this->sieve->script->raw);

		$out .= $form_end;

		return $out;
	}

	function gen_list_title($attrib)
	{
		if (rcube::get_instance()->config->get('sieverules_multiplerules', false)) {
			// if multiple rulesets enabled then add current ruleset name to UI plus an icon to signify active ruleset
			if ($this->current_ruleset == $this->sieve->get_active()) {
				$status = html::img(array('id' => 'rulesetstatus', 'src' => $attrib['activeicon'], 'alt' => $this->gettext('isactive'), 'title' => $this->gettext('isactive')));
			}
			else {
				$status = html::img(array('id' => 'rulesetstatus', 'src' => $attrib['inactiveicon'], 'alt' => $this->gettext('isinactive'), 'title' => $this->gettext('isinactive')));
			}

			$title = html::span(array('title' => $this->current_ruleset), $this->gettext(array('name' => 'filtersname', 'vars' => array('name' => $this->current_ruleset)))) . $status;
		}
		else {
			$title = $this->gettext('filters');
		}

		return $title;
	}


	function gen_list($attrib)
	{
		// create rule list for UI
		$this->api->output->add_label('sieverules.movingfilter', 'loading', 'sieverules.switchtoadveditor', 'sieverules.filterdeleteconfirm');
		$this->api->output->add_gui_object('sieverules_list', 'sieverules-table');

		$table = new html_table($attrib + array('cols' => 2));

		if (!$attrib['noheader']) {
			$table->add_header(array('colspan' => 2), $this->gen_list_title($attrib));
		}

		if (sizeof($this->script) == 0) {
			// no rules exist
			$table->add(array('colspan' => '2'), rcube_utils::rep_specialchars_output($this->gettext('nosieverules')));
		}
		else foreach($this->script as $idx => $filter) {
			$args = rcube::get_instance()->plugins->exec_hook('sieverules_list_rules', array('idx' => $idx, 'name' => $filter['name']));

			// skip the vacation
			if ($this->vacation_ui && $idx == $this->vacation_rule_position && $filter['name'] == $this->vacation_rule_name)
				continue;

			$parts = $this->_rule_list_parts($idx, $filter);
			$table->set_row_attribs(array('id' => 'rcmrow' . $idx, 'style' => $args['abort'] ? 'display: none;' : ''));
			$table->add(null, rcmail::Q($parts['name']));
			$table->add('control', $parts['control']);
		}

		return html::tag('div', array('id' => 'sieverules-list-filters'), $table->show($attrib));
	}

	function gen_js_list()
	{
		// create JS version of rule list for updating UI via AJAX
		$this->_startup();

		if (sizeof($this->script) == 0) {
			// no rules exist, clear rule list
			$this->api->output->command('sieverules_update_list', 'add-first', -1, rcube_utils::rep_specialchars_output($this->gettext('nosieverules')));
		}
		else foreach($this->script as $idx => $filter) {
			$args = rcube::get_instance()->plugins->exec_hook('sieverules_list_rules', array('idx' => $idx, 'name' => $filter['name']));
			if ($args['abort'] === true)
				continue;

			// skip the vacation
			if ($this->vacation_ui && $idx == $this->vacation_rule_position && $filter['name'] == $this->vacation_rule_name)
				continue;

			$parts = $this->_rule_list_parts($idx, $filter);
			$parts['control'] = str_replace("'", "\'", $parts['control']);

			// send rule to UI
			$this->api->output->command('sieverules_update_list', $idx == 0 ? 'add-first' : 'add', 'rcmrow' . $idx, rcmail::JQ($parts['name']), $parts['control'], $args['abort']);
		}

		$this->api->output->send();
	}

	function gen_examples($attrib)
	{
		// create list of example rules
		if (sizeof($this->examples) > 0) {
			$this->api->output->add_gui_object('sieverules_examples', 'sieverules-examples');

			$examples = new html_table($attrib + array('cols' => 1));

			if (!$attrib['noheader']) {
				$examples->add_header(null, $this->gettext('examplefilters'));
			}

			foreach($this->examples as $idx => $filter) {
				$examples->set_row_attribs(array('id' => 'rcmrowex' . $idx));
				$examples->add(null, rcmail::Q($filter['name']));
			}

			return html::tag('div', array('id' => 'sieverules-list-examples'), $examples->show($attrib));
		}
		else {
			return '';
		}

	}

	function gen_advswitch($attrib)
	{
		// create "switch to advanced mode" element
		$input_adv = new html_checkbox(array('id' => 'adveditor', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_adveditor(this);', 'value' => '1'));
		$out = html::label('adveditor', rcmail::Q($this->gettext('adveditor'))) . $input_adv->show($this->action == 'plugin.sieverules.advanced' ? '1' : '');
		return html::tag('div', array('id' => 'advancedmode'), $out);
	}

	function gen_rulelist($attrib)
	{
		// generate ruleset list (used when multiple rulesets enabled)
		$this->api->output->add_label('sieverules.delrulesetconf', 'sieverules.rulesetexists', 'sieverules.norulesetname');

		// get all the rulesets on the server
		$rulesets = array();
		foreach ($this->sieve->list as $ruleset) {
			$args = rcube::get_instance()->plugins->exec_hook('sieverules_list_rulesets', array('ruleset' => $ruleset));
			if ($args['abort'] === true)
				continue;

			array_push($rulesets, $ruleset);
		}
		sort($rulesets);

		// find the currently active ruleset
		$activeruleset = $this->sieve->get_active();

		// define "next ruleset" loaded after current ruleset is deleted
		$next_ruleset = '';
		for ($i = 0; $i < sizeof($rulesets); $i++) {
			if ($rulesets[$i] == $this->current_ruleset) {
				$i++;

				if ($i == sizeof($rulesets))
					$i = sizeof($rulesets) - 2;

				$next_ruleset = $rulesets[$i];
				break;
			}
		}

		// pass ruleset info to UI
		$this->api->output->set_env('ruleset_total', sizeof($rulesets));
		$this->api->output->set_env('ruleset_active', $this->current_ruleset == $activeruleset ? True : False);
		$this->api->output->set_env('ruleset_next', $next_ruleset);

		// new/rename ruleset dialog
		$out = '';
		$table = new html_table(array('cols' => 2, 'class' => 'propform'));
		$table->set_row_attribs(array('id' => 'sieverulesrsdialog_input'));
		$table->add('title', html::label('sieverulesrsdialog_name', rcmail::Q($this->gettext('name'))));
		$table->add(null, html::tag('input', array('type' => 'text', 'id' => 'sieverulesrsdialog_name', 'name' => '_name', 'value' => '', 'required' => 'required')));

		$select_ruleset = new html_select(array('id' => 'sieverulesrsdialog_ruleset'));
		if (sizeof($this->sieve->list) == 1) {
			$select_ruleset->add($this->gettext('nosieverulesets'), '');
		}
		else foreach ($rulesets as $ruleset) {
			if ($ruleset !== $this->current_ruleset)
				$select_ruleset->add($ruleset, $ruleset);
		}

		$table->set_row_attribs(array('id' => 'sieverulesrsdialog_select'));
		$table->add('title', html::label('sieverulesrsdialog_ruleset', rcmail::Q($this->gettext('selectruleset'))));
		$table->add(null, $select_ruleset->show());

		$buttons = html::tag('input', array('type' => 'hidden', 'id' => 'sieverulesrsdialog_action', 'value' => ''));
		$buttons .= html::tag('input', array('type' => 'button', 'class' => 'button mainaction', 'value' => $this->gettext('save'), 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverulesdialog_submit();')) . '&nbsp;';

		// create new/rename ruleset UI
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_add'), rcmail::Q($this->gettext('newruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_edit', 'style' => 'display: none;'), rcmail::Q($this->gettext('renameruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_copyto', 'style' => 'display: none;'), rcmail::Q($this->gettext('copytoruleset')));
		$out .= html::tag('h3', array('id' => 'sieverulesrsdialog_copyfrom', 'style' => 'display: none;'), rcmail::Q($this->gettext('copyfromruleset')));
		$out .= $table->show();
		$out .= html::p(array('class' => 'formbuttons'), $buttons);
		$out = html::tag('form', array(), $out);
		$out = html::div(array('id' => 'sieverulesrsdialog', 'style' => 'display: none;'), $out);

		// add overlay to main UI
		$this->api->output->add_footer($out);

		// build ruleset list for UI
		$action = ($this->action == 'plugin.sieverules.advanced') ? 'plugin.sieverules.advanced' : 'plugin.sieverules';
		if ($attrib['type'] == 'link') {
			$lis = '';

			if (sizeof($this->sieve->list) == 0) {
				$href = html::a(array('href' => "#", 'class' => 'active', 'onclick' => 'return false;', 'role' => 'button', 'tabindex' => '0', 'aria-disabled' => 'false'), rcmail::Q($this->gettext('nosieverulesets')));
				$lis .= html::tag('li', array('role' => 'menuitem'), $href);
			}
			else foreach ($rulesets as $ruleset) {
				$class = 'active';
				if ($ruleset === $this->current_ruleset)
					$class .= ' selected';

				$ruleset_text = $ruleset;
				if ($ruleset === $activeruleset)
					$ruleset_text = str_replace('%s', $ruleset, $this->gettext('activeruleset'));

				$href = html::a(array('href' => "#", 'class' => $class, 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_select_ruleset(\''. $ruleset .'\', \''. $action .'\');', 'role' => 'button', 'tabindex' => '0', 'aria-disabled' => 'false'), rcmail::Q($ruleset_text));
				$lis .= html::tag('li', array('role' => 'menuitem'), $href);
			}

			return $lis;
		}
		elseif ($attrib['type'] == 'select') {
			$select_ruleset = new html_select(array('id' => 'rulelist', 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_select_ruleset(this, \''. $action .'\');'));

			if (sizeof($this->sieve->list) == 0) {
				$select_ruleset->add($this->gettext('nosieverulesets'), '');
			}
			else foreach ($rulesets as $ruleset) {
				if ($ruleset === $activeruleset)
					$ruleset = str_replace('%s', $ruleset, $this->gettext('activeruleset'));

				$select_ruleset->add($ruleset, $ruleset);
			}

			return html::label('rulelist', rcmail::Q($this->gettext('selectruleset'))) . $select_ruleset->show(rcmail::Q($this->current_ruleset));
		}
	}

	function gen_setup()
	{
		$rcmail = rcube::get_instance();
		$text = '';
		$buttons = '';

		if ($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) {
			// show import options
			$text .= "<br /><br />" . $this->gettext('importdefault');
			$buttons .= $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_import=_default_', 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.usedefaultfilter'));
		}
		elseif ($rcmail->config->get('sieverules_default_file', false) && !is_readable($rcmail->config->get('sieverules_default_file'))) {
			rcube::raise_error(array(
				'code' => 600,
				'type' => 'php',
				'file' => __FILE__,
				'line' => __LINE__,
				'message' => "SieveRules plugin: Unable to open default rule file"
				), true, false);
		}

		$type = '';
		$ruleset = '';
		if (sizeof($this->sieve->list) > 0) {
			// show existing (non-supported?) ruleset
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

		if ($rcmail->config->get('sieverules_auto_load_default') && !$rcmail->config->get('sieverules_multiplerules', false) && $type != '' && $ruleset != '' && $ruleset == $this->sieve->get_active()) {
			// no ruleset found, automatically import active ruleset
			$this->import($type, $ruleset, false);

			if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
				$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".goto_url('plugin.sieverules');");
			}
			else {
				// go to sieverules page
				$rcmail->overwrite_action('plugin.sieverules');
				$this->api->output->send('sieverules.sieverules');
			}
		}
		else if ($rcmail->config->get('sieverules_auto_load_default') && is_readable($rcmail->config->get('sieverules_default_file')) && strlen($text) > 0 && strlen($buttons) > 0 && $type == '' && $ruleset == '') {
			// no ruleset found, automatically import default ruleset
			$this->import($type, '_default_', false);

			if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
				$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".goto_url('plugin.sieverules');");
			}
			else {
				// go to sieverules page
				$rcmail->overwrite_action('plugin.sieverules');
				$this->api->output->send('sieverules.sieverules');
			}
		}
		else if (strlen($text) > 0 && strlen($buttons) > 0) {
			// no existing rulesets, nothing to import
			$out = "<br />". $this->gettext('noexistingfilters') . $text . "<br /><br /><br />\n";
			$out .= $buttons;
			$out .= "&nbsp;&nbsp;" . $this->api->output->button(array('command' => 'plugin.sieverules.import', 'prop' => '_import=_none_', 'type' => 'input', 'class' => 'button', 'label' => 'cancel'));

			$out = html::tag('p', array('style' => 'text-align: center; padding: 10px;'), "\n" . $out);

			return $out;
		}
		else {
			if ($rcmail->config->get('sieverules_auto_load_default') && !is_readable($rcmail->config->get('sieverules_default_file')))
				rcube::raise_error(array(
					'code' => 600,
					'type' => 'php',
					'file' => __FILE__,
					'line' => __LINE__,
					'message' => "SieveRules plugin: Unable to open default rule file"
					), true, false);

			$this->sieve->save();
			if (!($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
				$this->sieve->set_active($this->current_ruleset);

			if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
				$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".goto_url('plugin.sieverules');");
			}
			else {
				// go to sieverules page
				$rcmail->overwrite_action('plugin.sieverules');
				$this->api->output->send('sieverules.sieverules');
			}
		}
	}

	function gen_rule_setup()
	{
		$out = "<br /><br />" . $this->gettext('addtoexisting');
		$out .= "<br /><br />" . $this->api->output->button(array('command' => 'plugin.sieverules.add_rule', 'type' => 'input', 'class' => 'button', 'label' => 'sieverules.newfilter'));
		$out .= "&nbsp;&nbsp;" . $this->api->output->button(array('command' => 'plugin.sieverules.cancel_rule', 'type' => 'input', 'class' => 'button', 'label' => 'cancel'));

		$out = html::tag('p', array('style' => 'text-align: center;'), "\n" . $out);

		return $out;
	}

	function gen_form($attrib)
	{
		$rcmail = rcube::get_instance();
		$this->include_script('jquery.maskedinput.min.js');
		$this->api->output->add_label(
			'sieverules.norulename', 'sieverules.ruleexists', 'sieverules.noheader',
			'sieverules.headerbadchars', 'sieverules.noheadervalue', 'sieverules.sizewrongformat',
			'sieverules.noredirect', 'sieverules.redirectaddresserror', 'sieverules.noreject', 'sieverules.vacnoperiod',
			'sieverules.vacperiodwrongformat', 'sieverules.vacnomsg', 'sieverules.vacmsgone', 'sieverules.notifynomethod',
			'sieverules.missingfoldername', 'sieverules.notifynomsg', 'sieverules.ruledeleteconfirm',
			'sieverules.actiondeleteconfirm', 'sieverules.notifyinvalidmethod', 'sieverules.nobodycontentpart',
			'sieverules.badoperator','sieverules.baddateformat','sieverules.badtimeformat','sieverules.vactoexp_err','editorwarning',
			'sieverules.eheadernoname','sieverules.eheadernoval');

		$ext = $this->sieve->get_extensions();

		// build conditional options
		if (in_array('regex', $ext) || in_array('relational', $ext) || in_array('subaddress', $ext))
			$this->operators[] = array('text' => 'filteradvoptions', 'value' => 'advoptions', 'ext' => null);

		foreach ($ext as $extension) {
			if (substr($extension, 0, 11) == 'comparator-' && $extension != 'comparator-i;ascii-casemap' && $extension != 'comparator-i;octet')
				$this->comparators[] = array('text' => substr($extension, 11), 'value' => substr($extension, 11), 'ext' => null);
		}

		// define standard ops
		foreach ($this->operators as $option)
			$this->standardops[] = $option['value'];

		// get user identities
		$this->identities = $rcmail->user->list_identities();
		foreach ($this->identities as $sql_id => $sql_arr)
			$this->identities[$sql_id]['from'] = $this->_rcmail_get_identity($sql_arr['identity_id']);

		// get user folders
		if (empty($this->mailboxes)) {
			$rcmail->storage_init();

			// get mailbox list
			if ($rcmail->config->get('sieverules_fileinto_options', 0) > 0)
				$a_folders = $rcmail->storage->list_folders();
			else
				$a_folders = $rcmail->storage->list_folders_subscribed();

			$delimiter = $rcmail->storage->get_hierarchy_delimiter();
			$this->mailboxes = array();

			foreach ($a_folders as $ifolder)
				$rcmail->build_folder_tree($this->mailboxes, $ifolder, $delimiter);

			if ($rcmail->config->get('sieverules_fileinto_options', 0) == 2 && in_array('mailbox', $ext))
				array_push($this->mailboxes, array('id' => '@@newfolder', 'name' => $this->gettext('createfolder'), 'virtual' => '', 'folders' => array()));
		}

		$iid = rcube_utils::get_input_value('_iid', rcube_utils::INPUT_GPC);
		if ($iid == '')
			$iid = sizeof($this->script);

		// get current script
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

			if (isset($this->script[$iid]))
				$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".sieverules_ready('".$iid."');");
		}

		// exec sieverules_init hook, allows for edit of default values
		$defaults = array();
		foreach (array('headers', 'bodyparts', 'dateparts', 'operators', 'sizeoperators', 'dateoperators', 'spamoperators', 'sizeunits', 'spamprobability', 'virusprobability', 'advoperators', 'comparators', 'flags', 'identities', 'folders') as $default)
			$defaults[$default] = $this->{$default};

		list($iid, $cur_script, $ext, $defaults) = array_values($rcmail->plugins->exec_hook('sieverules_init', array('id' => $iid, 'script' => $cur_script, 'extensions' => $ext, 'defaults' => $defaults)));

		foreach ($defaults as $name => $content)
			$this->{$name} = $content;

		//  build predefined rules and add to UI
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

		// filter name input
		$field_id = 'rcmfd_name';
		$input_name = new html_inputfield(array('name' => '_name', 'id' => $field_id, 'required' => 'required'));

		$out .= html::label($field_id, rcmail::Q($this->gettext('filtername')));
		$out .= "&nbsp;" . $input_name->show($cur_script['name']);

		// filter disable
		$field_id = 'rcmfd_disable';
		$input_disable = new html_checkbox(array('name' => '_disable', 'id' => $field_id, 'value' => 1));

		$out .= html::span('disableLink', html::label($field_id, rcmail::Q($this->gettext('disablerule')))
				. "&nbsp;" . $input_disable->show($cur_script['disabled']));

		$out .= "<br /><br />";

		// add rule join type to UI
		if (sizeof($cur_script['tests']) == 1 && $cur_script['tests'][0]['type'] == 'true' && !$cur_script['tests'][0]['not'])
			$join_any = true;

		$field_id = 'rcmfd_join_all';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'allof', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'allof\')'));
		$join_type = $input_join->show($cur_script['join'] && !$join_any ? 'allof' : '');
		$join_type .= "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('filterallof')));

		$field_id = 'rcmfd_join_anyof';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'anyof', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'anyof\')'));
		$join_type .= "&nbsp;" . $input_join->show($cur_script['join'] && !$join_any ? '' : 'anyof');
		$join_type .= "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('filteranyof')));

		$field_id = 'rcmfd_join_any';
		$input_join = new html_radiobutton(array('name' => '_join', 'id' => $field_id, 'value' => 'any', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_rule_join_radio(\'any\')'));
		$join_type .= "&nbsp;" . $input_join->show($join_any ? 'any' : '');
		$join_type .= "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('filterany')));

		$rules_table = new html_table(array('id' => 'rules-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 5));
		$rules_table = $this->_rule_row($ext, $rules_table, null, $rcmail->config->get('sieverules_predefined_rules'), $attrib);

		// add rules to UI
		if (!$join_any) {
			if (!isset($cur_script))
				$rules_table = $this->_rule_row($ext, $rules_table, array(), $rcmail->config->get('sieverules_predefined_rules'), $attrib);
			else foreach ($cur_script['tests'] as $rules)
				$rules_table = $this->_rule_row($ext, $rules_table, $rules, $rcmail->config->get('sieverules_predefined_rules'), $attrib);
		}

		$this->api->output->set_env('sieverules_rules', $rules_table->size());

		$out .= html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('messagesrules')))
				. rcmail::Q((!$rcmail->config->get('sieverules_use_elsif', true)) ? $this->gettext('sieveruleexp_stop') : $this->gettext('sieveruleexp')) . "<br /><br />"
				. $join_type . "<br /><br />"
				. $rules_table->show($attrib));

		$actions_table = new html_table(array('id' => 'actions-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$actions_table = $this->_action_row($ext, $actions_table, 'rowid', null, $attrib, $example);

		// add actions to UI
		if (!isset($cur_script))
			$actions_table = $this->_action_row($ext, $actions_table, 0, array(), $attrib, $example);
		else foreach ($cur_script['actions'] as $idx => $actions)
			$actions_table = $this->_action_row($ext, $actions_table, $idx, $actions, $attrib, $example);

		$this->api->output->set_env('sieverules_actions', $actions_table->size() - 3);
		$this->api->output->set_env('sieverules_htmleditor', $rcmail->config->get('htmleditor'));

		$out .= html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('messagesactions')))
				. rcmail::Q($this->gettext('sieveactexp')). "<br /><br />"
				. $actions_table->show($attrib));

		$out .= $form_end;

		// output sigs for vacation messages
		if (count($this->identities)) {
			foreach ($this->identities as $sql_arr) {
				// add signature to array
				if (!empty($sql_arr['signature'])) {
					$identity_id = $sql_arr['identity_id'];
					$a_signatures[$identity_id]['text'] = $sql_arr['signature'];

					if ($sql_arr['html_signature'] == 1) {
						$h2t = new rcube_html2text($a_signatures[$identity_id]['text'], false, false);
						$a_signatures[$identity_id]['text'] = trim($h2t->get_text());
					}
				}
			}

			$this->api->output->set_env('signatures', $a_signatures);
		}

		return $out;
	}

	function gen_vacation_form($attrib)
	{
		// check for sieve error
		if ($this->sieve_error) {
			return $this->gettext('pleaseinitialise'). '<br /><br />';
		}

		$rcmail = rcube::get_instance();
		$ext = $this->sieve->get_extensions();

		// add some labels to client
		$rcmail->output->add_label(
			'sieverules.baddateformat',
			'sieverules.redirectaddresserror',
			'sieverules.vactoexp_err',
			'sieverules.vacnoperiod',
			'sieverules.vacperiodwrongformat',
			'sieverules.vacnomsg',
			'editorwarning'
		);

		$help_icon = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('sieverules.messagehelp'), 'border' => 0));

		// set default field display
		$display = array(
			'vacadv' => ($this->force_vacto) ? '' : 'display: none;',
			'vacfrom' => ($this->show_vacfrom) ? $display['vacadv'] : 'display: none;',
			'vachandle' => ($this->show_vachandle) ? $display['vacadv'] : 'display: none;',
		);

		$defaults = array(
			'method' => 'vacation',
			'disabled' => 1,
			'vacto' => null,
			'address' => '',
			'period' => '',
			'periodtype' => '',
			'handle' => '',
			'subject' => '',
			'origsubject' => '',
			'msg' => '',
			'charset' => RCUBE_CHARSET
		);

		// get user identities
		$this->identities = $rcmail->user->list_identities();
		foreach ($this->identities as $sql_id => $sql_arr)
			$this->identities[$sql_id]['from'] = $this->_rcmail_get_identity($sql_arr['identity_id']);

		$cur_script = $this->script[$this->vacation_rule_position];

		// exec sieverules_init hook, allows for edit of default values
		$coredefaults = array();
		$coredefaults['identities'] = $this->identities;

		list($iid, $cur_script, $ext, $coredefaults) = array_values($rcmail->plugins->exec_hook('sieverules_init', array('id' => 0, 'script' => $cur_script, 'extensions' => $ext, 'defaults' => $coredefaults)));

		$this->identities = $coredefaults['identities'];

		if ($cur_script['name'] == $this->vacation_rule_name) {
			if (sizeof($cur_script['tests']) == 2) {
				$defaults['limitperiod'] = 1;
				$defaults['periodfrom'] = $cur_script['tests'][0]['target'];
				$defaults['periodto'] = $cur_script['tests'][1]['target'];
			}

			$action = $cur_script['actions'][0];

			$defaults['disabled'] = empty($cur_script['disabled']) ? 0 : $cur_script['disabled'];

			if (isset($action['seconds'])) {
				$defaults['period'] = $action['seconds'];
				$defaults['periodtype'] = 'seconds';
			}
			else {
				$defaults['period'] = $action['days'];
				$defaults['periodtype'] = 'days';
			}

			$defaults['vacfromdefault'] = $defaults['vacfrom'];
			$defaults['vacfrom'] = $action['from'];
			$defaults['vacto'] = $action['addresses'];
			$defaults['handle'] = $action['handle'];
			$defaults['subject'] = $action['subject'];
			$defaults['origsubject'] = $action['origsubject'];
			$defaults['msg'] = $action['msg'];
			$defaults['htmlmsg'] = $action['htmlmsg'] ? '1' : '';
			$defaults['charset'] = $action['charset'];

			if ($defaults['htmlmsg'] == '1' && $rcmail->config->get('htmleditor') == '0') {
				$h2t = new rcube_html2text($defaults['msg'], false, true, 0);
				$defaults['msg'] = $h2t->get_text();
				$defaults['htmlmsg'] = '';
			}
			elseif ($defaults['htmlmsg'] == '' && $rcmail->config->get('htmleditor') == '1') {
				$defaults['msg'] = $defaults['msg'];
				$defaults['msg'] = nl2br($defaults['msg']);
				$defaults['htmlmsg'] = '1';
			}

			// check advanced enabled
			if ((!empty($defaults['vacfrom']) && $defaults['vacfrom'] != $defaults['vacfromdefault']) || !empty($defaults['vacto']) || !empty($defaults['handle']) || !empty($defaults['period']) || $defaults['charset'] != RCUBE_CHARSET || $this->force_vacto) {
				$display['vacadv'] = '';
				$display['vacfrom'] = ($this->show_vacfrom) ? '' : 'display: none;';
				$display['vachandle'] = ($this->show_vachandle) ? '' : 'display: none;';
			}
		}

		list($form_start, $form_end) = get_form_tags(array('id' => 'sievevacation-form') + $attrib, 'plugin.sieverules.save');
		$rcmail->output->add_gui_object('sieveform', 'sievevacation-form');

		$input_name = new html_hiddenfield(array('name' => '_name', 'value' => $this->vacation_rule_name));
		$enable = $input_name->show();

		$input_mode = new html_hiddenfield(array('name' => '_vacation_mode', 'value' => 1));
		$enable .= $input_mode->show();

		$input_id = new html_hiddenfield(array('name' => '_iid', 'value' => $this->vacation_rule_position));
		$enable .= $input_id->show();

		$field_id = 'rcmfd_sievevac_disabled';
		$input_disabled = new html_hiddenfield(array('name' => '_disable', 'id' => $field_id, 'value' => $defaults['disabled'] === 0 ? '' : 1));
		$enable .= $input_disabled->show();

		$field_id = 'rcmfd_sievevac_join';
		$input_join = new html_hiddenfield(array('name' => '_join', 'id' => $field_id, 'value' => $defaults['limitperiod'] == 1 ? 'allof' : 'any'));
		$enable .= $input_join->show();

		$input_id = new html_hiddenfield(array('name' => '_iid', 'value' => $this->vacation_rule_position));
		$enable .= $input_id->show();

		$field_id = 'rcmfd_sievevac_enabled';
		$input_enabled = new html_checkbox(array('name' => '_enabled', 'id' => $field_id, 'value' => '1'));
		$enable .= $input_enabled->show($defaults['disabled'] === 0 ? 1 : 0);
		$enable .= "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('enableautoreply')));

		$input_test = new html_hiddenfield(array('name' => '_test[]', 'value' => 'date'));
		$input_header = new html_hiddenfield(array('name' => '_header[]', 'value' => 'currentdate'));
		$enable .= $input_test->show() . $input_header->show();
		$enable .= $input_test->show() . $input_header->show();

		$input_part = new html_hiddenfield(array('name' => '_datepart[]', 'value' => 'date'));
		$enable .= $input_part->show();
		$enable .= $input_part->show();

		$input_operator = new html_hiddenfield(array('name' => '_date_operator[]', 'value' => 'value "ge"'));
		$enable .= $input_operator->show();
		$input_operator = new html_hiddenfield(array('name' => '_date_operator[]', 'value' => 'value "le"'));
		$enable .= $input_operator->show();

		$field_id = 'rcmfd_sievevac_period';
		$input_period = new html_checkbox(array('name' => '_limit_period', 'id' => $field_id, 'value' => '1'));
		$enable .= "<br />" . $input_period->show($defaults['limitperiod']);
		$enable .= "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('sendonlyperiod')));

		$input_period_from = new html_inputfield(array('name' => '_target[]', 'id' => $field_id .'_from', 'disabled' => 'disabled'));
		$input_period_to = new html_inputfield(array('name' => '_target[]', 'id' => $field_id .'_to', 'disabled' => 'disabled'));
		$enable .= "&nbsp;" . html::label($field_id .'_from', rcmail::Q($this->gettext('datefrom'))) . $input_period_from->show($defaults['periodfrom']);
		$enable .= "&nbsp;" . html::label($field_id .'_to', rcmail::Q($this->gettext('dateto'))) . $input_period_to->show($defaults['periodto']);

		$input_act = new html_hiddenfield(array('name' => '_act[]', 'value' => 'vacation'));
		$enable .= $input_act->show();

		// return the complete form as table
		$vacs_table = $this->_vacation_table($ext, 0, $defaults, $display, $help_icon);

		$out = $vacs_table->show($attrib + array('id' => 'actions-table'));
		$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $enable . '<br /><br />' . $out);
		$out = $form_start . $out . $form_end;

		// output sigs for vacation messages
		if (count($this->identities)) {
			foreach ($this->identities as $sql_arr) {
				// add signature to array
				if (!empty($sql_arr['signature'])) {
					$identity_id = $sql_arr['identity_id'];
					$a_signatures[$identity_id]['text'] = $sql_arr['signature'];

					if ($sql_arr['html_signature'] == 1) {
						$h2t = new rcube_html2text($a_signatures[$identity_id]['text'], false, false);
						$a_signatures[$identity_id]['text'] = trim($h2t->get_text());
					}
				}
			}

			$this->api->output->set_env('signatures', $a_signatures);
		}

		return $out;
	}

	function move()
	{
		$this->_startup();

		$src = rcube_utils::get_input_value('_src', rcube_utils::INPUT_GET);
		$dst = rcube_utils::get_input_value('_dst', rcube_utils::INPUT_GET);

		$result = $this->sieve->script->move_rule($src, $dst);
		$result = $this->sieve->save();

		if ($result === true)
			$this->api->output->command('sieverules_update_list', 'move', $src , $dst);
		else
			$this->api->output->command('display_message', $result !== false ? $result : $this->gettext('filtersaveerror'), 'error');

		$this->api->output->send();
	}

	function save()
	{
		$rcmail = rcube::get_instance();
		$this->_startup();
		$vacation_mode = rcube_utils::get_input_value('_vacation_mode', rcube_utils::INPUT_POST) == 1 ? true : false;

		$script = trim(rcube_utils::get_input_value('_script', rcube_utils::INPUT_POST, true));
		if ($script != '' && ($rcmail->config->get('sieverules_adveditor') == 1 || $rcmail->config->get('sieverules_adveditor') == 2)) {
			$script = $this->_strip_val($script);
			$save = $this->sieve->save($script);

			if ($save === true) {
				$this->api->output->command('display_message', $this->gettext('filtersaved'), 'confirmation');
				$this->sieve->get_script();
			}
			else {
				$this->api->output->command('display_message', $save !== false ? $save : $this->gettext('filtersaveerror'), 'error');
			}

			// go to next step
			$rcmail->overwrite_action('plugin.sieverules.advanced');
			$this->action = 'plugin.sieverules.advanced';
			$this->init_html();
		}
		else {
			// check if POST var limits have been reached
			// code by Aleksander Machniak
			$max_post = max(array(
				ini_get('max_input_vars'),
				ini_get('suhosin.request.max_vars'),
				ini_get('suhosin.post.max_vars'),
			));

			$max_depth = max(array(
				ini_get('suhosin.request.max_array_depth'),
				ini_get('suhosin.post.max_array_depth'),
			));

			// check request size limit
			if ($max_post && count($_POST, COUNT_RECURSIVE) >= $max_post) {
				rcube::raise_error(array(
					'code' => 500,
					'type' => 'php',
					'file' => __FILE__,
					'line' => __LINE__,
					'message' => "SieveRules plugin: max_input_vars, suhosin.request.max_vars or suhosin.post.max_vars limit reached."
					), true, false);

				$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');

				// go to next step
				$rcmail->overwrite_action('plugin.sieverules.edit');
				$this->action = 'plugin.sieverules.edit';
				$this->init_html();

				return;
			}
			// check request depth limits
			else if ($max_depth && count($_POST['_test']) > $max_depth) {
				rcube::raise_error(array(
					'code' => 500,
					'type' => 'php',
					'file' => __FILE__,
					'line' => __LINE__,
					'message' => "SieveRules plugin: suhosin.request.max_array_depth or suhosin.post.max_array_depth limit reached."
					), true, false);

				$this->api->output->command('display_message', $this->gettext('filtersaveerror'), 'error');

				// go to next step
				$rcmail->overwrite_action('plugin.sieverules.edit');
				$this->action = 'plugin.sieverules.edit';
				$this->init_html();

				return;
			}

			// get input from form
			$name = trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST, true));
			$iid = trim(rcube_utils::get_input_value('_iid', rcube_utils::INPUT_POST));
			$join = trim(rcube_utils::get_input_value('_join', rcube_utils::INPUT_POST));
			$disabled = trim(rcube_utils::get_input_value('_disable', rcube_utils::INPUT_POST));

			$tests = rcube_utils::get_input_value('_test', rcube_utils::INPUT_POST);
			$headers = rcube_utils::get_input_value('_header', rcube_utils::INPUT_POST);
			$bodyparts = rcube_utils::get_input_value('_bodypart', rcube_utils::INPUT_POST);
			$ops = rcube_utils::get_input_value('_operator', rcube_utils::INPUT_POST);
			$sizeops = rcube_utils::get_input_value('_size_operator', rcube_utils::INPUT_POST);
			$dateops = rcube_utils::get_input_value('_date_operator', rcube_utils::INPUT_POST);
			$spamtestops = rcube_utils::get_input_value('_spamtest_operator', rcube_utils::INPUT_POST);
			$targets = rcube_utils::get_input_value('_target', rcube_utils::INPUT_POST, true);
			$sizeunits = rcube_utils::get_input_value('_units', rcube_utils::INPUT_POST);
			$contentparts = rcube_utils::get_input_value('_body_contentpart', rcube_utils::INPUT_POST);
			$comparators = rcube_utils::get_input_value('_comparator', rcube_utils::INPUT_POST);
			$advops = rcube_utils::get_input_value('_advoperator', rcube_utils::INPUT_POST);
			$advtargets = rcube_utils::get_input_value('_advtarget', rcube_utils::INPUT_POST, true);
			$dateparts = rcube_utils::get_input_value('_datepart', rcube_utils::INPUT_POST);
			$weekdays = rcube_utils::get_input_value('_weekday', rcube_utils::INPUT_POST);
			$advweekdays = rcube_utils::get_input_value('_advweekday', rcube_utils::INPUT_POST);

			$actions = rcube_utils::get_input_value('_act', rcube_utils::INPUT_POST);
			$folders = rcube_utils::get_input_value('_folder', rcube_utils::INPUT_POST);
			$customfolders = rcube_utils::get_input_value('_customfolder', rcube_utils::INPUT_POST);
			$addresses = rcube_utils::get_input_value('_redirect', rcube_utils::INPUT_POST);
			$rejects = rcube_utils::get_input_value('_reject', rcube_utils::INPUT_POST);
			$vacfroms = rcube_utils::get_input_value('_vacfrom', rcube_utils::INPUT_POST);
			$vactos = rcube_utils::get_input_value('_vacto', rcube_utils::INPUT_POST);
			$periods = rcube_utils::get_input_value('_period', rcube_utils::INPUT_POST);
			$periodtypes = rcube_utils::get_input_value('_periodtype', rcube_utils::INPUT_POST);
			$handles = rcube_utils::get_input_value('_handle', rcube_utils::INPUT_POST);
			$subjects = rcube_utils::get_input_value('_subject', rcube_utils::INPUT_POST, true);
			$origsubjects = rcube_utils::get_input_value('_orig_subject', rcube_utils::INPUT_POST, true);
			$msgs = rcube_utils::get_input_value('_msg', rcube_utils::INPUT_POST, true);
			$htmlmsgs = rcube_utils::get_input_value('_htmlmsg', rcube_utils::INPUT_POST, true);
			$charsets = rcube_utils::get_input_value('_vaccharset', rcube_utils::INPUT_POST);
			$flags = rcube_utils::get_input_value('_imapflags', rcube_utils::INPUT_POST);
			$nfroms = rcube_utils::get_input_value('_nfrom', rcube_utils::INPUT_POST);
			$nimpts = rcube_utils::get_input_value('_nimpt', rcube_utils::INPUT_POST);
			$nmethods = rcube_utils::get_input_value('_nmethod', rcube_utils::INPUT_POST);
			$noptions = rcube_utils::get_input_value('_noption', rcube_utils::INPUT_POST);
			$nmsgs = rcube_utils::get_input_value('_nmsg', rcube_utils::INPUT_POST, true);
			$eheadnames = rcube_utils:: get_input_value('_eheadname', rcube_utils::INPUT_POST, true);
			$eheadvals = rcube_utils::get_input_value('_eheadval', rcube_utils::INPUT_POST, true);
			$eheadopps = rcube_utils::get_input_value('_eheadopp', rcube_utils::INPUT_POST);
			$eheadindexes = rcube_utils::get_input_value('_eheadindex', rcube_utils::INPUT_POST);

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
				// parse form input
				$header = $this->_strip_val($headers[$idx]);
				$op = $this->_strip_val($ops[$idx]);
				$bodypart = $this->_strip_val($bodyparts[$idx]);
				$advop = $this->_strip_val($advops[$idx]);
				$contentpart = $this->_strip_val($contentparts[$idx]);
				$target = $this->_strip_val($targets[$idx]);
				$advtarget = $this->_strip_val($advtargets[$idx]);
				$comparator = $this->_strip_val($comparators[$idx]);
				$datepart = $this->_strip_val($dateparts[$idx]);
				$weekday = $this->_strip_val($weekdays[$idx]);
				$advweekday = $this->_strip_val($advweekdays[$idx]);

				switch ($type) {
					case 'size':
						$sizeop = $this->_strip_val($sizeops[$idx]);
						$sizeunit = $this->_strip_val($sizeunits[$idx]);

						$script['tests'][$i]['type'] = 'size';
						$script['tests'][$i]['operator'] = $sizeop;
						$script['tests'][$i]['target'] = $target.$sizeunit;
						break;
					case 'spamtest':
					case 'virustest':
						$spamtestop = $this->_strip_val($spamtestops[$idx]);

						$script['tests'][$i]['type'] = $type;
						$script['tests'][$i]['operator'] = $spamtestop;
						$script['tests'][$i]['target'] = $target;
						break;
					case 'date':
						$op = $this->_strip_val($dateops[$idx]);

						if ($datepart == 'weekday')
							$target = $weekday;

						$script['tests'][$i]['datepart'] = $datepart;
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
						if (preg_match('/^not/', $op) || preg_match('/^not/', $advop))
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
				$type = $this->_strip_val($type);

				$script['actions'][$i]['type'] = $type;

				// parse form input
				switch ($type) {
					case 'fileinto':
					case 'fileinto_copy':
						$folder = $this->_strip_val($folders[$idx], false, false);
						$rcmail = rcube::get_instance();
						$rcmail->storage_init();
						$script['actions'][$i]['create'] = false;
						if ($folder == '@@newfolder') {
							$script['actions'][$i]['create'] = true;
							$folder = rcube_charset::convert($customfolders[$idx], RCMAIL_CHARSET, 'UTF7-IMAP');
							$folder = $this->_strip_val($folder);
							$folder = $rcmail->config->get('sieverules_include_imap_root', true) ? $rcmail->storage->mod_folder($folder, 'IN') : $folder;
						}
						$script['actions'][$i]['target'] = $rcmail->config->get('sieverules_include_imap_root', true) ? $folder : $rcmail->storage->mod_folder($folder);
						if ($rcmail->config->get('sieverules_folder_delimiter'))
							$script['actions'][$i]['target'] = str_replace($rcmail->storage->get_hierarchy_delimiter(), $rcmail->config->get('sieverules_folder_delimiter'), $script['actions'][$i]['target']);
						if ($rcmail->config->get('sieverules_folder_encoding'))
							$script['actions'][$i]['target'] = rcube_charset::convert($script['actions'][$i]['target'], 'UTF7-IMAP', $rcmail->config->get('sieverules_folder_encoding'));
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
						$period = $this->_strip_val($periods[$idx]);
						$periodtype = $this->_strip_val($periodtypes[$idx]);
						$handle = $this->_strip_val($handles[$idx]);
						$subject = $this->_strip_val($subjects[$idx]);
						$origsubject = $this->_strip_val($origsubjects[$idx]);
						$htmlmsg = $this->_strip_val($htmlmsgs[$idx]);
						$msg = ($htmlmsg == "1") ? $msgs[$idx] : $this->_strip_val($msgs[$idx]);
						$charset = $this->_strip_val($charsets[$idx]);

						// format from address
						if (is_numeric($from)) {
							if (is_array($identity_arr = $this->_rcmail_get_identity($from))) {
								if ($identity_arr['val_string'])
									$from = $identity_arr['val_string'];
							}
							else {
								$from = null;
							}
						}

						// default vacation period units
						if (empty($periodtype))
							$periodtype = 'days';

						$script['actions'][$i][$periodtype] = $period;
						$script['actions'][$i]['subject'] = $subject;
						$script['actions'][$i]['origsubject'] = $origsubject;
						$script['actions'][$i]['from'] = $from;
						$script['actions'][$i]['addresses'] = $to;
						$script['actions'][$i]['handle'] = $handle;
						$script['actions'][$i]['msg'] = $msg;
						$script['actions'][$i]['htmlmsg'] = ($htmlmsg == "1") ? true : false;
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

						// format from address
						if (is_numeric($from)) {
							if (is_array($identity_arr = $this->_rcmail_get_identity($from))) {
								if ($identity_arr['val_string'])
									$from = $identity_arr['val_string'];
							}
							else {
								$from = null;
							}
						}

						$script['actions'][$i]['from'] = $from;
						$script['actions'][$i]['importance'] = $importance;
						$script['actions'][$i]['method'] = $method;
						$script['actions'][$i]['options'] = $option;
						$script['actions'][$i]['msg'] = $msg;
						break;
					case 'editheaderadd':
					case 'editheaderrem':
						$name = $this->_strip_val($eheadnames[$idx]);
						$value = $this->_strip_val($eheadvals[$idx]);
						$script['actions'][$i]['name'] = $name;
						$script['actions'][$i]['value'] = $value;
						$script['actions'][$i]['index'] = $eheadindexes[$idx];

						if (strlen($script['actions'][$i]['value']) > 0)
							$script['actions'][$i]['operator'] = $eheadopps[$idx];

						break;
				}

				$i++;
			}

			if ($vacation_mode && !isset($this->script[$iid]))
				$result = $this->sieve->script->add_rule($script, $iid);
			elseif (!isset($this->script[$iid]))
				$result = $this->sieve->script->add_rule($script);
			else
				$result = $this->sieve->script->update_rule($iid, $script);

			if ($result === true)
				$save = $this->sieve->save();

			// always set ruleset active if its the only one
			if ($save === true && $result === true && !($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
				$save = $this->sieve->set_active($this->current_ruleset);

			if ($save === true && $result === true) {
				$this->api->output->command('display_message', $vacation_mode ? $this->gettext('autoreplysaved') : $this->gettext('filtersaved'), 'confirmation');

				$parts = $this->_rule_list_parts($iid, $script);
				$parts['control'] = str_replace("'", "\'", $parts['control']);

				// update rule list in UI
				if (!$vacation_mode) {
					if (!isset($this->script[$iid]) && sizeof($this->script) == 0)
						$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".sieverules_update_list('add-first', 'rcmrow". $iid ."', '". rcmail::Q($parts['name']) ."', '". $parts['control'] ."');");
					elseif (!isset($this->script[$iid]))
						$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".sieverules_update_list('add', 'rcmrow". $iid ."', '". rcmail::Q($parts['name']) ."', '". $parts['control'] ."');");
					else
						$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".sieverules_update_list('update', ". $iid .", '". rcmail::Q($parts['name']) ."');");
				}
			}
			else {
				if ($result === SIEVE_ERROR_BAD_ACTION)
					$this->api->output->command('display_message', $this->gettext('filteractionerror'), 'error');
				elseif ($result === SIEVE_ERROR_NOT_FOUND)
					$this->api->output->command('display_message', $this->gettext('filtermissingerror'), 'error');
				else
					$this->api->output->command('display_message', $save !== false ? $save : $this->gettext('filtersaveerror'), 'error');
			}

			// update rule list in script
			if ($this->sieve_error)
				$this->script = array();
			else
				$this->script = $this->sieve->script->as_array();

			// go to next step
			if ($vacation_mode) {
				$rcmail->overwrite_action('plugin.sieverules.vacation');
				$this->action = 'plugin.sieverules.vacation';
			}
			else {
				$rcmail->overwrite_action('plugin.sieverules.edit');
				$this->action = 'plugin.sieverules.edit';
			}

			$this->init_html();
		}
	}

	function delete()
	{
		$this->_startup();

		$result = false;
		$ids = rcube_utils::get_input_value('_iid', rcube_utils::INPUT_GET);
		if (is_numeric($ids) && isset($this->script[$ids]) && !$this->sieve_error) {
			$result = $this->sieve->script->delete_rule($ids);
			if ($result === true)
				$result = $this->sieve->save();
		}

		if ($result === true) {
			$this->api->output->command('display_message', $this->gettext('filterdeleted'), 'confirmation');
			$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".sieverules_update_list('delete', ". $ids .");");
		}
		elseif ($result === SIEVE_ERROR_NOT_FOUND)
			$this->api->output->command('display_message', $this->gettext('filtermissingerror'), 'error');
		else
			$this->api->output->command('display_message', $result !== false ? $result : $this->gettext('filterdeleteerror'), 'error');

		// update rule list
		if ($this->sieve_error)
			$this->script = array();
		else
			$this->script = $this->sieve->script->as_array();

		if (isset($_GET['_framed']) || isset($_POST['_framed'])) {
			$this->api->output->add_script("parent.". rcmail_output::JS_OBJECT_NAME .".show_contentframe(false);");
		}
		else {
			// go to sieverules page
			rcube::get_instance()->overwrite_action('plugin.sieverules');
			$this->action = 'plugin.sieverules';
			$this->init_html();
		}
	}

	function import($type = null, $ruleset = null, $redirect = true)
	{
		$rcmail = rcube::get_instance();
		$this->_startup();

		if (!$type && !$ruleset) {
			$type = rcube_utils::get_input_value('_type', rcube_utils::INPUT_GET);
			$ruleset = rcube_utils::get_input_value('_import', rcube_utils::INPUT_GET);
		}

		if ($ruleset == '_default_') {
			// import default rule file (defined in config)
			if ($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) {
				$this->sieve->script->add_text(file_get_contents($rcmail->config->get('sieverules_default_file')));
				$save = $this->sieve->save();

				if ($save === true && !($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
					$save = $this->sieve->set_active($this->current_ruleset);

				if ($save === true)
					$this->api->output->command('display_message', $this->gettext('filterimported'), 'confirmation');
				else
					$this->api->output->command('display_message', $save !== false ? $save : $this->gettext('filterimporterror'), 'error');

				// update rule list
				if ($this->sieve_error)
					$this->script = array();
				else
					$this->script = $this->sieve->script->as_array();
			}
			elseif ($rcmail->config->get('sieverules_default_file', false) && !is_readable($rcmail->config->get('sieverules_default_file'))) {
				rcube::raise_error(array(
					'code' => 600,
					'type' => 'php',
					'file' => __FILE__,
					'line' => __LINE__,
					'message' => "SieveRules plugin: Unable to open default rule file"
					), true, false);
			}
		}
		elseif ($ruleset == '_example_') {
			// import example rule file (defined in config)
			if (rcube_utils::get_input_value('_eids', rcube_utils::INPUT_GET)) {
				$pos = rcube_utils::get_input_value('_pos', rcube_utils::INPUT_GET);
				$eids = explode(",", rcube_utils::get_input_value('_eids', rcube_utils::INPUT_GET));

				if ($pos == 'end')
					$pos = null;
				else
					$pos = substr($pos, 6);

				foreach ($eids as $eid) {
					$this->sieve->script->add_rule($this->examples[substr($eid, 2)], $pos);
					if ($pos)
						$pos++;
				}

				$this->sieve->save();
				if (!($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
					$this->sieve->set_active($this->current_ruleset);

				// update rule list
				if ($this->sieve_error)
					$this->script = array();
				else
					$this->script = $this->sieve->script->as_array();
			}
		}
		elseif ($ruleset == '_none_') {
			// do not import anything
			$this->sieve->save();
			if (!($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
				$this->sieve->set_active($this->current_ruleset);
		}
		elseif ($ruleset == '_copy_') {
			// copy existing ruleset
			$this->rename_ruleset(true);
			return;
		}
		elseif ($type != '' && $ruleset != '') {
			// attempt to import with import filter
			$import = $this->sieve->do_import($type, $ruleset);

			if ($import) {
				$this->script = $this->sieve->script->as_array();
				$this->sieve->save();

				if (!($rcmail->config->get('sieverules_multiplerules', false) && sizeof($this->sieve->list) > 1))
					$this->sieve->set_active($this->current_ruleset);

				$this->api->output->command('display_message', $this->gettext('filterimported'), 'confirmation');
			}
			else {
				$this->script = array();

				if (!$redirect)
					$this->sieve->save();

				$this->api->output->command('display_message', $this->gettext('filterimporterror'), 'error');
			}
		}

		if ($redirect) {
			// go to sieverules page
			$rcmail->overwrite_action('plugin.sieverules');
			$this->action = 'plugin.sieverules';
			$this->init_html();
		}
	}

	function delete_ruleset()
	{
		$this->_startup();
		$this->sieve->del_script($this->current_ruleset);

		$this->current_ruleset = rcube_utils::get_input_value('_next', rcube_utils::INPUT_GET);

		rcube::get_instance()->overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	function rename_ruleset($makeCopy = false)
	{
		$this->_startup();
		$script = $this->sieve->script->as_text();
		$active = $this->sieve->get_active() == $this->current_ruleset ? true : false;

		$old_ruleset = $this->current_ruleset;
		$this->current_ruleset = rcube_utils::get_input_value('_new', rcube_utils::INPUT_GET, true);
		$this->sieve->set_ruleset($this->current_ruleset);
		$this->sieve->save($script);

		if (!$makeCopy) {
			if ($active)
				$this->sieve->set_active($this->current_ruleset);

			$this->sieve->del_script($old_ruleset);
		}

		rcube::get_instance()->overwrite_action('plugin.sieverules');
		$this->action = 'plugin.sieverules';
		$this->init_html();
	}

	function enable_ruleset()
	{
		$this->_startup();
		$activeruleset = rcube_utils::get_input_value('_ruleset', rcube_utils::INPUT_GET, true);
		$this->sieve->set_active($activeruleset);

		if (rcube_utils::get_input_value('_reload', rcube_utils::INPUT_GET, true) == "1") {
			rcube::get_instance()->overwrite_action('plugin.sieverules');
			$this->action = 'plugin.sieverules';
			$this->init_html();
		}
		else {
			$rulesets = array();
			foreach ($this->sieve->list as $ruleset)
				array_push($rulesets, $ruleset);

			sort($rulesets);

			foreach ($rulesets as $ruleset) {
				if ($ruleset === $activeruleset)
					$this->api->output->command('sieverules_add_ruleset', rcmail::Q($ruleset), rcmail::Q(str_replace('%s', $ruleset, $this->gettext('activeruleset'))));
				else
					$this->api->output->command('sieverules_add_ruleset', rcmail::Q($ruleset), rcmail::Q($ruleset));
			}

			$this->api->output->send();
		}
	}

	function copy_filter()
	{
		$this->_startup();
		$script = $this->script[rcube_utils::get_input_value('_iid', rcube_utils::INPUT_GET)];
		$this->current_ruleset = rcube_utils::get_input_value('_dest', rcube_utils::INPUT_GET);
		$this->_startup();
		$this->sieve->script->add_rule($script);
		$this->sieve->save();

		$this->api->output->command('display_message', $this->gettext('filtercopied'), 'confirmation');
		$this->api->output->send();
	}

	function fetch_headers($attr)
	{
		$attr['fetch_headers'] .= trim($attr['fetch_headers'] . join(' ', $this->additional_headers));
		return($attr);
	}

	function add_rule()
	{
		$_SESSION['plugin.sieverules.rule'] = true;
		$_SESSION['plugin.sieverules.messageset'] = serialize(rcmail::get_uids());
		rcube::get_instance()->output->redirect(array('task' => 'settings', 'action' => 'plugin.sieverules'));
	}

	function create_rule($args)
	{
		$rcmail = rcube::get_instance();
		if ($rcmail->action == 'plugin.sieverules.add' || $rcmail->action == 'plugin.sieverules.edit') {
			$messageset = unserialize($_SESSION['plugin.sieverules.messageset']);
			$headers = $args['defaults']['headers'];
			$rcmail->storage_init();

			foreach ($messageset as $mbox => $uids) {
				$rcmail->get_storage()->set_folder($mbox);

				foreach ($uids as $uid) {
					$message = new rcube_message($uid);
					$this->_add_to_array($args['script']['tests'], array('type' => $rcmail->config->get('sieverules_address_rules', true) ? 'address' : 'header', 'operator' => 'is', 'header' => 'From', 'target' => $message->sender['mailto']));

					$recipients = array();
					$recipients_array = rcube_mime::decode_address_list($message->headers->to);
					foreach ($recipients_array as $recipient) {
						$recipients[] = $recipient['mailto'];
					}

					$identity = $rcmail->user->get_identity();
					$recipient_str = join(', ', $recipients);
					if ($recipient_str != $identity['email']) {
						$this->_add_to_array($args['script']['tests'], array('type' => $rcmail->config->get('sieverules_address_rules', true) ? 'address' : 'header', 'operator' => 'is', 'header' => 'To', 'target' => $recipient_str));
					}

					if (strlen($message->subject) > 0) {
						$this->_add_to_array($args['script']['tests'], array('type' => 'header', 'operator' => 'contains', 'header' => 'Subject', 'target' => $message->subject));
					}

					foreach ($this->additional_headers as $header) {
						if (strlen($message->headers->others[strtolower($header)]) > 0) {
							$this->_add_to_array($args['script']['tests'], array('type' => 'header', 'operator' => 'is', 'header' => $header, 'target' => $message->headers->others[strtolower($header)]));
						}
					}

					$this->_add_to_array($args['script']['actions'], array('type' => 'fileinto', 'target' => $mbox));

					foreach ($message->headers->flags as $flag => $value) {
						if ($flag == 'FLAGGED') {
							$this->_add_to_array($args['script']['actions'], array('type' => 'imapflags', 'target' => '\\\\Flagged'));
						}
					}
				}
			}

			$_SESSION['plugin.sieverules.rule'] = false;
			$_SESSION['plugin.sieverules.messageset'] = null;
		}

		return $args;
	}

	function cancel_rule()
	{
		$_SESSION['plugin.sieverules.rule'] = false;
		$_SESSION['plugin.sieverules.messageset'] = null;
		rcube::get_instance()->output->redirect(array('task' => 'mail', 'action' => ''));
	}

	protected function _startup()
	{
		$rcmail = rcube::get_instance();

		if (!$this->sieve) {
			// Add include path for internal classes
			$include_path = $this->home . '/lib' . PATH_SEPARATOR;
			$include_path .= ini_get('include_path');
			set_include_path($include_path);

			// try to connect to managesieve server and to fetch the script
			$this->sieve = new rcube_sieve($_SESSION['username'],
						$rcmail->decrypt($_SESSION['password']),
						rcube_utils::idn_to_ascii(rcube_utils::parse_host($rcmail->config->get('sieverules_host'))),
						$rcmail->config->get('sieverules_port'), $rcmail->config->get('sieverules_auth_type', NULL),
						$rcmail->config->get('sieverules_usetls'), $this->current_ruleset,
						$this->home, $rcmail->config->get('sieverules_use_elsif', true),
						$rcmail->config->get('sieverules_auth_cid', NULL), $rcmail->config->get('sieverules_auth_pw', NULL),
						$rcmail->config->get('sieverules_conn_options', NULL));

			if ($rcmail->config->get('sieverules_debug', false))
				$this->sieve->set_debug(true);

			$this->sieve_error = $this->sieve->error();

			if ($this->sieve_error == SIEVE_ERROR_NOT_EXISTS) {
				// load default rule set
				if (($rcmail->config->get('sieverules_default_file', false) && is_readable($rcmail->config->get('sieverules_default_file'))) || sizeof($this->sieve->list) > 0) {
					$rcmail->overwrite_action('plugin.sieverules.setup');
					$this->action = 'plugin.sieverules.setup';
				}
				elseif ($rcmail->config->get('sieverules_default_file', false) && !is_readable($rcmail->config->get('sieverules_default_file'))) {
					rcube::raise_error(array(
						'code' => 600,
						'type' => 'php',
						'file' => __FILE__,
						'line' => __LINE__,
						'message' => "SieveRules plugin: Unable to open default rule file"
						), true, false);
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
			if ($this->sieve_error) {
				$this->script = array();
			}
			else {
				$this->script = $this->sieve->script->as_array();

				// load example filters
				if ($rcmail->config->get('sieverules_example_file', false) && is_readable($rcmail->config->get('sieverules_example_file')))
					$this->examples = $this->sieve->script->parse_text(file_get_contents($rcmail->config->get('sieverules_example_file')));
				elseif ($rcmail->config->get('sieverules_example_file', false) && !is_readable($rcmail->config->get('sieverules_example_file')))
					rcube::raise_error(array(
						'code' => 600,
						'type' => 'php',
						'file' => __FILE__,
						'line' => __LINE__,
						'message' => "SieveRules plugin: Unable to open example rule file"
						), true, false);
			}
		}
		else {
			$this->sieve->set_ruleset($this->current_ruleset);
			$this->script = $this->sieve->script->as_array();
		}
	}

	private function _rule_row($ext, $rules_table, $rule, $predefined_rules, $attrib)
	{
		$rcmail = rcube::get_instance();

		// set default field display
		$display = array(
			'header' => 'visibility: hidden;',
			'op' => '',
			'sizeop' => 'display: none;',
			'dateop' => 'display: none;',
			'spamtestop' => 'display: none;',
			'target' => '',
			'units' => 'display: none;',
			'bodypart' => 'display: none;',
			'datepart' => 'display: none;',
			'advancedopts' => false,
			'advcontentpart' => 'display: none;',
			'spamprob' => 'display: none;',
			'virusprob' => 'display: none;',
			'weekdays' => 'display: none;',
			'advweekdays' => 'display: none;',
			'advtarget' => ''
		);

		// set default values
		$defaults = array(
			'test' => 'header',
			'selheader' => 'Subject',
			'header' => 'Subject',
			'op' => 'contains',
			'sizeop' => 'under',
			'spamtestop' => 'ge',
			'target' => '',
			'targetsize' => '',
			'units' => 'KB',
			'bodypart' => '',
			'advcontentpart' => ''
		);

		// check if current rule is predefined (hide all option boxes)
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

		// apply current rule values
		if ($predefined > -1) {
			$display['op'] = 'display: none;';
			$display['target'] = 'display: none;';
			$defaults['selheader'] = $rule['type'] . '::predefined_' . $predefined;
			$defaults['test'] = $rule['type'];

			if ($rule['type'] == 'size') {
				$defaults['header'] = 'size';
				$defaults['sizeop'] = $rule['operator'];
				preg_match('/^([0-9]+)(K|M|G)*$/', $rule['target'], $matches);
				$defaults['target'] = $matches[1];
				$defaults['targetsize'] = 'short';
				$defaults['units'] = $matches[2];
			}
			elseif ($rule['type'] == 'spamtest') {
				$defaults['header'] = 'spamtest';
				$defaults['spamtestop'] = $rule['operator'];
				$defaults['target'] = $rule['target'];
			}
			elseif ($rule['type'] == 'virustest') {
				$defaults['header'] = 'virustest';
				$defaults['spamtestop'] = $rule['operator'];
				$defaults['target'] = $rule['target'];
			}
			elseif ($rule['type'] == 'exists') {
				$defaults['selheader'] = $predefined_rules[$predefined]['type'] . '::predefined_' . $predefined;
				$defaults['header'] = $rule['header'];
				$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
			}
			else {
				$defaults['header'] = $rule['header'];
				$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
				$defaults['target'] = $rule['target'];
			}
		}
		elseif ((isset($rule['type']) && $rule['type'] == 'exists') && $this->_in_headerarray($rule['header'], $this->headers) != false) {
			$display['target'] = $rule['operator'] == 'exists' ? 'display: none;' : '';

			$defaults['selheader'] = $this->_in_headerarray($rule['header'], $this->headers) . '::' . $rule['header'];
			$defaults['test'] = $rule['type'];
			$defaults['header'] = $rule['header'];
			$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'size') {
			$display['op'] = 'display: none;';
			$display['sizeop'] = '';
			$display['units'] = '';

			$defaults['selheader'] = 'size::size';
			$defaults['header'] = 'size';
			$defaults['test'] = 'size';
			$defaults['sizeop'] = $rule['operator'];
			preg_match('/^([0-9]+)(K|M|G)*$/', $rule['target'], $matches);
			$defaults['target'] = $matches[1];
			$defaults['targetsize'] = 'short';
			$defaults['units'] = $matches[2];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'body') {
			$display['bodypart'] = '';
			$display['header'] = 'display: none;';

			$defaults['selheader'] = 'body::body';
			$defaults['header'] = 'body';
			$defaults['test'] = 'body';
			$defaults['bodypart'] = $rule['bodypart'];
			$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$defaults['target'] = $rule['target'];

			if ($rule['contentpart'] != '') {
				$defaults['advcontentpart'] = $rule['contentpart'];
				$display['advcontentpart'] = '';
			}
		}
		elseif (isset($rule['type']) && $rule['type'] == 'spamtest') {
			$display['op'] = 'display: none;';
			$display['target'] = 'display: none;';
			$display['spamtestop'] = '';
			$display['spamprob'] = '';

			$defaults['test'] = $rule['type'];
			$defaults['selheader'] = 'spamtest::spamtest';
			$defaults['header'] = 'spamtest';
			$defaults['spamtestop'] = $rule['operator'];
			$defaults['target'] = $rule['target'];
			$defaults['spamprobability'] = $rule['target'];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'virustest') {
			$display['op'] = 'display: none;';
			$display['target'] = 'display: none;';
			$display['spamtestop'] = '';
			$display['virusprob'] = '';

			$defaults['test'] = $rule['type'];
			$defaults['selheader'] = 'virustest::virustest';
			$defaults['header'] = 'virustest';
			$defaults['spamtestop'] = $rule['operator'];
			$defaults['target'] = $rule['target'];
			$defaults['virusprobability'] = $rule['target'];
		}
		elseif (isset($rule['type']) && $rule['type'] == 'date') {
			$display['op'] = 'display: none;';
			$display['dateop'] = '';
			$display['header'] = 'display: none;';
			$display['datepart'] = '';

			if ($rule['datepart'] == 'weekday') {
				$display['target'] = 'display: none;';
				$display['advtarget'] = 'display: none;';
				$display['weekdays'] = '';
				$display['advweekdays'] = '';
			}

			$defaults['test'] = $rule['type'];
			$defaults['selheader'] = 'date::' . $rule['header'];
			$defaults['header'] = $rule['header'];
			$defaults['datepart'] = $rule['datepart'];
			$defaults['dateop'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$defaults['target'] = $rule['target'];
		}
		elseif ((isset($rule['type']) && $rule['type'] != 'exists') && $this->_in_headerarray($rule['type'] . '::' . $rule['header'], $this->headers)) {
			$display['target'] = $rule['operator'] == 'exists' ? 'display: none;' : '';

			$defaults['selheader'] = $rule['type'] . '::' . $rule['header'];
			$defaults['test'] = $rule['type'];
			$defaults['header'] = $rule['header'];
			$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$defaults['target'] = $rule['target'];
		}
		elseif (isset($rule['type']) && $rule['type'] != 'true') {
			$display['header'] = '';
			$display['target'] = $rule['operator'] == 'exists' ? 'display: none;' : '';

			$defaults['selheader'] = 'header::other';
			$defaults['test'] = 'header';
			$defaults['header'] = is_array($rule['header']) ? join(', ', $rule['header']) : $rule['header'];
			$defaults['op'] = ($rule['not'] ? 'not' : '') . $rule['operator'];
			$defaults['target'] = $rule['target'];
		}

		// check for advanced options
		if (!in_array($defaults['op'], $this->standardops) || $rule['comparator'] != '' || $rule['contentpart'] != '') {
			$display['advancedopts'] = true;
			$display['target'] = 'display: none;';
		}

		// hide the "template" row
		if (!isset($rule))
			$rules_table->set_row_attribs(array('style' => 'display: none;'));

		// header select box
		$select_header = new html_select(array('name' => "_selheader[]", 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_header_select(this)'));
		foreach($this->headers as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_header->add($this->gettext($option['text']), $option['value']);
		}

		foreach($predefined_rules as $idx => $data)
			$select_header->add($data['name'], $data['type'] . '::predefined_' . $idx);

		$input_test = new html_hiddenfield(array('name' => '_test[]', 'value' => $defaults['test']));
		$rules_table->add('selheader', $select_header->show($defaults['selheader']) . $input_test->show());

		$help_button = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
		$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sieverules_xheaders(this);', 'title' => $this->gettext('sieveruleheaders'), 'style' => $display['header']), $help_button);

		// header input box
		$input_header = new html_inputfield(array('name' => '_header[]', 'style' => $display['header'], 'class' => 'short'));

		// bodypart select box
		$select_bodypart = new html_select(array('name' => '_bodypart[]', 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_bodypart_select(this)', 'style' => $display['bodypart']));
		foreach($this->bodyparts as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_bodypart->add($this->gettext($option['text']), $option['value']);
		}

		// datepart select box
		$select_datepart = new html_select(array('name' => '_datepart[]', 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_datepart_select(this)','style' => $display['datepart']));
		foreach($this->dateparts as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_datepart->add($this->gettext($option['text']), $option['value']);
		}

		// add header elements to UI
		$rules_table->add('header', $input_header->show($defaults['header']) . $help_button . $select_bodypart->show($defaults['bodypart']) . $select_datepart->show($defaults['datepart']));

		// header operators select box
		$select_op = new html_select(array('name' => "_operator[]", 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_rule_op_select(this)', 'style' => $display['op']));
		foreach($this->operators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_op->add($this->gettext($option['text']), $option['value']);
		}

		// size operators select box
		$select_size_op = new html_select(array('name' => "_size_operator[]", 'style' => $display['sizeop']));
		foreach($this->sizeoperators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_size_op->add($this->gettext($option['text']), $option['value']);
		}

		// date operators select box
		$select_date_op = new html_select(array('name' => "_date_operator[]", 'style' => $display['dateop']));
		foreach($this->dateoperators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_date_op->add($this->gettext($option['text']), $option['value']);
		}

		// spamtext operators select box
		$select_spamtest_op = new html_select(array('name' => "_spamtest_operator[]", 'style' => $display['spamtestop']));
		foreach($this->spamoperators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_spamtest_op->add($this->gettext($option['text']), $option['value']);
		}

		// add operator inputs to UI
		$rules_table->add('op', $select_op->show(($display['advancedopts'] ? 'advoptions' : $defaults['op'])) . $select_size_op->show($defaults['sizeop']) . $select_date_op->show($defaults['dateop']) . $select_spamtest_op->show($defaults['spamtestop']));

		// target input box
		$input_target = new html_inputfield(array('name' => '_target[]', 'style' => $display['target'], 'class' => $defaults['targetsize']));

		// size units select box
		$select_units = new html_select(array('name' => "_units[]", 'style' => $display['units'], 'class' => 'short'));
		foreach($this->sizeunits as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_units->add($this->gettext($option['text']), $option['value']);
		}

		// spam probability select box
		$select_spam_probability = new html_select(array('name' => "_spam_probability[]", 'style' => $display['spamprob'], 'class' => 'long'));
		foreach($this->spamprobability as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_spam_probability->add((strpos($option['text'], '%') > 0 ? $option['text'] : $this->gettext($option['text'])), $option['value']);
		}

		// virus probability select box
		$select_virus_probability = new html_select(array('name' => "_virus_probability[]", 'style' => $display['virusprob'], 'class' => 'long'));
		foreach($this->virusprobability as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_virus_probability->add($this->gettext($option['text']), $option['value']);
		}

		// weekday select box
		$select_weekdays = new html_select(array('name' => "_weekday[]", 'style' => $display['weekdays'], 'class' => 'long'));
		foreach($this->weekdays as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_weekdays->add($this->gettext($option['text']), $option['value']);
		}

		// add target/value and unit inputs to UI
		$rules_table->add('target', $select_weekdays->show($defaults['target']) . $select_spam_probability->show($defaults['spamprobability']) . $select_virus_probability->show($defaults['virusprobability']) . $input_target->show($defaults['target']) . "&nbsp;" . $select_units->show($defaults['units']));

		// add add/delete buttons to UI
		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_rule', 'type' => 'link', 'class' => 'add', 'title' => 'sieverules.addsieverule', 'content' => ' '));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_rule', 'type' => 'link', 'class' => 'delete', 'classact' => 'delete_act', 'title' => 'sieverules.deletesieverule', 'content' => ' '));
		$rules_table->add('control', $add_button . $delete_button);

		if (isset($rule))
			$rowid = $rules_table->size();
		else
			$rowid = 'rowid';

		// create "other headers" table
		$headers_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 4));
		$headers_table->add(array('colspan' => 4, 'style' => 'white-space: normal;'), rcmail::Q($this->gettext('sieveheadershlp')));

		$col1 = '';
		$col2 = '';
		$col3 = '';
		$col4 = '';
		$other_headers = $rcmail->config->get('sieverules_other_headers');
		sort($other_headers);
		$col_length = sizeof($other_headers) / 4;
		$col_length = ceil($col_length);
		foreach ($other_headers as $idx => $xheader) {
			$input_xheader = new html_radiobutton(array('id' => $xheader . '_' . $rowid, 'name' => '_xheaders_' . $rowid . '[]', 'value' => $xheader, 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_set_xheader(this)', 'class' => 'radio'));
			$xheader_show = $input_xheader->show($defaults['header']) . "&nbsp;" . html::label($xheader . '_' . $rowid, rcmail::Q($xheader));

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

		// add "other headers" table to UI
		$rules_table->set_row_attribs(array('style' => 'display: none;'));
		$rules_table->add(array('colspan' => 5), $headers_table->show());

		// create advanced options table
		$advanced_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
		$advanced_table->add(array('colspan' => 2, 'style' => 'white-space: normal;'), rcmail::Q($this->gettext('advancedoptions')));

		$help_button = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('contentpart'), 'border' => 0, 'style' => 'margin-left: 4px;'));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sieverules_help(this, ' . $advanced_table->size() . ');', 'title' => $this->gettext('contentpart')), $help_button);

		// input for body test content part
		$field_id = 'rcmfd_advcontentpart_'. $rowid;
		$advanced_table->set_row_attribs(array('style' => $display['advcontentpart']));
		$input_advcontentpart = new html_inputfield(array('id' => $field_id, 'name' => '_body_contentpart[]', 'class' => 'short'));
		// add to advanced UI
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, rcmail::Q($this->gettext('bodycontentpart'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advcontentpart->show($defaults['advcontentpart']) . $help_button);

		// add help message for content part input to advanced UI
		$advanced_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$advanced_table->add(array('colspan' => 2, 'class' => 'helpmsg'), $this->gettext('contentpartexp'));

		// advanced operator select box
		$field_id = 'rcmfd_advoperator_'. $rowid;
		$select_advop = new html_select(array('id' => $field_id, 'name' => "_advoperator[]", 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_rule_advop_select(this)'));
		foreach($this->advoperators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_advop->add($this->gettext($option['text']), $option['value']);
		}

		// add to advanced UI
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, rcmail::Q($this->gettext('operator'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $select_advop->show($defaults['op']));

		// comparator select box
		$field_id = 'rcmfd_comparator_'. $rowid;
		$select_comparator = new html_select(array('id' => $field_id, 'name' => "_comparator[]") + (substr($defaults['op'], 0, 5) == 'count' || substr($defaults['op'], 0, 5) == 'value' ? array() : array('disabled' => 'disabled')));
		foreach($this->comparators as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_comparator->add($this->gettext($option['text']), $option['value']);
		}

		// add to advanced UI
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, rcmail::Q($this->gettext('comparator'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $select_comparator->show($rule['comparator']));

		// advanced weekday select box
		$select_advweekdays = new html_select(array('name' => "_advweekday[]", 'style' => $display['advweekdays']));
		foreach($this->weekdays as $option) {
			if (empty($option['ext']) || in_array($option['ext'], $ext))
				$select_advweekdays->add($this->gettext($option['text']), $option['value']);
		}

		// advanced target input box
		$field_id = 'rcmfd_advtarget_'. $rowid;
		$input_advtarget = new html_inputfield(array('id' => $field_id, 'name' => '_advtarget[]', 'style' => $display['advtarget']));

		// add to advanced target and weekday select to advanced UI
		$advanced_table->add(array('style' => 'white-space: normal;', 'class' => 'selheader'), html::label($field_id, rcmail::Q($this->gettext('teststring'))));
		$advanced_table->add(array('style' => 'white-space: normal;'), $input_advtarget->show($defaults['target']) . $select_advweekdays->show($defaults['target']));

		if (!($display['advancedopts'] && $predefined == -1))
			$rules_table->set_row_attribs(array('style' => 'display: none;'));

		// add advanced UI to main UI
		$rules_table->add(array('colspan' => 5), $advanced_table->show());

		return $rules_table;
	}

	private function _action_row($ext, $actions_table, $rowid, $action, $attrib, $example)
	{
		$rcmail = rcube::get_instance();
		$help_icon = html::img(array('src' => $attrib['helpicon'], 'alt' => $this->gettext('messagehelp'), 'border' => 0));

		// set default field display
		$display = array(
			'vacadv' => ($action['type'] == 'vacation' && $this->force_vacto) ? '' : 'display: none;',
			'vacfrom' => ($this->show_vacfrom) ? $display['vacadv'] : 'display: none;',
			'vachandle' => ($this->show_vachandle) ? $display['vacadv'] : 'display: none;',
			'noteadv' => 'display: none;',
			'eheadadv' => 'display: none;'
		);

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
		if (in_array('imap4flags', $ext) && ($config_actions['imapflags'] || $action['type'] == 'imap4flags'))
			$allowed_actions['imap4flags'] = $this->gettext('messageimapflags');
		elseif (in_array('imapflags', $ext) && ($config_actions['imapflags'] || $action['type'] == 'imapflags'))
			$allowed_actions['imapflags'] = $this->gettext('messageimapflags');
		if (in_array('notify', $ext) && ($config_actions['notify'] || $action['type'] == 'notify'))
			$allowed_actions['notify'] = $this->gettext('messagenotify');
		elseif (in_array('enotify', $ext) && ($config_actions['notify'] || $action['type'] == 'enotify'))
			$allowed_actions['enotify'] = $this->gettext('messagenotify');
		if (in_array('editheader', $ext) && ($config_actions['editheaderadd'] || $action['type'] == 'editheaderadd'))
			$allowed_actions['editheaderadd'] = $this->gettext('addheader');
		if (in_array('editheader', $ext) && ($config_actions['editheaderrem'] || $action['type'] == 'editheaderrem'))
			$allowed_actions['editheaderrem'] = $this->gettext('removeheader');
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

		// set the default values
		reset($allowed_actions);

		$defaults = array(
			'method' => key($allowed_actions),
			'folder' => 'INBOX',
			'reject' => '',
			'vacto' => null,
			'address' => '',
			'period' => '',
			'periodtype' => '',
			'handle' => '',
			'subject' => '',
			'origsubject' => '',
			'msg' => '',
			'charset' => RCUBE_CHARSET,
			'flags' => '',
			'nfrom' => '',
			'nimpt' => '',
			'nmethod' => '',
			'noptions' => '',
			'nmsg' => ''
		);

		// set default identity for use in vacation action
		$identity = $rcmail->user->get_identity();
		if ($this->show_vacfrom)
			$defaults['vacfrom'] = (in_array('variables', $ext)) ? 'auto' : $identity['email'];
		else
			$defaults['vacfrom'] = null;

		// apply current action values
		if ($action['type'] == 'fileinto' || $action['type'] == 'fileinto_copy') {
			$defaults['method'] = $action['type'];
			$defaults['folder'] = $action['target'];

			if ($rcmail->config->get('sieverules_folder_encoding'))
				$defaults['folder'] = rcube_charset::convert($defaults['folder'], $rcmail->config->get('sieverules_folder_encoding'), 'UTF7-IMAP');

			if ($rcmail->config->get('sieverules_folder_delimiter'))
				$defaults['folder'] = str_replace($rcmail->config->get('sieverules_folder_delimiter'), $rcmail->storage->get_hierarchy_delimiter(), $defaults['folder']);

			$defaults['folder'] = $rcmail->config->get('sieverules_include_imap_root', true) ? $defaults['folder'] : $rcmail->storage->mod_folder($defaults['folder'], 'IN');
		}
		elseif ($action['type'] == 'reject' || $action['type'] == 'ereject') {
			$defaults['method'] = $action['type'];
			$defaults['reject'] = $action['target'];
		}
		elseif ($action['type'] == 'vacation') {
			$defaults['method'] = 'vacation';

			if (isset($action['seconds'])) {
				$defaults['period'] = $action['seconds'];
				$defaults['periodtype'] = 'seconds';
			}
			else {
				$defaults['period'] = $action['days'];
				$defaults['periodtype'] = 'days';
			}

			$defaults['vacfromdefault'] = $defaults['vacfrom'];
			$defaults['vacfrom'] = $action['from'];
			$defaults['vacto'] = $action['addresses'];
			$defaults['handle'] = $action['handle'];
			$defaults['subject'] = $action['subject'];
			$defaults['origsubject'] = $action['origsubject'];
			$defaults['msg'] = $action['msg'];
			$defaults['htmlmsg'] = $action['htmlmsg'] ? '1' : '';
			$defaults['charset'] = $action['charset'];

			if ($defaults['htmlmsg'] == '1' && $rcmail->config->get('htmleditor') == '0') {
				$h2t = new rcube_html2text($defaults['msg'], false, true, 0);
				$defaults['msg'] = $h2t->get_text();
				$defaults['htmlmsg'] = '';
			}
			elseif ($defaults['htmlmsg'] == '' && $rcmail->config->get('htmleditor') == '1') {
				$defaults['msg'] = $defaults['msg'];
				$defaults['msg'] = nl2br($defaults['msg']);
				$defaults['htmlmsg'] = '1';
			}

			if (!$example)
				$this->force_vacto = false;

			// check advanced enabled
			if ((!empty($defaults['vacfrom']) && $defaults['vacfrom'] != $defaults['vacfromdefault']) || !empty($defaults['vacto']) || !empty($defaults['handle']) || !empty($defaults['period']) || $defaults['charset'] != RCUBE_CHARSET || $this->force_vacto) {
				$display['vacadv'] = '';
				$display['vacfrom'] = ($this->show_vacfrom) ? '' : 'display: none;';
				$display['vachandle'] = ($this->show_vachandle) ? '' : 'display: none;';
			}
		}
		elseif ($action['type'] == 'redirect' || $action['type'] == 'redirect_copy') {
			$defaults['method'] = $action['type'];
			$defaults['address'] = $action['target'];
		}
		elseif ($action['type'] == 'imapflags' || $action['type'] == 'imap4flags') {
			$defaults['method'] = $action['type'];
			$defaults['flags'] = $action['target'];
		}
		elseif ($action['type'] == 'notify' || $action['type'] == 'enotify') {
			$defaults['method'] = $action['type'];
			$defaults['nfrom'] = $action['from'];
			$defaults['nimpt'] = $action['importance'];
			$defaults['nmethod'] = $action['method'];
			$defaults['noptions'] = $action['options'];
			$defaults['nmsg'] = $action['msg'];

			// check advanced enabled
			if (!empty($defaults['nfrom']) || !empty($defaults['nimpt']))
				$display['noteadv'] = '';
		}
		elseif ($action['type'] == 'editheaderadd' || $action['type'] == 'editheaderrem') {
			$defaults['method'] = $action['type'];
			$defaults['headername'] = $action['name'];
			$defaults['headerval'] = $action['value'];
			$defaults['headerindex'] = $action['index'];
			$defaults['headerop'] = $action['operator'];

			if ($action['type'] == 'editheaderrem' && (!empty($defaults['headerindex']) || !empty($defaults['headerval'])))
				$display['eheadadv'] = '';
		}
		elseif ($action['type'] == 'discard' || $action['type'] == 'keep' || $action['type'] == 'stop') {
			$defaults['method'] = $action['type'];
		}

		// hide the "template" row
		if (!isset($action))
			$actions_table->set_row_attribs(array('style' => 'display: none;'));

		// action type select box
		$select_action = new html_select(array('name' => "_act[]", 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_action_select(this)'));
		foreach ($allowed_actions as $value => $text)
			$select_action->add($text, $value);

		// add action type to UI
		$actions_table->add('action', $select_action->show($defaults['method']));

		$vacs_table = $this->_vacation_table($ext, $rowid, $defaults, $display, $help_icon);

		// begin notify action
		$notify_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => ($defaults['method'] == 'notify' || $defaults['method'] == 'enotify') ? '' : 'display: none;'));

		if (count($this->identities)) {
			$field_id = 'rcmfd_sievenotifyfrom_'. $rowid;
			$select_id = new html_select(array('id' => $field_id, 'name' => "_nfrom[]"));
			$select_id->add($this->gettext('autodetect'), "");

			foreach ($this->identities as $sql_arr) {
				// find currently selected from address
				if ($defaults['nfrom'] != '' && $defaults['nfrom'] == rcmail::Q($sql_arr['from']['string']))
					$defaults['nfrom'] = $sql_arr['identity_id'];
				elseif ($defaults['nfrom'] != '' && $defaults['nfrom'] == $sql_arr['from']['mailto'])
					$defaults['nfrom'] = $sql_arr['identity_id'];

				$select_id->add($sql_arr['from']['disp_string'], $sql_arr['identity_id']);
			}

			$notify_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['noteadv']));
			$notify_table->add(null, html::label($field_id, rcmail::Q($this->gettext('sievefrom'))));
			$notify_table->add(array('colspan' => 2), $select_id->show($defaults['nfrom']));
		}

		$field_id = 'rcmfd_nmethod_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_nmethod[]'));
		$notify_table->add(null, html::label($field_id, rcmail::Q($this->gettext('method'))));
		$notify_table->add(array('colspan' => 2), $input_method->show($defaults['nmethod']));

		$field_id = 'rcmfd_noption_'. $rowid;
		$input_method = new html_inputfield(array('id' => $field_id, 'name' => '_noption[]'));
		$notify_table->add(null, html::label($field_id, rcmail::Q($this->gettext('options'))));
		$notify_table->add(array('colspan' => 2), $input_method->show($defaults['noptions']));

		$notify_table->set_row_attribs(array('style' => 'display: none;'));
		$notify_table->add(array('colspan' => 3, 'class' => 'helpmsg'), $this->gettext('nmethodexp'));

		$field_id = 'rcmfd_nimpt_'. $rowid;
		$input_importance = new html_radiobutton(array('id' => $field_id . '_none', 'name' => '_notify_radio_' . $rowid, 'value' => 'none', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')', 'class' => 'radio'));
		$importance_show = $input_importance->show($defaults['nimpt']) . "&nbsp;" . html::label($field_id . '_none', rcmail::Q($this->gettext('importancen')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_1', 'name' => '_notify_radio_' . $rowid, 'value' => '1', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')', 'class' => 'radio'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($defaults['nimpt']) . "&nbsp;" . html::label($field_id . '_1', rcmail::Q($this->gettext('importance1')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_2', 'name' => '_notify_radio_' . $rowid, 'value' => '2', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')', 'class' => 'radio'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($defaults['nimpt']) . "&nbsp;" . html::label($field_id . '_2', rcmail::Q($this->gettext('importance2')));
		$input_importance = new html_radiobutton(array('id' => $field_id . '_3', 'name' => '_notify_radio_' . $rowid, 'value' => '3', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_notify_impt(this, '. $rowid .')', 'class' => 'radio'));
		$importance_show .= '&nbsp;&nbsp;' . $input_importance->show($defaults['nimpt']) . "&nbsp;" . html::label($field_id . '_3', rcmail::Q($this->gettext('importance3')));
		$input_importance = new html_hiddenfield(array('id' => 'rcmfd_sievenimpt_'. $rowid, 'name' => '_nimpt[]'));

		$notify_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['noteadv']));
		$notify_table->add(null, rcmail::Q($this->gettext('flag')));
		$notify_table->add(array('colspan' => 2), $importance_show . $input_importance->show($defaults['nimpt']));

		$field_id = 'rcmfd_nmsg_'. $rowid;
		$input_msg = new html_inputfield(array('id' => $field_id, 'name' => '_nmsg[]'));
		$notify_table->add(null, html::label($field_id, rcmail::Q($this->gettext('message'))));
		$notify_table->add(array('colspan' => 2), $input_msg->show($defaults['nmsg']));

		if (in_array('enotify', $ext)) {
			$input_advopts = new html_checkbox(array('id' => 'nadvopts' . $rowid, 'name' => '_nadv_opts[]', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1', 'class' => 'checkbox'));
			$notify_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('nadvopts' . $rowid, rcmail::Q($this->gettext('advancedoptions'))) . $input_advopts->show(($display['noteadv'] == '' ? 1 : 0)));
		}

		// begin editheader action
		$headers_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 2, 'style' => ($defaults['method'] == 'editheaderadd' || $defaults['method'] == 'editheaderrem') ? '' : 'display: none;'));

		$field_id = 'rcmfd_eheadname_'. $rowid;
		$input_header = new html_inputfield(array('id' => $field_id, 'name' => '_eheadname[]'));
		$headers_table->add(null, html::label($field_id, rcmail::Q($this->gettext('headername'))));
		$headers_table->add(null, $input_header->show($defaults['headername']));

		$field_id = 'rcmfd_eheadindex_'. $rowid;
		$select_index = new html_select(array('id' => $field_id, 'name' => "_eheadindex[]"));
		$select_index->add($this->gettext('headerdelall'), "");
		$select_index->add("1", "1");
		$select_index->add("2", "2");
		$select_index->add("3", "3");
		$select_index->add("4", "4");
		$select_index->add("5", "5");
		$select_index->add($this->gettext('last'), "last");

		$headers_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['eheadadv']));
		$headers_table->add(null, html::label($field_id, rcmail::Q($this->gettext('headerindex'))));
		$headers_table->add(null, $select_index->show($defaults['headerindex']));

		$field_id = 'rcmfd_eheadopp_'. $rowid;
		$select_match = new html_select(array('id' => $field_id, 'name' => "_eheadopp[]"));
		$select_match->add($this->gettext('filteris'), "");
		$select_match->add($this->gettext('filtercontains'), "contains");

		$headers_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['eheadadv']));
		$headers_table->add(null, html::label($field_id, rcmail::Q($this->gettext('operator'))));
		$headers_table->add(null, $select_match->show($defaults['headerop']));

		$field_id = 'rcmfd_eheadval_'. $rowid;
		$input_header = new html_inputfield(array('id' => $field_id, 'name' => '_eheadval[]'));

		if ($defaults['method'] == 'editheaderrem')
			$headers_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['eheadadv']));

		$headers_table->add(null, html::label($field_id, rcmail::Q($this->gettext('headervalue'))));
		$headers_table->add(null, $input_header->show($defaults['headerval']));

		if ($defaults['method'] == 'editheaderrem')
			$headers_table->set_row_attribs(array('style' => 'display: none;'));

		$field_id = 'rcmfd_eheadaddlast_'. $rowid;
		$input_index = new html_checkbox(array('id' => $field_id, 'value' => 'last', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_toggle_eheadlast(this);', 'name' => '_eheadaddlast[]', 'class' => 'checkbox'));
		$headers_table->add(null, '&nbsp;');
		$headers_table->add(null, $input_index->show($defaults['headerindex']) . "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('headerappend'))));

		if ($defaults['method'] == 'editheaderadd')
			$headers_table->set_row_attribs(array('style' => 'display: none;'));

		$input_advopts = new html_checkbox(array('id' => 'hadvopts' . $rowid, 'name' => '_hadv_opts[]', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1', 'class' => 'checkbox'));
		$headers_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('nadvopts' . $rowid, rcmail::Q($this->gettext('advancedoptions'))) . $input_advopts->show(($display['eheadadv'] == '' ? 1 : 0)));

		// begin fileinto action
		$mbox_name = $rcmail->storage->get_folder();
		$input_folderlist = new html_select(array('name' => '_folder[]', 'onchange' => rcmail_output::JS_OBJECT_NAME . '.sieverules_select_folder(this);', 'style' => ($defaults['method'] == 'fileinto' || $defaults['method'] == 'fileinto_copy') ? '' : 'display: none;', 'is_escaped' => true));
		$rcmail->render_folder_tree_select($this->mailboxes, $mbox_name, 100, $input_folderlist, false);

		$show_customfolder = 'display: none;';
		if ($rcmail->config->get('sieverules_fileinto_options', 0) == 2 && !$rcmail->storage->folder_exists($defaults['folder'])) {
			$customfolder = rcube_charset::convert($rcmail->storage->mod_folder($defaults['folder']), 'UTF7-IMAP');
			$defaults['folder'] = '@@newfolder';
			$show_customfolder = '';
		}

		$input_customfolder = new html_inputfield(array('name' => '_customfolder[]'));
		$otherfolders = html::span(array('id' => 'customfolder_rowid', 'style' => $show_customfolder), '<br />' . $input_customfolder->show($customfolder));

		// begin redirect action
		$input_address = new html_inputfield(array('name' => '_redirect[]', 'style' => ($defaults['method'] == 'redirect' || $defaults['method'] == 'redirect_copy') ? '' : 'display: none;'));

		// begin reject action
		$input_reject = new html_textarea(array('name' => '_reject[]', 'rows' => '5', 'cols' => '40', 'style' => ($defaults['method'] == 'reject' || $defaults['method'] == 'ereject') ? '' : 'display: none;'));

		// begin imapflags action
		$input_imapflags = new html_select(array('name' => '_imapflags[]', 'style' => ($defaults['method'] == 'imapflags' || $defaults['method'] == 'imap4flags') ? '' : 'display: none;'));
		foreach($this->flags as $name => $val)
			$input_imapflags->add($this->gettext($name), $val);

		// add actions to UI
		$actions_table->add('folder', $input_folderlist->show($defaults['folder']) . $otherfolders . $input_address->show($defaults['address']) . $vacs_table->show() . $notify_table->show() . $input_imapflags->show($defaults['flags']) . $input_reject->show($defaults['reject']) . $headers_table->show());

		// add add/delete buttons to UI (if enabled)
		$add_button = $this->api->output->button(array('command' => 'plugin.sieverules.add_action', 'type' => 'link', 'class' => 'add', 'title' => 'sieverules.addsieveact', 'content' => ' '));
		$delete_button = $this->api->output->button(array('command' => 'plugin.sieverules.del_action', 'type' => 'link', 'class' => 'delete', 'classact' => 'delete_act', 'title' => 'sieverules.deletesieveact', 'content' => ' '));

		if ($rcmail->config->get('sieverules_multiple_actions'))
			$actions_table->add('control', $add_button . $delete_button);
		else
			$actions_table->add('control', "&nbsp;");

		return $actions_table;
	}

	protected function _vacation_table($ext, $rowid, $defaults, $display, $help_icon)
	{
		$rcmail = rcube::get_instance();

		// begin vacation action
		$vacs_table = new html_table(array('class' => 'records-table', 'cellspacing' => '0', 'cols' => 3, 'style' => ($defaults['method'] == 'vacation') ? '' : 'display: none;'));

		$to_addresses = "";
		$vacto_arr = explode(",", $defaults['vacto']);
		$field_id_vacfrom = 'rcmfd_sievevacfrom_'. $rowid;
		$field_id_vacto = 'rcmfd_sievevacto_'. $rowid;
		if (count($this->identities)) {
			$select_id = new html_select(array('id' => $field_id_vacfrom, 'name' => "_vacfrom[]", 'class' => 'short', 'onchange' => rcmail_output::JS_OBJECT_NAME . '.enable_sig(this);'));

			if ($this->show_vacfrom && in_array('variables', $ext))
				$select_id->add($this->gettext('autodetect'), "auto");
			elseif (!$this->show_vacfrom)
				$select_id->add($this->gettext('autodetect'), "");

			foreach ($this->identities as $sql_arr) {
				// find currently selected from address
				if ($defaults['vacfrom'] != '' && $defaults['vacfrom'] == rcmail::Q($sql_arr['from']['string']))
					$defaults['vacfrom'] = $sql_arr['identity_id'];
				elseif ($defaults['vacfrom'] != '' && $defaults['vacfrom'] == $sql_arr['from']['mailto'])
					$defaults['vacfrom'] = $sql_arr['identity_id'];

				$select_id->add($sql_arr['from']['disp_string'], $sql_arr['identity_id']);

				$ffield_id = 'rcmfd_vac_' . $rowid . '_' . $sql_arr['identity_id'];

				if ($this->force_vacto) {
					$curaddress = $sql_arr['email'];
					$defaults['vacto'] .= (!empty($defaults['vacto']) ? ',' : '') . $sql_arr['email'];
				}
				else {
					$curaddress = in_array($sql_arr['email'], $vacto_arr) ? $sql_arr['email'] : "";
				}

				$input_address = new html_checkbox(array('id' => $ffield_id, 'name' => '_vacto_check_' . $rowid . '[]', 'value' => $sql_arr['email'], 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_toggle_vac_to(this, '. $rowid .')', 'class' => 'checkbox'));
				$to_addresses .= $input_address->show($curaddress) . "&nbsp;" . html::label($ffield_id, rcmail::Q($sql_arr['email'])) . "<br />";
			}
		}

		// deduplicate vacto list
		$tmparr = explode(",", $defaults['vacto']);
		$tmparr = array_unique($tmparr);
		$defaults['vacto'] = implode(",", $tmparr);

		if ($rcmail->config->get('sieverules_limit_vacto', true) && strlen($to_addresses) > 0) {
			$vacfrom_input = $select_id->show($defaults['vacfrom']);
			$input_vacto = new html_hiddenfield(array('id' => $field_id_vacto, 'name' => '_vacto[]', 'value' => $defaults['vacto']));
			$vacto_input = $to_addresses . $input_vacto->show();
			$vac_help = $this->gettext('vactoexp');
		}
		else {
			$input_vacfrom = new html_inputfield(array('id' => $field_id_vacfrom, 'name' => '_vacfrom[]'));
			$vacfrom_input = $input_vacfrom->show($defaults['vacfrom']);
			$input_vacto = new html_inputfield(array('id' => $field_id_vacto, 'name' => '_vacto[]', 'class' => 'short'));
			$vacto_input = $input_vacto->show($defaults['vacto']);
			$vac_help = $this->gettext('vactoexp') . '<br /><br />' . $this->gettext('vactoexp_adv');
		}

		// from param
		$vacs_table->set_row_attribs(array('class' => ($this->show_vacfrom) ? 'advanced' : 'disabled', 'style' => $display['vacfrom']));
		$vacs_table->add(null, html::label($field_id_vacfrom, rcmail::Q($this->gettext('from'))));
		$vacs_table->add(null, $vacfrom_input);

		$sig_button = $this->api->output->button(array('command' => 'plugin.sieverules.vacation_sig', 'prop' => $rowid, 'type' => 'link', 'class' => 'vacsig', 'classact' => 'vacsig_act', 'title' => 'insertsignature', 'content' => ' '));
		$vacs_table->add(null, $sig_button);

		// to param
		$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['vacadv']));
		$vacs_table->add(array('style' => 'vertical-align: top;'), html::label($field_id_vacto, rcmail::Q($this->gettext('sieveto'))));
		$vacs_table->add(null, $vacto_input);

		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . rcmail_output::JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(array('style' => 'vertical-align: top;'), $help_button);
		$vacs_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'helpmsg'), $vac_help);

		$field_id = 'rcmfd_sievevacperiod_'. $rowid;
		$input_period = new html_inputfield(array('id' => $field_id, 'name' => '_period[]', 'class' => 'short'));
		$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['vacadv']));
		$vacs_table->add(null, html::label($field_id, rcmail::Q($this->gettext('period'))));
		$vacs_table->add(null, $input_period->show($defaults['period']));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . rcmail_output::JS_OBJECT_NAME . '.sieverules_help(this, ' . (in_array('vacation-seconds', $ext) ? $vacs_table->size() + 1 : $vacs_table->size()) . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		if (in_array('vacation-seconds', $ext)) {
			$input_periodtype = new html_radiobutton(array('id' => $field_id . '_days', 'name' => '_period_radio_' . $rowid, 'value' => 'days', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_period_type(this, '. $rowid .')', 'class' => 'radio'));
			$period_type_show = $input_periodtype->show($defaults['periodtype']) . "&nbsp;" . html::label($field_id . '_days', rcmail::Q($this->gettext('days')));
			$input_periodtype = new html_radiobutton(array('id' => $field_id . '_seconds', 'name' => '_period_radio_' . $rowid, 'value' => 'seconds', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_period_type(this, '. $rowid .')', 'class' => 'radio'));
			$period_type_show .= '&nbsp;&nbsp;' . $input_periodtype->show($defaults['periodtype']) . "&nbsp;" . html::label($field_id . '_seconds', rcmail::Q($this->gettext('seconds')));
			$input_periodtype = new html_hiddenfield(array('id' => 'rcmfd_sievevacperiodtype_'. $rowid, 'name' => '_periodtype[]'));

			$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['vacadv']));
			$vacs_table->add(null, '&nbsp;');
			$vacs_table->add(null, $period_type_show . $input_periodtype->show($defaults['periodtype']));
			$vacs_table->add(null, '&nbsp;');
		}

		$vacs_table->set_row_attribs(array('style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'helpmsg'), $this->gettext('vacperiodexp'));

		$field_id = 'rcmfd_sievevachandle_'. $rowid;
		$input_handle = new html_inputfield(array('id' => $field_id, 'name' => '_handle[]', 'class' => 'short'));
		$vacs_table->set_row_attribs(array('class' => ($this->show_vachandle) ? 'advanced' : 'disabled', 'style' => $display['vachandle']));
		$vacs_table->add(null, html::label($field_id, rcmail::Q($this->gettext('sievevachandle'))));
		$vacs_table->add(null, $input_handle->show($defaults['handle']));
		$help_button = html::a(array('href' => "#", 'onclick' => 'return ' . rcmail_output::JS_OBJECT_NAME . '.sieverules_help(this, ' . $vacs_table->size() . ');', 'title' => $this->gettext('messagehelp')), $help_icon);
		$vacs_table->add(null, $help_button);

		$vacs_table->set_row_attribs(array('class' => 'advhelp', 'style' => 'display: none;'));
		$vacs_table->add(array('colspan' => 3, 'class' => 'helpmsg'), $this->gettext('vachandleexp'));

		$field_id = 'rcmfd_sievevacsubject_'. $rowid;
		$input_subject = new html_inputfield(array('id' => $field_id, 'name' => '_subject[]'));
		$vacs_table->add(null, html::label($field_id, rcmail::Q($this->gettext('subject'))));
		$vacs_table->add(array('colspan' => 2), $input_subject->show($defaults['subject']));

		if (in_array('variables', $ext)) {
			$field_id = 'rcmfd_sievevacsubject_orig_'. $rowid;
			$input_origsubject = new html_checkbox(array('id' => $field_id, 'value' => '1', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_toggle_vac_osubj(this, '. $rowid .')', 'class' => 'checkbox'));
			$input_vacosubj = new html_hiddenfield(array('id' => 'rcmfd_sievevactoh_'. $rowid, 'name' => '_orig_subject[]', 'value' => $defaults['origsubject']));
			$vacs_table->add(null, '&nbsp;');
			$vacs_table->add(array('colspan' => 2), $input_origsubject->show($defaults['origsubject']) . "&nbsp;" . html::label($field_id, rcmail::Q($this->gettext('sieveorigsubj'))) . $input_vacosubj->show());
		}

		$field_id = 'rcmfd_sievevacmag_'. $rowid;
		$input_msg = new html_textarea(array('id' => $field_id, 'name' => '_msg[]', 'rows' => '8', 'cols' => '40', 'class' => $defaults['htmlmsg'] == 1 ? 'mce_editor' : '', 'is_escaped' => $defaults['htmlmsg'] == 1 ? true : null));
		$input_html = new html_checkbox(array('id' => 'rcmfd_sievevachtmlcb_'. $rowid, 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_toggle_vac_html(this, '. $rowid .', \'' . $field_id .'\');', 'value' => '1', 'class' => 'checkbox'));
		$input_htmlhd = new html_hiddenfield(array('id' => 'rcmfd_sievevachtmlhd_'. $rowid, 'name' => '_htmlmsg[]', 'value' => $defaults['htmlmsg']));
		$vacs_table->add('msg', html::label($field_id, rcmail::Q($this->gettext('message'))));
		$vacs_table->add(array('colspan' => 2), $input_msg->show($defaults['msg']) . html::tag('div', in_array('htmleditor', $rcmail->config->get('dont_override')) ? array('style' => 'display: none;') : null, $input_html->show($defaults['htmlmsg']) . "&nbsp;" . html::label('rcmfd_sievevachtmlcb_' . $rowid, rcmail::Q($this->gettext('htmlmessage')))) . $input_htmlhd->show());

		$field_id = 'rcmfd_sievecharset_'. $rowid;
		$vacs_table->set_row_attribs(array('class' => 'advanced', 'style' => $display['vacadv']));
		$vacs_table->add(null, html::label($field_id, rcmail::Q($this->gettext('charset'))));
		$vacs_table->add(array('colspan' => 2), $rcmail->output->charset_selector(array('id' => $field_id, 'name' => '_vaccharset[]', 'selected' => $defaults['charset'])));

		$input_advopts = new html_checkbox(array('id' => 'vadvopts' . $rowid, 'name' => '_vadv_opts[]', 'onclick' => rcmail_output::JS_OBJECT_NAME . '.sieverules_show_adv(this);', 'value' => '1', 'class' => 'checkbox'));
		$vacs_table->add(array('colspan' => '3', 'style' => 'text-align: right'), html::label('vadvopts' . $rowid, rcmail::Q($this->gettext('advancedoptions'))) . $input_advopts->show(($display['vacadv'] == '' ? 1 : 0)));

		return $vacs_table;
	}

	private function _rule_list_parts($idx, $script)
	{
		$parts = array();
		$output = is_a($this->api->output, 'rcmail_output_html') ? $this->api->output : new rcmail_output_html('settings');

		$parts['name'] = $script['name'] . ($script['disabled'] == 1 ? ' (' . $this->gettext('disabled') . ')' : '');

		$up_link = $output->button(array('command' => 'plugin.sieverules.move', 'prop' => ($idx - 1), 'type' => 'link', 'class' => 'up_arrow', 'title' => 'sieverules.moveup', 'content' => ' '));
		$down_link = $output->button(array('command' => 'plugin.sieverules.move', 'prop' => ($idx + 2), 'type' => 'link', 'class' => 'down_arrow', 'title' => 'sieverules.movedown', 'content' => ' '));

		$parts['control'] = $down_link . $up_link;

		return $parts;
	}

	private function _in_headerarray($needle, $haystack)
	{
		foreach ($haystack as $data) {
			$args = (strpos($needle, "::") > 0 ? array($data['value'], $data['value']) : explode("::", $data['value']));
			if ($args[1] == $needle)
				return $args[0];
		}

		return false;
	}

	private function _strip_val($str, $allow_html = false, $trim = true)
	{
		$str = !$allow_html ? htmlspecialchars_decode($str) : $str;
		$str = $trim ? trim($str) : $str;

		return $str;
	}

	// get identity record
	protected function _rcmail_get_identity($id)
	{
		$rcmail = rcube::get_instance();

		if ($sql_arr = $rcmail->user->get_identity($id)) {
			$out = $sql_arr;
			$out['mailto'] = $sql_arr['email'];
			$out['string'] = format_email_recipient($sql_arr['email'], rcube_charset::convert($sql_arr['name'], RCUBE_CHARSET, $this->api->output->get_charset()));

			if ($rcmail->config->get('sieverules_from_format', 0) == 1) {
				$out['disp_string'] = $out['string'];
				$out['val_string'] = $out['string'];
			}
			else {
				$out['disp_string'] = $out['mailto'];
				$out['val_string'] = $out['mailto'];
			}

			return $out;
		}

		return false;
	}

	private function _add_to_array(&$current, $new)
	{
		if (!is_array($current)) {
			$current[] = $new;
		}
		else {
			foreach ($current as $item) {
				if (!array_diff($item, $new)) {
					return;
				}
			}

			$current[] = $new;
		}
	}
}

?>