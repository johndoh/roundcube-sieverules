/**
 * SieveRules plugin script
 */

rcube_webmail.prototype.sieverules_select = function(list) {
	if (rcmail.sieverules_examples) rcmail.sieverules_examples.clear_selection();
	var id;

	if (this.sieverules_timer)
		clearTimeout(rcmail.sieverules_timer);

	if (id = list.get_single_selection())
		rcmail.sieverules_timer = window.setTimeout(function() { rcmail.sieverules_load(id, 'plugin.sieverules.edit'); }, 200);
}

rcube_webmail.prototype.sieverules_keypress = function(list) {
	if (list.key_pressed == list.DELETE_KEY)
		rcmail.command('plugin.sieverules.delete');
	else if (list.key_pressed == list.BACKSPACE_KEY)
		rcmail.command('plugin.sieverules.delete');
}

rcube_webmail.prototype.sieverules_ex_select = function(list) {
	rcmail.sieverules_list.clear_selection();
	if (list.multi_selecting)
		return false;

	if (this.sieverules_timer)
		clearTimeout(this.sieverules_timer);

	var id;
	if (id = list.get_single_selection())
		rcmail.sieverules_timer = window.setTimeout(function() { rcmail.sieverules_load(id, 'plugin.sieverules.add'); }, 200);
}

rcube_webmail.prototype.sieverules_mouse_up = function(e) {
	if (rcmail.sieverules_list) {
		if (!rcube_mouse_is_over(e, rcmail.sieverules_list.list))
			rcmail.sieverules_list.blur();
	}

	if (rcmail.sieverules_examples) {
		if (!rcube_mouse_is_over(e, rcmail.sieverules_examples.list))
			rcmail.sieverules_examples.blur();
	}

	// handle mouse release when dragging
	if (rcmail.sieverules_ex_drag_active && rcmail.sieverules_list && rcmail.env.sieverules_last_target) {
		rcmail.command('plugin.sieverules.import_ex');
		rcmail.sieverules_examples.draglayer.hide();
	}
	else if (rcmail.sieverules_drag_active && rcmail.sieverules_list && rcmail.env.sieverules_last_target) {
		var _src = rcmail.sieverules_list.get_single_selection();

		if (rcmail.env.sieverules_last_target == 'end') {
			var _dst = rcmail.sieverules_list.rows.length;
			$(rcmail.gui_objects.sieverules_list).children('tbody').children('tr:last').removeClass('droptargetend');
		}
		else {
			var _dst = rcmail.env.sieverules_last_target.substr(6);
			$('#' + rcmail.env.sieverules_last_target).removeClass('droptarget');
		}

		rcmail.command('plugin.sieverules.move', { source:_src, dest:_dst });
		rcmail.sieverules_list.draglayer.hide();
	}
};

rcube_webmail.prototype.sieverules_ex_drag_start = function(list) {
	rcmail.sieverules_ex_drag_active = true;
	rcmail.sieverules_list.drag_active = true;
	rcmail.sieverules_drag_start(list);
};

rcube_webmail.prototype.sieverules_drag_start = function(list) {
	rcmail.sieverules_drag_active = true;

	if (this.sieverules_timer)
		clearTimeout(this.sieverules_timer);

	if (rcmail.gui_objects.sieverules_list) {
		rcmail.initialBodyScrollTop = bw.ie ? 0 : window.pageYOffset;
		rcmail.initialListScrollTop = rcmail.gui_objects.sieverules_list.parentNode.scrollTop;

		var pos, list, rulesTable;
		list = $(rcmail.gui_objects.sieverules_list.parentNode);
		pos = list.offset();
		rcmail.env.sieveruleslist_coords = { x1:pos.left, y1:pos.top, x2:pos.left + list.width(), y2:pos.top + list.height() };

		rows = rcmail.sieverules_list.rows;
		rcmail.env.sieverules_coords = new Array();
		for (var i = 0; i < rows.length; i++) {
			pos = $('#' + rows[i].id).offset();
			rcmail.env.sieverules_coords[rows[i].id] = { x1:pos.left, y1:pos.top, x2:pos.left + $('#' + rows[i].id).width(), y2:pos.top + $('#' + rows[i].id).height(), on:0 };
		}
	}
};

rcube_webmail.prototype.sieverules_drag_move = function(e) {
	if (rcmail.gui_objects.sieverules_list && rcmail.env.sieveruleslist_coords) {
		// offsets to compensate for scrolling while dragging a message
		var boffset = bw.ie ? -document.documentElement.scrollTop : rcmail.initialBodyScrollTop;
		var moffset = rcmail.initialListScrollTop-rcmail.gui_objects.sieverules_list.parentNode.scrollTop;
		var toffset = -moffset-boffset;

		var li, pos, mouse;
		mouse = rcube_event.get_mouse_pos(e);
		pos = rcmail.env.sieveruleslist_coords;
		mouse.y += toffset;

		// if mouse pointer is outside of folderlist
		if (mouse.x < pos.x1 || mouse.x >= pos.x2 || mouse.y < pos.y1 || mouse.y >= pos.y2) {
			$(rcmail.gui_objects.sieverules_list).children('tbody').children('tr:last').removeClass('droptargetend');
			rcmail.env.sieverules_last_target = null;
		}
		else {
			$(rcmail.gui_objects.sieverules_list).children('tbody').children('tr:last').addClass('droptargetend');
			rcmail.env.sieverules_last_target = 'end';
		}

		// over the folders
		for (var k in rcmail.env.sieverules_coords) {
			pos = rcmail.env.sieverules_coords[k];
			if (mouse.x >= pos.x1 && mouse.x < pos.x2 && mouse.y >= pos.y1 && mouse.y < pos.y2) {
				$(rcmail.gui_objects.sieverules_list).children('tbody').children('tr:last').removeClass('droptargetend');
				$('#' + k).addClass('droptarget');
				rcmail.env.sieverules_last_target = k;
				rcmail.env.sieverules_coords[k].on = 1;
			}
			else if (pos.on) {
				$('#' + k).removeClass('droptarget');
				rcmail.env.sieverules_last_target = null;
				rcmail.env.sieverules_coords[k].on = 0;
			}
		}
	}
};

rcube_webmail.prototype.sieverules_drag_end = function(e) {
	rcmail.sieverules_drag_active = false;
	rcmail.sieverules_ex_drag_active = false;
	rcmail.env.sieverules_last_target = null;

	// over the rules
	if (rcmail.gui_objects.sieverules_list && rcmail.env.sieverules_coords) {
		for (var k in rcmail.env.sieverules_coords) {
			if (rcmail.env.sieverules_coords[k].on) {
				$('#' + k).removeClass('droptarget');
			}
		}
	}

	$(rcmail.gui_objects.sieverules_list).children('tbody').children('tr:last').removeClass('droptargetend');
};

rcube_webmail.prototype.sieverules_load = function(id, action) {
	if (action == 'plugin.sieverules.edit' && (!id || id == rcmail.env.iid))
		return false;

	rcmail.env.iid = id;
	var add_url = '';
	var target = window;
	if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe]) {
		add_url = '&_framed=1';
		target = window.frames[rcmail.env.contentframe];
		rcube_find_object(rcmail.env.contentframe).style.visibility = 'inherit';
	}

	if (action && (id || action == 'plugin.sieverules.add')) {
		rcmail.set_busy(true);
		target.location.href = rcmail.env.comm_path+'&_action='+action+'&_iid='+id+add_url;
	}

	rcmail.enable_command('plugin.sieverules.delete', true);
	return true;
}

