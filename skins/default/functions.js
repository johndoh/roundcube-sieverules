/**
 * SieveRules plugin default skin script
 */

function sieverules_init_ui() {
	rcmail_ui = new rcube_mail_ui();
	rcmail_ui.popupmenus.sieverulesmenu = 'sieverulesactionsmenu';
	rcmail_ui.sieverulesmenu = $('#sieverulesactionsmenu');

	rcmail_ui.body_mouseup_seiverules = function(evt, p) {
		var target = rcube_event.get_target(evt);

		if (rcmail_ui.sieverulesmenu && rcmail_ui.sieverulesmenu.is(':visible') && target != rcube_find_object('sieverulesactionslink'))
			rcmail_ui.show_popupmenu($('#sieverulesactionsmenu'), 'sieverulesactionslink', false, true);
	}

 	rcube_event.add_listener({ object:rcmail_ui, method:'body_mouseup_seiverules', event:'mouseup' });
}