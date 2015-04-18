/**
 * SieveRules plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2014 Philip Weir
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

function rcmail_sieverules() {
	if (!rcmail.env.uid && (!rcmail.message_list || !rcmail.message_list.get_selection().length))
		return;

	var uids = rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.get_selection();

	var lock = rcmail.set_busy(true, 'loading');
	rcmail.http_post('plugin.sieverules.add_rule', rcmail.selection_post_data({_uid: uids}), lock);
}

$(document).ready(function() {
	if (window.rcmail) {
		rcmail.addEventListener('init', function(evt) {
			rcmail.register_command('plugin.sieverules.create', rcmail_sieverules, rcmail.env.uid);

			if (rcmail.message_list) {
				rcmail.message_list.addEventListener('select', function(list) {
					rcmail.enable_command('plugin.sieverules.create', list.selection.length > 0);
				});
			}
		});
	}
});