rcube_webmail.prototype.sieverules_update_list = function(action, param1, param2, param3, param4) {
	var sid = rcmail.sieverules_list.get_single_selection();
	var selection;
	var rows = rcmail.sieverules_list.rows;
	var rules = Array();

	switch(action) {
		case 'add-first':
			rcmail.sieverules_list.clear();
		case 'add':
			if (rows.length == 1 && rows[0].obj.cells[0].innerHTML == rcmail.gettext('loading',''))
				rcmail.sieverules_list.remove_row(0);

			var newrow = document.createElement('tr');

			if (param1 == -1) {
				var cell = document.createElement('td');
				cell.setAttribute('colspan', '2');
				cell.appendChild(document.createTextNode(param2));
				newrow.appendChild(cell);
			}
			else {
				newrow.id = param1;
				var cell = document.createElement('td');
				cell.appendChild(document.createTextNode(param2));
				newrow.appendChild(cell);

				cell = document.createElement('td');
				cell.className = 'control';

				param3 = param3.replace(/\\'/g, '\'');
				param4 = param4.replace(/\\'/g, '\'');

				cell.innerHTML = param3 + param4;
				newrow.appendChild(cell);
			}

			rcmail.sieverules_list.insert_row(newrow);
			break;
		case 'update':
			rows[param1].obj.cells[0].innerHTML = param2;
			break;
		case 'delete':
			rcmail.sieverules_list.clear_selection();
			sid = null;
		case 'reload':
			rcmail.sieverules_list.clear();

			var newrow = document.createElement('tr');
			var cell = document.createElement('td');
			cell.setAttribute('colspan', '2');
			cell.appendChild(document.createTextNode(rcmail.gettext('loading','')));
			newrow.appendChild(cell);
			rcmail.sieverules_list.insert_row(newrow);

			rcmail.http_request('plugin.sieverules.update_list', '', false);
			break;
		case 'move':
			// create array of rules
			for (var i = 0; i < rows.length; i++) {
				rules[i] = rows[i].obj.cells[0].innerHTML;

				if (sid == i) selection = rules[i];
			}

			// assign order
			rules.splice(param2, 0, rules[param1]);

			if (parseInt(param1) < parseInt(param2))
				rules.splice(param1, 1);
			else
				rules.splice(parseInt(param1) + 1, 1);

			// update table
			for (var i = 0; i < rows.length; i++) {
				rows[i].obj.cells[0].innerHTML = rules[i];

				if (rules[i] == selection) sid = i;
			}

			var target = window;
			if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe])
				target = window.frames[rcmail.env.contentframe];

			// update iid of rule being editied
			var iid;
			if (target.rcube_find_object && (iid = target.rcube_find_object('_iid'))) {
				if (iid.value != param1 && iid.value != "") {
					if (iid.value > param1 && iid.value < param2) {
						sid = parseInt(iid.value) - 1;
						rcmail.sieverules_list.highlight_row(sid);
						rcmail.sieverules_list.select_row(sid);
						iid.value = sid;
						target.rcmail.env.iid = sid;
					}
					else if (iid.value < param1 && iid.value > param2) {
						sid = parseInt(iid.value) + 1;
						rcmail.sieverules_list.highlight_row(sid);
						rcmail.sieverules_list.select_row(sid);
						iid.value = sid;
						target.rcmail.env.iid = sid;
					}
					else {
						rcmail.sieverules_list.select_row(iid.value);
					}
				}
				else if (iid.value != "") {
					rcmail.sieverules_list.highlight_row(sid);
					rcmail.sieverules_list.select_row(sid);
					iid.value = sid;
					target.rcmail.env.iid = sid;
				}
			}
			else if (sid) {
				rcmail.sieverules_list.highlight_row(sid);
				rcmail.sieverules_list.select_row(sid);
			}

			break;
	}
}

rcube_webmail.prototype.sieverules_rule_join_radio = function(value) {
	var rulesTable = rcube_find_object('rules-table');

	if (rulesTable.tBodies[0].rows.length == 3)
		rcmail.command('plugin.sieverules.add_rule','', rulesTable.tBodies[0].rows[0]);

	rulesTable.style.display = (value == 'any' ? 'none' : '');
}

rcube_webmail.prototype.sieverules_header_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex / 3;
	var eidx = ((idx + 1) * 3) - 1;
	var obj = document.getElementsByName('_selheader[]')[idx];
	var testType = obj.value.split('::')[0];
	var header = obj.value.split('::')[1];
	var selIdx = 0;
	var target_obj = $("input[name='_target[]']")[idx];

	document.getElementsByName('_test[]')[idx].value = testType;
	document.getElementsByName('_header[]')[idx].value = header;
	document.getElementsByName('_target[]')[idx].className = '';
	document.getElementsByName('_operator[]')[idx].selectedIndex = 0;
	document.getElementsByName('_bodypart[]')[idx].style.display = 'none';
	document.getElementsByName('_datepart[]')[idx].style.display = 'none';
	document.getElementsByName('_weekday[]')[idx].style.display = 'none';
	$(target_obj).unmask();

	if (header == 'size') {
		document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
		document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		document.getElementsByName('_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_date_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spamtest_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spam_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_virus_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_size_operator[]')[idx].style.display = '';
		document.getElementsByName('_target[]')[idx].style.display = '';
		document.getElementsByName('_target[]')[idx].className = 'short';
		document.getElementsByName('_units[]')[idx].style.display = '';
	}
	else if (header == 'spamtest') {
		document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
		document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		document.getElementsByName('_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_date_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spamtest_operator[]')[idx].style.display = '';
		document.getElementsByName('_spam_probability[]')[idx].style.display = '';
		document.getElementsByName('_virus_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_target[]')[idx].style.display = 'none';
		document.getElementsByName('_target[]')[idx].value = document.getElementsByName('_spam_probability[]')[idx].value;
		document.getElementsByName('_units[]')[idx].style.display = 'none';
	}
	else if (header == 'virustest') {
		document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
		document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		document.getElementsByName('_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_date_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spamtest_operator[]')[idx].style.display = '';
		document.getElementsByName('_spam_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_virus_probability[]')[idx].style.display = '';
		document.getElementsByName('_target[]')[idx].style.display = 'none';
		document.getElementsByName('_target[]')[idx].value = document.getElementsByName('_spam_probability[]')[idx].value;
		document.getElementsByName('_units[]')[idx].style.display = 'none';
	}
	else if (header.indexOf('predefined_') == 0) {
		document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
		document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		document.getElementsByName('_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_date_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spamtest_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spam_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_virus_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_target[]')[idx].style.display = 'none';
		document.getElementsByName('_units[]')[idx].style.display = 'none';

		if (rcmail.env.predefined_rules[header.substring(11)][0] == 'size') {
			document.getElementsByName('_header[]')[idx].value = 'size';
			selIdx = rcmail.sieverules_get_index(document.getElementsByName('_size_operator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2]);
			document.getElementsByName('_size_operator[]')[idx].selectedIndex = selIdx;
			var reg = new RegExp('^([0-9]+)(K|M)*$');
			var matches = reg.exec(rcmail.env.predefined_rules[header.substring(11)][3]);
			document.getElementsByName('_target[]')[idx].value = matches[1];
			selIdx = rcmail.sieverules_get_index(document.getElementsByName('_units[]')[idx], matches[2]);
			document.getElementsByName('_units[]')[idx].selectedIndex = selIdx;
		}
		else if (rcmail.env.predefined_rules[header.substring(11)][0] == 'spamtest') {
			document.getElementsByName('_header[]')[idx].value = 'spamtest';
			selIdx = rcmail.sieverules_get_index(document.getElementsByName('_spamtest_operator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2]);
			document.getElementsByName('_spamtest_operator[]')[idx].selectedIndex = selIdx;
			document.getElementsByName('_spam_probability[]')[idx].value = rcmail.env.predefined_rules[header.substring(11)][3];
		}
		else if (rcmail.env.predefined_rules[header.substring(11)][0] == 'virustest') {
			document.getElementsByName('_header[]')[idx].value = 'virustest';
			selIdx = rcmail.sieverules_get_index(document.getElementsByName('_spamtest_operator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2]);
			document.getElementsByName('_spamtest_operator[]')[idx].selectedIndex = selIdx;
			document.getElementsByName('_virus_probability[]')[idx].value = rcmail.env.predefined_rules[header.substring(11)][3];
		}
		else {
			document.getElementsByName('_header[]')[idx].value = rcmail.env.predefined_rules[header.substring(11)][1];
			selIdx = rcmail.sieverules_get_index(document.getElementsByName('_operator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2], -1);

			// check advanced options if standard not found
			if (selIdx == -1 && rcmail.sieverules_get_index(document.getElementsByName('_advoperator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2], -1) > -1) {
				document.getElementsByName('_operator[]')[idx].selectedIndex = rcmail.sieverules_get_index(document.getElementsByName('_operator[]')[idx], 'advoptions');
				document.getElementsByName('_advoperator[]')[idx].selectedIndex = rcmail.sieverules_get_index(document.getElementsByName('_advoperator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][2]);
				document.getElementsByName('_comparator[]')[idx].selectedIndex = rcmail.sieverules_get_index(document.getElementsByName('_comparator[]')[idx], rcmail.env.predefined_rules[header.substring(11)][3]);
				document.getElementsByName('_advtarget[]')[idx].value = rcmail.env.predefined_rules[header.substring(11)][4];
			}
			else {
				document.getElementsByName('_operator[]')[idx].selectedIndex = selIdx;
				document.getElementsByName('_target[]')[idx].value = rcmail.env.predefined_rules[header.substring(11)][4];
			}
		}
	}
	else {
		document.getElementsByName('_operator[]')[idx].style.display = '';
		document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spamtest_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_spam_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_virus_probability[]')[idx].style.display = 'none';
		document.getElementsByName('_date_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_target[]')[idx].style.display = '';
		document.getElementsByName('_units[]')[idx].style.display = 'none';

		if (header == 'other') {
			document.getElementsByName('_header[]')[idx].style.visibility = 'visible';
			document.getElementsByName('_headerhlp')[idx].style.visibility = 'visible';
			document.getElementsByName('_header[]')[idx].value = '';
		}
		else {
			document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
			document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		}

		if (header == 'body') {
			document.getElementsByName('_header[]')[idx].style.display = 'none';
			document.getElementsByName('_headerhlp')[idx].style.display = 'none';
			document.getElementsByName('_bodypart[]')[idx].style.display = '';

			document.getElementsByName('_body_contentpart[]')[idx].parentNode.parentNode.style.display = '';
		}
		else if (testType == 'date') {
			document.getElementsByName('_header[]')[idx].style.display = 'none';
			document.getElementsByName('_headerhlp')[idx].style.display = 'none';
			document.getElementsByName('_datepart[]')[idx].style.display = '';
			document.getElementsByName('_operator[]')[idx].style.display = 'none';
			document.getElementsByName('_date_operator[]')[idx].style.display = '';

			document.getElementsByName('_datepart[]')[idx].selectedIndex = 0;
			document.getElementsByName('_body_contentpart[]')[idx].parentNode.parentNode.style.display = 'none';
			$(target_obj).datepicker({ dateFormat: 'yy-mm-dd' });
		}
		else {
			document.getElementsByName('_header[]')[idx].style.display = '';
			document.getElementsByName('_headerhlp')[idx].style.display = '';

			document.getElementsByName('_body_contentpart[]')[idx].parentNode.parentNode.style.display = 'none';
		}
	}

	var idx = sel.parentNode.parentNode.rowIndex;
	rcube_find_object('rules-table').tBodies[0].rows[idx + 1].style.display = 'none';
	rcube_find_object('rules-table').tBodies[0].rows[idx + 2].style.display = 'none';
}

rcube_webmail.prototype.sieverules_bodypart_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var eidx = idx / 3;
	var obj = document.getElementsByName('_bodypart[]')[eidx];

	document.getElementsByName('_body_contentpart[]')[eidx].disabled = false;
	document.getElementsByName('_advoperator[]')[eidx].disabled = (document.getElementsByName('_operator[]')[eidx].value == 'advoptions') ? false : true;

	if (document.getElementsByName('_operator[]')[eidx].value == 'advoptions')
		rcmail.sieverules_rule_advop_select(document.getElementsByName('_advoperator[]')[eidx]);
	else
		document.getElementsByName('_comparator[]')[eidx].disabled = true;

	document.getElementsByName('_advtarget[]')[eidx].disabled = (document.getElementsByName('_operator[]')[eidx].value == 'advoptions') ? false : true;
	var advopts_row = rcube_find_object('rules-table').tBodies[0].rows[idx + 2];
	if (obj.value != 'content' && document.getElementsByName('_operator[]')[eidx].value == 'advoptions')
		document.getElementsByName('_body_contentpart[]')[eidx].disabled = true;
	else
		advopts_row.style.display = (obj.value == 'content' ? '' : 'none');
}

rcube_webmail.prototype.sieverules_datepart_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var eidx = idx / 3;
	var obj = document.getElementsByName('_datepart[]')[eidx];
	var opr = document.getElementsByName('_operator[]')[eidx];
	var target_obj = $("input[name='_target[]']")[eidx];
	$(target_obj).datepicker("destroy");
	$(target_obj).unmask();

	if (obj.value == 'date')
		$(target_obj).datepicker({ dateFormat: 'yy-mm-dd' });
	else if (obj.value == 'time')
		$(target_obj).mask('99:99:99', {example: 'HH:MM:SS', placeholder: '0'});

	document.getElementsByName('_advtarget[]')[eidx].style.display = (obj.value == 'weekday') ? 'none' : '';
	document.getElementsByName('_advweekday[]')[eidx].style.display = (obj.value == 'weekday') ? '' : 'none';
	if (opr.value != 'exists' && opr.value != 'notexists' && opr.value != 'advoptions') {
		document.getElementsByName('_target[]')[eidx].style.display = (obj.value == 'weekday') ? 'none' : '';
		document.getElementsByName('_weekday[]')[eidx].style.display = (obj.value == 'weekday') ? '' : 'none';
	}
}

rcube_webmail.prototype.sieverules_rule_op_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var eidx = idx / 3;
	var datepart = document.getElementsByName('_datepart[]')[eidx].value;

	var obj = document.getElementsByName('_operator[]')[eidx];
	if (obj.value == 'exists' || obj.value == 'notexists' || obj.value == 'advoptions') {
		document.getElementsByName('_target[]')[eidx].style.display = 'none';
		document.getElementsByName('_weekday[]')[eidx].style.display = 'none';
	}
	else {
		document.getElementsByName('_target[]')[eidx].style.display = (datepart == 'weekday') ? 'none' : '';
		document.getElementsByName('_weekday[]')[eidx].style.display = (datepart == 'weekday') ? '' : 'none';
	}

	if (obj.value != 'exists' && obj.value != 'notexists' && document.getElementsByName('_test[]')[eidx].value == 'exists') {
		var h_obj = document.getElementsByName('_selheader[]')[eidx];
		var testType = h_obj.value.split('::')[0];

		document.getElementsByName('_test[]')[eidx].value = testType;
	}

	document.getElementsByName('_body_contentpart[]')[eidx].disabled = (document.getElementsByName('_bodypart[]')[eidx].value == 'content') ? false : true;
	document.getElementsByName('_advoperator[]')[eidx].disabled = false;
	rcmail.sieverules_rule_advop_select(document.getElementsByName('_advoperator[]')[eidx]);
	document.getElementsByName('_advtarget[]')[eidx].disabled = false;
	var advopts_row = rcube_find_object('rules-table').tBodies[0].rows[idx + 2];
	if (obj.value != 'advoptions' && document.getElementsByName('_bodypart[]')[eidx].value == 'content') {
		document.getElementsByName('_advoperator[]')[eidx].disabled = true;
		document.getElementsByName('_comparator[]')[eidx].disabled = true;
		document.getElementsByName('_advtarget[]')[eidx].disabled = true;
	}
	else {
		advopts_row.style.display = (obj.value == 'advoptions' ? '' : 'none');
	}

	return false;
}

rcube_webmail.prototype.sieverules_rule_advop_select = function(sel) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var idx = (obj.parentNode.parentNode.rowIndex - 2) / 3;

	if (sel.value.substring(0, 5) == 'count' || sel.value.substring(0, 5) == 'value')
		document.getElementsByName('_comparator[]')[idx].disabled = false;
	else
		document.getElementsByName('_comparator[]')[idx].disabled = true;

	return false;
}

rcube_webmail.prototype.sieverules_action_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var actoion_row = rcube_find_object('actions-table').tBodies[0].rows[idx];
	var obj = document.getElementsByName('_act[]')[idx];

	// hide everything
	document.getElementsByName('_folder[]')[idx].style.display = 'none';
	$(document.getElementsByName('_customfolder[]')[idx]).parent().hide();
	document.getElementsByName('_redirect[]')[idx].style.display = 'none';
	document.getElementsByName('_reject[]')[idx].style.display = 'none';
	document.getElementsByName('_imapflags[]')[idx].style.display = 'none';
	document.getElementsByName('_day[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = 'none';
	document.getElementsByName('_nmethod[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = 'none';
	document.getElementsByName('_eheadname[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = 'none';

	if (obj.value == 'fileinto' || obj.value == 'fileinto_copy')
		document.getElementsByName('_folder[]')[idx].style.display = '';
	else if (obj.value == 'reject' || obj.value == 'ereject')
		document.getElementsByName('_reject[]')[idx].style.display = '';
	else if (obj.value == 'vacation') {
		document.getElementsByName('_day[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = '';

		if (rcmail.env.sieverules_htmleditor == 1) {
			rowid = document.getElementsByName('_msg[]')[idx].id.replace('rcmfd_sievevacmag_', '');
			document.getElementById('rcmfd_sievevachtmlcb_' + rowid).checked = true;
			rcmail.sieverules_toggle_vac_html(document.getElementById('rcmfd_sievevachtmlcb_' + rowid), rowid, 'rcmfd_sievevacmag_' + rowid);
		}

		rcmail.enable_sig(document.getElementsByName('_vacfrom[]')[idx]);
	}
	else if (obj.value == 'notify' || obj.value == 'enotify')
		document.getElementsByName('_nmethod[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = '';
	else if (obj.value == 'redirect' || obj.value == 'redirect_copy')
		document.getElementsByName('_redirect[]')[idx].style.display = '';
	else if (obj.value == 'imapflags' || obj.value == 'imap4flags')
		document.getElementsByName('_imapflags[]')[idx].style.display = '';
	else if (obj.value == 'editheaderadd' || obj.value == 'editheaderrem') {
		document.getElementsByName('_eheadname[]')[idx].parentNode.parentNode.parentNode.parentNode.style.display = '';

		if (obj.value == 'editheaderrem') {
			document.getElementsByName('_eheadval[]')[idx].parentNode.parentNode.style.display = 'none';
			document.getElementsByName('_eheadaddlast[]')[idx].parentNode.parentNode.style.display = 'none';
			document.getElementsByName('_hadv_opts[]')[idx].parentNode.parentNode.style.display = '';
		}
		else {
			document.getElementsByName('_eheadval[]')[idx].parentNode.parentNode.style.display = '';
			document.getElementsByName('_eheadaddlast[]')[idx].parentNode.parentNode.style.display = '';
			document.getElementsByName('_eheadopp[]')[idx].parentNode.parentNode.style.display = 'none';
			document.getElementsByName('_eheadindex[]')[idx].parentNode.parentNode.style.display = 'none';
			document.getElementsByName('_hadv_opts[]')[idx].parentNode.parentNode.style.display = 'none';
		}
	}

	if ($(document.getElementsByName('_folder[]')[idx]).is(':visible') && document.getElementsByName('_folder[]')[idx].value == '@@newfolder')
		$(document.getElementsByName('_customfolder[]')[idx]).parent().show();
}

rcube_webmail.prototype.sieverules_select_folder = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var actoion_row = rcube_find_object('actions-table').tBodies[0].rows[idx];
	var obj = document.getElementsByName('_folder[]')[idx];

	$(document.getElementsByName('_customfolder[]')[idx]).parent().hide();
	if (obj.value == '@@newfolder')
		$(document.getElementsByName('_customfolder[]')[idx]).parent().show();
}

rcube_webmail.prototype.sieverules_xheaders = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex + 1;
	var xheader_row = rcube_find_object('rules-table').tBodies[0].rows[idx];
	xheader_row.style.display = (xheader_row.style.display == 'none' ? '' : 'none');
	return false;
}

rcube_webmail.prototype.sieverules_set_xheader = function(sel) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var idx = (obj.parentNode.parentNode.rowIndex - 1) / 3;
	var headerBox = document.getElementsByName('_header[]')[idx];
	headerBox.value = sel.value;
}

rcube_webmail.prototype.sieverules_get_index = function(list, value, fallback) {
	fallback = fallback || 0;

	for (var i = 0; i < list.length; i++) {
		if (list[i].value == value)
			return i;
	}

	return fallback;
}

rcube_webmail.prototype.sieverules_toggle_vac_to = function(sel, id) {
	var obj = rcube_find_object('rcmfd_sievevacto_' + id);
	var opts = document.getElementsByName('_vacto_check_' + id + '[]')

	obj.value = "";
	for (i = 0; i < opts.length; i++) {
		if (opts[i].checked) {
			if (obj.value.length > 0) obj.value += ",";
			obj.value += opts[i].value;
		}
	}
}

rcube_webmail.prototype.sieverules_toggle_vac_osubj = function(sel, id) {
	var obj = rcube_find_object('rcmfd_sievevactoh_' + id);
	obj.value = sel.checked ? sel.value : "";
}

rcube_webmail.prototype.sieverules_toggle_vac_html = function(obj, rowid, txtid) {
	rcmail_toggle_editor(obj, txtid);

	var sel = rcube_find_object('rcmfd_sievevachtmlhd_' + rowid);
	sel.value = obj.checked ? obj.value : "";
}

rcube_webmail.prototype.sieverules_notify_impt = function(sel, id) {
	var obj = rcube_find_object('rcmfd_sievenimpt_' + id);
	obj.value = sel.value == 'none' ? '' : sel.value;
}

rcmail.sieverules_help = function(sel, row) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var help_row = obj.tBodies[0].rows[row];
	help_row.style.display = (help_row.style.display == 'none' ? '' : 'none');
	return false;
}

rcube_webmail.prototype.sieverules_show_adv = function(sel) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var rows = obj.tBodies[0].rows;

	if (sel.checked) {
		for(var i = 0; i < rows.length; i++)
			if(rows[i].className && rows[i].className.match(/advanced/))
				rows[i].style.display = '';
	}
	else {
		for(var i = 0; i < rows.length; i++)
			if(rows[i].className && rows[i].className.match(/advanced/))
				rows[i].style.display = 'none';

		for(var i = 0; i < rows.length; i++)
			if(rows[i].className && rows[i].className.match(/advhelp/))
				rows[i].style.display = 'none';
	}
}

rcube_webmail.prototype.sieverules_adveditor = function(sel) {
	if (sel.checked && !confirm(rcmail.gettext('switchtoadveditor','sieverules'))) {
		sel.checked = false;
		return false;
	}

	if (sel.checked)
		rcmail.goto_url('plugin.sieverules.advanced', '', true);
	else
		rcmail.goto_url('plugin.sieverules', '_override=1', true);
}

rcube_webmail.prototype.sieverules_load_setup = function() {
	var add_url = '';

	var target = window;
	if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe]) {
		add_url = '&_framed=1';
		target = window.frames[rcmail.env.contentframe];
		rcube_find_object(rcmail.env.contentframe).style.visibility = 'inherit';
	}

	target.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.setup' + add_url;
}

rcube_webmail.prototype.sieverules_select_ruleset = function(obj, action) {
	if (typeof obj == 'string') {
		window.location.href = rcmail.env.comm_path+'&_action='+action+'&_ruleset=' + obj;
	}
	else {
		var idx = obj.selectedIndex;
		window.location.href = rcmail.env.comm_path+'&_action='+action+'&_ruleset=' + obj.options[idx].value;
	}
}

rcube_webmail.prototype.sieverules_add_ruleset = function(val, text) {
	var obj = rcube_find_object('rulelist');

	// remove loading message
	if (obj.options.length == 1 && obj.options[0].value == '' && obj.options[0].text == rcmail.gettext('loading',''))
		obj.remove(0);

	var opt = document.createElement('option');
	opt.value = val;
	opt.text = text;

	obj.options.add(opt);

	if (rcmail.env.ruleset == val)
		obj.selectedIndex = obj.options.length - 1;
}

rcube_webmail.prototype.sieverules_disable_ruleset_options = function() {
	$('#rulelist').attr("disabled", "disabled");
	rcmail.enable_command('plugin.sieverules.ruleset_dialog', 'plugin.sieverules.activate_ruleset', 'plugin.sieverules.del_ruleset', false);
}

rcube_webmail.prototype.sieverulesdialog_submit = function() {
	var action = rcube_find_object('sieverulesrsdialog_action').value;
	var val = rcube_find_object('sieverulesrsdialog_name').value;

	if (action == '' || action == 'rename_ruleset') {
		var obj = rcube_find_object('sieverulesrsdialog_ruleset');
		for (i = 0; i < obj.options.length ; i++) {
			if (obj.options[i].value == val) {
				alert(rcmail.gettext('rulesetexists','sieverules'));
				rcube_find_object('sieverulesrsdialog_name').focus();
				return false;
			}
		}
	}
	else if (action == 'copyto_ruleset' || action == 'copyfrom_ruleset') {
		var obj = rcube_find_object('sieverulesrsdialog_ruleset');
		var idx = obj.selectedIndex;
		val = obj.options[idx].value;
	}

	$('#sieverulesrsdialog').dialog('close');

	var target = window;
	if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe])
		target = window.frames[rcmail.env.contentframe];

	if (action == 'rename_ruleset')
		window.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.rename_ruleset&_ruleset=' + rcmail.env.ruleset + '&_new=' + val;
	else if (action == 'copyto_ruleset')
		rcmail.http_request('plugin.sieverules.copy_filter', '_iid='+ target.rcmail.env.iid +'&_dest=' + val, true);
	else if (action == 'copyfrom_ruleset')
		window.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.import&_import=_copy_&_ruleset=' + val + '&_new=' + rcmail.env.ruleset;
	else
		window.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules&_ruleset=' + val;
}

rcube_webmail.prototype.enable_sig = function(obj) {
	var id;

	if (obj.options[0].value == 'auto' || obj.options[0].value == '')
		id = obj.selectedIndex;
	else
		id = obj.selectedIndex + 1;

	// enable manual signature insert
	if (rcmail.env.signatures && rcmail.env.signatures[id])
		rcmail.enable_command('plugin.sieverules.vacation_sig', true);
	else
		rcmail.enable_command('plugin.sieverules.vacation_sig', false);
}

rcube_webmail.prototype.sieverules_toggle_eheadlast = function(obj) {
	var selectobj = document.getElementById(obj.id.replace('_eheadaddlast_', '_eheadindex_'));

	if (obj.checked)
		selectobj.selectedIndex = 6;
	else
		selectobj.selectedIndex = 0;
}

$(document).ready(function() {
	if (window.rcmail) {
		rcmail.addEventListener('init', function(evt) {
			if (rcmail.env.action == 'plugin.sieverules.add' || rcmail.env.action == 'plugin.sieverules.edit' || rcmail.env.action == 'plugin.sieverules.setup' || rcmail.env.action == 'plugin.sieverules.advanced')
				var tab = $('<span>').attr('id', 'settingstabpluginsieverules').addClass('tablink selected');
			else
				var tab = $('<span>').attr('id', 'settingstabpluginsieverules').addClass('tablink');

			var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.sieverules').attr('title', rcmail.gettext('managefilters', 'sieverules')).html(rcmail.gettext('filters','sieverules')).appendTo(tab);

			// add button and register command
			rcmail.add_element(tab, 'tabs');

			if ((rcmail.env.action == 'plugin.sieverules' || rcmail.env.action == 'plugin.sieverules.advanced') && !rcmail.env.sieveruleserror) {
				if (rcmail.gui_objects.sieverules_list) {
					rcmail.sieverules_list = new rcube_list_widget(rcmail.gui_objects.sieverules_list, {multiselect:false, draggable:true, keyboard:true});

					// override blur function to prevent current rule being deselected
					rcmail.sieverules_list.blur = function() {}

					rcmail.sieverules_list.addEventListener('select', function(o) { rcmail.sieverules_select(o); });
					rcmail.sieverules_list.addEventListener('keypress', function(o) { rcmail.sieverules_keypress(o); });
					rcmail.sieverules_list.addEventListener('dragstart', function(o) { rcmail.sieverules_drag_start(o); });
					rcmail.sieverules_list.addEventListener('dragmove', function(e) { rcmail.sieverules_drag_move(e); });
					rcmail.sieverules_list.addEventListener('dragend', function(e) { rcmail.sieverules_drag_end(e); });
					document.onmouseup = function(e) { return rcmail.sieverules_mouse_up(e); };
					rcmail.sieverules_list.init();
					rcmail.sieverules_list.focus();

					if (rcmail.env.iid && rcmail.env.iid < rcmail.sieverules_list.rows.length && !rcmail.env.eid)
						rcmail.sieverules_list.select_row(rcmail.env.iid, false, false);
				}

				if (rcmail.gui_objects.sieverules_examples) {
					rcmail.sieverules_examples = new rcube_list_widget(rcmail.gui_objects.sieverules_examples, {multiselect:true, draggable:true, keyboard:true});
					rcmail.sieverules_examples.addEventListener('select', function(o) { rcmail.sieverules_ex_select(o); });
					rcmail.sieverules_examples.addEventListener('dragstart', function(o) { rcmail.sieverules_ex_drag_start(o); });
					rcmail.sieverules_examples.addEventListener('dragmove', function(e) { rcmail.sieverules_drag_move(e); });
					rcmail.sieverules_examples.addEventListener('dragend', function(e) { rcmail.sieverules_drag_end(e); });
					rcmail.sieverules_examples.init();

					if (rcmail.env.eid)
						rcmail.sieverules_examples.highlight_row(rcmail.env.eid);

					rcmail.register_command('plugin.sieverules.import_ex', function() {
						if (rcmail.sieverules_examples.get_selection().length > 0) {
							rcmail.set_busy(true, 'sieverules.movingfilter');
							rcmail.goto_url('plugin.sieverules.import', '_import=_example_&_pos='+ rcmail.env.sieverules_last_target +'&_eids=' + rcmail.sieverules_examples.get_selection(), true);
						}
					}, true);
				}

				if (rcmail.env.action == 'plugin.sieverules') {
					rcmail.register_command('plugin.sieverules.move', function(props, obj) {
						var args = (props.source) ? props : { source:obj.parentNode.parentNode.rowIndex - 1, dest:props };

						if (args.dest > -1 && args.dest <= rcmail.sieverules_list.rows.length) {
							var lock = rcmail.set_busy(true, 'sieverules.movingfilter');
							rcmail.http_request('plugin.sieverules.move', '_src=' + args.source + '&_dst=' + args.dest, lock);
						}
					}, true);

					rcmail.register_command('plugin.sieverules.add', function(id) {
							if (rcmail.sieverules_examples) rcmail.sieverules_examples.clear_selection();
							rcmail.sieverules_list.clear_selection();
							var add_url = '';

							var target = window;
							if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe]) {
								add_url = '&_framed=1';
								target = window.frames[rcmail.env.contentframe];
								rcube_find_object(rcmail.env.contentframe).style.visibility = 'inherit';
							}

							target.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.add' + add_url;
					}, true);
				}

				rcmail.register_command('plugin.sieverules.ruleset_dialog', function(props, obj) {
					rcube_find_object('sieverulesrsdialog_add').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_edit').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_copyto').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_copyfrom').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_input').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_select').style.display = 'none';
					rcube_find_object('sieverulesrsdialog_name').value = '';

					if (props == 'rename_ruleset') {
						//rcube_find_object('sieverulesrsdialog_edit').style.display = '';
						boxtitle = rcube_find_object('sieverulesrsdialog_edit').innerHTML;
						rcube_find_object('sieverulesrsdialog_input').style.display = '';
						rcube_find_object('sieverulesrsdialog_name').value = rcmail.env.ruleset;
					}
					else if (props == 'copyto_ruleset') {
						//rcube_find_object('sieverulesrsdialog_copyto').style.display = '';
						boxtitle = rcube_find_object('sieverulesrsdialog_copyto').innerHTML;
						rcube_find_object('sieverulesrsdialog_select').style.display = '';
					}
					else if (props == 'copyfrom_ruleset') {
						//rcube_find_object('sieverulesrsdialog_copyfrom').style.display = '';
						boxtitle = rcube_find_object('sieverulesrsdialog_copyfrom').innerHTML;
						rcube_find_object('sieverulesrsdialog_select').style.display = '';
					}
					else {
						//rcube_find_object('sieverulesrsdialog_add').style.display = '';
						boxtitle = rcube_find_object('sieverulesrsdialog_add').innerHTML;
						rcube_find_object('sieverulesrsdialog_input').style.display = '';
					}

					rcube_find_object('sieverulesrsdialog_action').value = props;

					$('#sieverulesrsdialog').dialog({ title: boxtitle, width: 512, resizable: false, modal: true });
				}, true);

				rcmail.register_command('plugin.sieverules.activate_ruleset', function(props, obj) {
					rcmail.set_busy(true);

					var obj = rcube_find_object('rulelist');
					if (obj) {
						rcmail.http_request('plugin.sieverules.enable_ruleset', '_ruleset=' + rcmail.env.ruleset, true);
						obj.options.length = 0;

						var opt = document.createElement('option');
						opt.value = '';
						opt.text = rcmail.gettext('loading','');

						obj.options.add(opt);
						rcmail.enable_command('plugin.sieverules.activate_ruleset', false);
					}
					else {
						window.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.enable_ruleset&_reload=1&_ruleset=' + rcmail.env.ruleset;
					}
				}, false);

				rcmail.register_command('plugin.sieverules.del_ruleset', function(props, obj) {
					if (rcmail.env.ruleset_total < 2)
						return false;

					if (confirm(rcmail.gettext('delrulesetconf','sieverules')))
						window.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.del_ruleset&_ruleset=' + rcmail.env.ruleset + '&_next=' + rcmail.env.ruleset_next;
				}, false);

				rcmail.register_command('plugin.sieverules.sieverules_adveditor', function(props, obj) {
					var chkbox = document.createElement('checkbox');

					if (props == "1")
						chkbox.checked = true;

					rcmail.sieverules_adveditor(chkbox);
				}, true);

				rcmail.register_command('plugin.sieverules.delete', function(id) {
					if (confirm(rcmail.gettext('filterdeleteconfirm','sieverules'))) {
						var add_url = '';

						var target = window;
						if (rcmail.env.contentframe && window.frames && window.frames[rcmail.env.contentframe]) {
							add_url = '&_framed=1';
							target = window.frames[rcmail.env.contentframe];
							rcube_find_object(rcmail.env.contentframe).style.visibility = 'inherit';
						}

						target.location.href = rcmail.env.comm_path+'&_action=plugin.sieverules.delete&_iid=' + rcmail.env.iid + add_url;
						rcmail.enable_command('plugin.sieverules.delete', false);
					}
				}, false);

				if (rcmail.env.action == 'plugin.sieverules.advanced') {
					rcmail.register_command('plugin.sieverules.save', function() {
						rcmail.gui_objects.editform.submit();
					}, true);
				}

				// enable commands
				if (!rcmail.env.ruleset_active && rcmail.env.ruleset_total > 1)
					rcmail.enable_command('plugin.sieverules.del_ruleset', true);

				if (!rcmail.env.ruleset_active)
					rcmail.enable_command('plugin.sieverules.activate_ruleset', true);
			}
			else if (rcmail.env.action == 'plugin.sieverules.setup') {
				rcmail.register_command('plugin.sieverules.import', function(props) {
					var add_url = '';

					var target = window;
					if (rcmail.env.framed)
						target = window.parent;

					target.location.href = './?_task=settings&_action=plugin.sieverules.import&' + props;
				}, true);

				rcmail.register_command('plugin.sieverules.ruleset_dialog_setup', function(props, obj) {
					var target = window;
					if (rcmail.env.framed)
						target = window.parent;

					target.rcube_find_object('sieverulesrsdialog_add').style.display = 'none';
					target.rcube_find_object('sieverulesrsdialog_edit').style.display = 'none';
					target.rcube_find_object('sieverulesrsdialog_input').style.display = 'none';
					//target.rcube_find_object('sieverulesrsdialog_copyfrom').style.display = '';
					boxtitle = rcube_find_object('sieverulesrsdialog_copyfrom').innerHTML;
					target.rcube_find_object('sieverulesrsdialog_select').style.display = '';
					target.rcube_find_object('sieverulesrsdialog_action').value = props;

					target.$('#sieverulesrsdialog').dialog({ title: boxtitle, width: 512, resizable: false, modal: true });
				}, true);
			}

			if (rcmail.env.action == 'plugin.sieverules.add' || rcmail.env.action == 'plugin.sieverules.edit') {
				rcmail.register_command('plugin.sieverules.add_rule', function(props, obj) {
					rcmail.enable_command('plugin.sieverules.del_rule', true);
					var rulesTable = rcube_find_object('rules-table').tBodies[0];
					var idx = obj.parentNode.parentNode.rowIndex + 3;
					var newNode1 = rulesTable.rows[0].cloneNode(true);
					var newNode2 = rulesTable.rows[1].cloneNode(true);
					var newNode3 = rulesTable.rows[2].cloneNode(true);

					if (idx < rulesTable.rows.length) {
						rulesTable.insertBefore(newNode3, rulesTable.rows[idx]);
						rulesTable.insertBefore(newNode2, rulesTable.rows[idx]);
						rulesTable.insertBefore(newNode1, rulesTable.rows[idx]);
					}
					else {
						rulesTable.appendChild(newNode1);
						rulesTable.appendChild(newNode2);
						rulesTable.appendChild(newNode3);
					}

					rcmail.env.sieverules_rules++;
					var tmp = $(newNode2).html().replace(/rowid/g, rcmail.env.sieverules_rules);
					$(newNode2).html(tmp);
					var tmp = $(newNode3).html().replace(/rowid/g, rcmail.env.sieverules_rules);
					$(newNode3).html(tmp);

					newNode1.style.display = "";
					newNode2.style.display = "none";
					newNode3.style.display = "none";

					return false;
				}, true);

				rcmail.register_command('plugin.sieverules.del_rule', function(props, obj) {
					var rulesTable = rcube_find_object('rules-table').tBodies[0];

					if (rulesTable.rows.length == 6)
						return false;

					if (confirm(rcmail.gettext('ruledeleteconfirm','sieverules'))) {
						rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex + 2);
						rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex + 1);
						rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex);
					}

					if (rcube_find_object('rules-table').tBodies[0].rows.length == 6)
						rcmail.enable_command('plugin.sieverules.del_rule', false);

					return false;
				}, false);

				rcmail.register_command('plugin.sieverules.copy_rule', function(props, obj) {
					parent.rcmail.command('plugin.sieverules.ruleset_dialog', 'copyto_ruleset', obj);
				}, true);

				rcmail.register_command('plugin.sieverules.add_action', function(props, obj) {
					rcmail.enable_command('plugin.sieverules.del_action', true);
					var actsTable = rcube_find_object('actions-table').tBodies[0];
					var idx = obj.parentNode.parentNode.rowIndex + 1;
					var newNode = actsTable.rows[0].cloneNode(true);

					if (idx < actsTable.rows.length)
						actsTable.insertBefore(newNode, actsTable.rows[idx]);
					else
						actsTable.appendChild(newNode);

					rcmail.env.sieverules_actions++;
					var tmp = $(newNode).html().replace(/rowid/g, rcmail.env.sieverules_actions);
					$(newNode).html(tmp);

					newNode.style.display = "";

					return false;
				}, true);

				rcmail.register_command('plugin.sieverules.del_action', function(props, obj) {
					var actsTable = rcube_find_object('actions-table').tBodies[0];

					if (actsTable.rows.length == 2)
						return false;

					if (confirm(rcmail.gettext('actiondeleteconfirm','sieverules')))
						actsTable.deleteRow(obj.parentNode.parentNode.rowIndex);

					if (rcube_find_object('actions-table').tBodies[0].rows.length == 2)
						rcmail.enable_command('plugin.sieverules.del_action', false);

					return false;
				}, false);

				rcmail.register_command('plugin.sieverules.save', function() {
					var rows;

					if (rcmail.env.framed)
						rows = parent.rcmail.sieverules_list.rows;
					else
						rows = rcmail.sieverules_list.rows;

					var input_name = rcube_find_object('_name');
					var rule_join = document.getElementsByName('_join');
					var headers = document.getElementsByName('_header[]');
					var bodyparts = document.getElementsByName('_bodypart[]');
					var contentparts = document.getElementsByName('_body_contentpart[]');
					var dateparts = document.getElementsByName('_datepart[]');
					var ops = document.getElementsByName('_operator[]');
					var advops = document.getElementsByName('_advoperator[]');
					var targets = document.getElementsByName('_target[]');
					var advtargets = document.getElementsByName('_advtarget[]');
					var acts = document.getElementsByName('_act[]');
					var folders = document.getElementsByName('_folder[]');
					var customfolders = document.getElementsByName('_customfolder[]');
					var addrs = document.getElementsByName('_redirect[]');
					var rejects = document.getElementsByName('_reject[]');
					var senders = document.getElementsByName('_vacfrom[]');
					var aliases = document.getElementsByName('_vacto[]');
					var days = document.getElementsByName('_day[]');
					var subjects = document.getElementsByName('_subject[]');
					var msgs = document.getElementsByName('_msg[]');
					var nmethods = document.getElementsByName('_nmethod[]');
					var nmsgs = document.getElementsByName('_nmsg[]');
					var eheadernames = document.getElementsByName('_eheadname[]');
					var eheadervals = document.getElementsByName('_eheadval[]');
					var size_test = new RegExp('^[0-9]+$');
					var spamtest_test = new RegExp('^[0-9]+$');
					var header_test = new RegExp('^[a-zA-Z0-9\-]+( ?, ?[a-zA-Z0-9\-]+)*$');
					var date_test = new RegExp('^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$');
					var time_test = new RegExp('^[0-9]{2}:[0-9]{2}:[0-9]{2}$');

					if (input_name && input_name.value == '') {
						alert(rcmail.gettext('norulename','sieverules'));
						input_name.focus();
						return false;
					}

					for (var i = 0; i < rows.length; i++) {
						if (input_name.value == rows[i].obj.cells[0].innerHTML && i != rcmail.env.iid) {
							alert(rcmail.gettext('ruleexists','sieverules'));
							input_name.focus();
							return false;
						}
					}

					for (var i = 1; i < headers.length && (rule_join[0].checked || rule_join[1].checked); i++) {
						if (headers[i].value == '') {
							alert(rcmail.gettext('noheader','sieverules'));
							headers[i].focus();
							return false;
						}

						if (!header_test.test(headers[i].value)) {
							alert(rcmail.gettext('headerbadchars','sieverules'));
							headers[i].focus();
							return false;
						}

						if (bodyparts[i].value == 'content' && contentparts[i].value == '') {
							alert(rcmail.gettext('nobodycontentpart','sieverules'));
							contentparts[i].focus();
							return false;
						}

						if (targets[i] && dateparts[i].value != 'weekday' && ops[i].value.indexOf("exists") == -1 && ops[i].value.indexOf("advoptions") == -1 && targets[i].value == '') {
							alert(rcmail.gettext('noheadervalue','sieverules'));
							targets[i].focus();
							return false;
						}

						if (advtargets[i] && dateparts[i].value != 'weekday' && ops[i].value.indexOf("advoptions") != -1 && advtargets[i].value == '') {
							alert(rcmail.gettext('noheadervalue','sieverules'));
							advtargets[i].focus();
							return false;
						}

						if (headers[i].value == 'size' && !size_test.test(targets[i].value)) {
							alert(rcmail.gettext('sizewrongformat','sieverules'));
							targets[i].focus();
							return false;
						}

						if (headers[i].value == 'spamtest') {
							targets[i].value = document.getElementsByName('_spam_probability[]')[i].value;
						}

						if (headers[i].value == 'virustest') {
							targets[i].value = document.getElementsByName('_virus_probability[]')[i].value;
						}

						if (headers[i].value == 'body' && (advops[i].value.indexOf('user') > -1 || advops[i].value.indexOf('detail') > -1 || advops[i].value.indexOf('domain') > -1)) {
							alert(rcmail.gettext('badoperator','sieverules'));
							advops[i].focus();
							return false;
						}

						if ((headers[i].value == 'date' || headers[i].value == 'currentdate')) {
							if (dateparts[i].value == 'date' && !date_test.test(targets[i].value)) {
								alert(rcmail.gettext('baddateformat','sieverules'));
								targets[i].focus();
								return false;
							}
							else if (dateparts[i].value == 'time' && !time_test.test(targets[i].value)) {
								alert(rcmail.gettext('badtimeformat','sieverules'));
								targets[i].focus();
								return false;
							}
						}
					}

					for (var i = 1; i < acts.length; i++) {
						var idx = acts[i].selectedIndex;

						if (acts[i][idx].value == 'fileinto' || acts[i][idx].value == 'fileinto_copy') {
							if (folders[i].value == '@@newfolder' && customfolders[i].value == '') {
								alert(rcmail.gettext('missingfoldername','sieverules'));
								customfolders[i].focus();
								return false;
							}
						}
						else if (acts[i][idx].value == 'redirect' || acts[i][idx].value == 'redirect_copy') {
							if (addrs[i].value == '') {
								alert(rcmail.gettext('noredirect','sieverules'));
								addrs[i].focus();
								return false;
							}

							if (!rcube_check_email(addrs[i].value.replace(/^\s+/, '').replace(/[\s,;]+$/, ''), true)) {
								alert(rcmail.gettext('redirectaddresserror','sieverules'));
								addrs[i].focus();
								return false;
							}
						}
						else if (acts[i][idx].value == 'reject' || acts[i][idx].value == 'ereject') {
							if (rejects[i].value == '') {
								alert(rcmail.gettext('noreject','sieverules'));
								rejects[i].focus();
								return false;
							}
						}
						else if (acts[i][idx].value == 'vacation') {
							if (senders[i].value != '' && senders[i].value != 'auto' && !rcube_check_email(senders[i].value.replace(/^\s+/, '').replace(/[\s,;]+$/, ''), true) && !$.isNumeric(senders[i].value)) {
								alert(rcmail.gettext('redirectaddresserror','sieverules'));
								senders[i].focus();
								return false;
							}

							if (aliases[i].value.indexOf(' ') > -1 || aliases[i].value.indexOf(';') > -1) {
								alert(rcmail.gettext('vactoexp_err','sieverules'));
								aliases[i].focus();
								return false;
							}

							if (days[i].value == '') {
								alert(rcmail.gettext('vacnodays','sieverules'));
								days[i].focus();
								return false;
							}

							if (!size_test.test(days[i].value) || days[i].value < 1) {
								alert(rcmail.gettext('vacdayswrongformat','sieverules'));
								days[i].focus();
								return false;
							}

							//if (subjects[i].value == '') {
							//	alert(rcmail.gettext('vacnosubject','sieverules'));
							//	subjects[i].focus();
							//	return false;
							//}

							var editor = tinyMCE.get("rcmfd_sievevacmag_" + (i - 1));
							if ((editor && editor.getContent() == '') || (!editor && msgs[i].value == '')) {
								alert(rcmail.gettext('vacnomsg','sieverules'));
								msgs[i].focus();
								return false;
							}
						}
						else if (acts[i][idx].value == 'notify' || acts[i][idx].value == 'enotify') {
							if (nmethods[i].value == '') {
								alert(rcmail.gettext('notifynomethod','sieverules'));
								nmethods[i].focus();
								return false;
							}

							if (acts[i][idx].value == 'enotify' && nmethods[i].value.indexOf(':') == -1) {
								alert(rcmail.gettext('notifyinvalidmethod','sieverules'));
								nmethods[i].focus();
								return false;
							}

							if (nmsgs[i].value == '') {
								alert(rcmail.gettext('notifynomsg','sieverules'));
								nmsgs[i].focus();
								return false;
							}
						}
						else if (acts[i][idx].value == 'editheaderadd' || acts[i][idx].value == 'editheaderrem') {
							if (eheadernames[i].value == '') {
								alert(rcmail.gettext('eheadernoname','sieverules'));
								eheadernames[i].focus();
								return false;
							}

							if (acts[i][idx].value == 'editheaderadd') {
								if (eheadervals[i].value == '') {
									alert(rcmail.gettext('eheadernoval','sieverules'));
									eheadervals[i].focus();
									return false;
								}
							}
						}
					}

					// enable the comparators field
					for (var i = 0; i < document.getElementsByName('_comparator[]').length; i++)
						document.getElementsByName('_comparator[]')[i].disabled = false;

					rcmail.gui_objects.editform.submit();
				}, true);

				rcmail.register_command('plugin.sieverules.vacation_sig', function(id) {
					var obj = document.getElementById("rcmfd_sievevacfrom_" + id);
					var is_html = ($("#rcmfd_sievevachtmlcb_" + id).is(':checked'));

					if (!obj || !obj.options)
						return false;

					var sig, id;
					var sig_separator = '-- ';

					if (obj.options[0].value == 'auto' || obj.options[0].value == '')
						id = obj.selectedIndex;
					else
						id = obj.selectedIndex + 1;

					if (is_html) {
						var editor = tinyMCE.get("rcmfd_sievevacmag_" + id),
						sigElem = editor.dom.get('_rc_sig');

						// Append the signature as a div within the body
						if (!sigElem) {
							var body = editor.getBody(),
							doc = editor.getDoc();

							sigElem = doc.createElement('div');
							sigElem.setAttribute('id', '_rc_sig');

							if (bw.ie)  // add empty line before signature on IE
								body.appendChild(doc.createElement('br'));

							body.appendChild(sigElem);
						}

						if (rcmail.env.signatures[id]) {
							if (rcmail.env.signatures[id].is_html) {
								sig = rcmail.env.signatures[id].text;
								if (!rcmail.env.signatures[id].plain_text.match(/^--[ -]\r?\n/m))
									sig = sig_separator + '<br />' + sig;
							}
							else {
								sig = rcmail.env.signatures[id].text;
								if (!sig.match(/^--[ -]\r?\n/m))
									sig = sig_separator + '\n' + sig;

								sig = '<pre>' + sig + '</pre>';
							}

							sigElem.innerHTML = sig;
						}
					}
					else {
						var input_message = $("#rcmfd_sievevacmag_" + id);
						var message = input_message.val();

						if (rcmail.env.signatures && rcmail.env.signatures[id]) {
							sig = rcmail.env.signatures[id]['text'];
							sig = sig.replace(/\r\n/g, '\n');

							if (!sig.match(/^--[ -]\n/))
								sig = sig_separator + '\n' + sig;

							message = message.replace(/[\r\n]+$/, '');
							message += '\n\n' + sig;
						}

						input_message.val(message);
					}

					return false;
				}, false);

				// enable commands
				if (rcube_find_object('rules-table').tBodies[0].rows.length > 6)
					rcmail.enable_command('plugin.sieverules.del_rule', true);

				if (rcube_find_object('actions-table').tBodies[0].rows.length > 2)
					rcmail.enable_command('plugin.sieverules.del_action', true);

				rcmail.enable_command('toggle-editor', true);

				// enable sig button
				var acts = document.getElementsByName('_act[]');
				for (var i = 1; i < acts.length; i++) {
					var idx = acts[i].selectedIndex;

					if (acts[i][idx].value == 'vacation')
						rcmail.enable_sig(document.getElementsByName('_vacfrom[]')[i]);
				}

				// add input masks
				rcmail.add_onload(function setup_inputmasks() {
					// date/time inputs
					headers = document.getElementsByName('_selheader[]');
					for (var i = 0; i < headers.length; i++) {
						if (headers[i].value.indexOf('date::') == 0) {
							var obj = document.getElementsByName('_datepart[]')[i];
							var target_obj = $("input[name='_target[]']")[i];

							$(target_obj).datepicker("destroy");
							$(target_obj).unmask();

							if (obj.value == 'date')
								$(target_obj).datepicker({ dateFormat: 'yy-mm-dd' });
							else if (obj.value == 'time')
								$(target_obj).mask('99:99:99', {example: 'HH:MM:SS', placeholder: '0'});
						}

					}
				});
			}
		});
	}
});