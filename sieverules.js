/* Show sieverules plugin script */

if (window.rcmail) {
	rcmail.addEventListener('init', function(evt) {
		if (rcmail.env.action == 'plugin.sieverules.add' || rcmail.env.action == 'plugin.sieverules.edit' || rcmail.env.action == 'plugin.sieverules.setup' || rcmail.env.action == 'plugin.sieverules.advanced')
			var tab = $('<span>').attr('id', 'settingstabpluginsieverules').addClass('tablink-selected');
		else
			var tab = $('<span>').attr('id', 'settingstabpluginsieverules').addClass('tablink');

		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.sieverules').html(rcmail.gettext('filters','sieverules')).appendTo(tab);
		button.bind('click', function(e){ return rcmail.command('plugin.sieverules', this) });

		// add button and register command
		rcmail.add_element(tab, 'tabs');
		rcmail.register_command('plugin.sieverules', function(){ rcmail.goto_url('plugin.sieverules') }, true);

		if ((rcmail.env.action == 'plugin.sieverules' || rcmail.env.action == 'plugin.sieverules.add' || rcmail.env.action == 'plugin.sieverules.edit') && !rcmail.env.sieveruleserror) {
			if (rcmail.gui_objects.sieverules_list) {
				rcmail.sieverules_list = new rcube_list_widget(rcmail.gui_objects.sieverules_list, {multiselect:false, draggable:false, keyboard:false});
				rcmail.sieverules_list.addEventListener('select', function(o){ rcmail.sieverules_select(o); });
				rcmail.sieverules_list.init();
				rcmail.sieverules_list.focus();

				if (!isNaN(rcmail.env.iid))
					rcmail.sieverules_list.highlight_row(rcmail.env.iid);
			}

			if (rcmail.gui_objects.sieverules_examples) {
				rcmail.sieverules_examples = new rcube_list_widget(rcmail.gui_objects.sieverules_examples, {multiselect:false, draggable:false, keyboard:false});
				rcmail.sieverules_examples.addEventListener('select', function(o){ rcmail.sieverules_select(o); });
				rcmail.sieverules_examples.init();

				if (rcmail.env.eid)
					rcmail.sieverules_examples.highlight_row(rcmail.env.eid);
			}

			rcmail.register_command('plugin.sieverules.moveup', function(id){
				if (id > 0) {
					rcmail.set_busy(true, 'sieverules.movingfilter');
		    		rcmail.http_request('plugin.sieverules.move', '_operation=up&_iid=' + rcmail.sieverules_list.rows[id].uid, true);
	    		}
			}, true);

			rcmail.register_command('plugin.sieverules.movedown', function(id){
				var rows = rcmail.sieverules_list.rows;

				if (id < rows.length - 1) {
					rcmail.set_busy(true, 'sieverules.movingfilter');
			    	rcmail.http_request('plugin.sieverules.move', '_operation=down&_iid=' + rcmail.sieverules_list.rows[id].uid, true);
		    	}
			}, true);

			rcmail.register_command('plugin.sieverules.add', function(id){ rcmail.goto_url('plugin.sieverules.add', '', true); }, true);
			rcmail.enable_command('plugin.sieverules.add', true);
		}
		else if (rcmail.env.action == 'plugin.sieverules.setup') {
			rcmail.register_command('plugin.sieverules.import', function(props){ rcmail.goto_url('plugin.sieverules.import', props, true); }, true);
			rcmail.enable_command('plugin.sieverules.import', true);
		}
		else if (rcmail.env.action == 'plugin.sieverules.advanced') {
			rcmail.register_command('plugin.sieverules.save', function(){ rcmail.gui_objects.editform.submit(); }, true);
			rcmail.enable_command('plugin.sieverules.save', true);
		}

		if (rcmail.env.action == 'plugin.sieverules.add' || rcmail.env.action == 'plugin.sieverules.edit') {
			rcmail.register_command('plugin.sieverules.add_rule', function(props, obj){
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

				var xheadsTable = newNode2.cells[0].childNodes[0];
				var advTable = newNode3.cells[0].childNodes[0];
				var randId = Math.random();

				if (!xheadsTable.cells)
					xheadsTable.innerHTML = xheadsTable.innerHTML.replace(/rowid/g, randId);
				else for (var i = 0; i < xheadsTable.cells.length; i++)
					xheadsTable.cells[i].innerHTML = xheadsTable.cells[i].innerHTML.replace(/rowid/g, randId);

				if (!advTable.cells)
					advTable.innerHTML = advTable.innerHTML.replace(/rowid/g, randId);
				else for (var i = 0; i < advTable.cells.length; i++)
					advTable.cells[i].innerHTML = advTable.cells[i].innerHTML.replace(/rowid/g, randId);

				// remove nohtc class (IE6 fix)
				newNode1.cells[1].innerHTML = newNode1.cells[1].innerHTML.replace(/class=["']?nohtc["']? /ig, "");
				newNode1.cells[4].innerHTML = newNode1.cells[4].innerHTML.replace(/class=["']?nohtc["']? /ig, "");

				newNode1.style.display = "";
				newNode2.style.display = "none";
				newNode3.style.display = "none";

				return false;
			}, true);

			rcmail.register_command('plugin.sieverules.del_rule', function(props, obj){
				var rulesTable = rcube_find_object('rules-table').tBodies[0];

				if (rulesTable.rows.length == 6)
					return false;

				if (confirm(rcmail.gettext('ruledeleteconfirm','sieverules'))) {
					rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex + 2);
					rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex + 1);
					rulesTable.deleteRow(obj.parentNode.parentNode.rowIndex);
				}

				return false;
			}, true);

			rcmail.register_command('plugin.sieverules.add_action', function(props, obj){
				var actsTable = rcube_find_object('actions-table').tBodies[0];
				var idx = obj.parentNode.parentNode.rowIndex + 1;
				var newNode = actsTable.rows[0].cloneNode(true);

				if (idx < actsTable.rows.length)
					actsTable.insertBefore(newNode, actsTable.rows[idx]);
				else
					actsTable.appendChild(newNode);

				var vacsTable = newNode.cells[1].childNodes[3];
				var notifyTable = newNode.cells[1].childNodes[5];
				var randId = Math.random();

				if (!vacsTable.cells)
					vacsTable.innerHTML = vacsTable.innerHTML.replace(/rowid/g, randId);
				else for (var i = 0; i < vacsTable.cells.length; i++)
					vacsTable.cells[i].innerHTML = vacsTable.cells[i].innerHTML.replace(/rowid/g, randId);

				if (!notifyTable.cells)
					notifyTable.innerHTML = notifyTable.innerHTML.replace(/rowid/g, randId);
				else for (var i = 0; i < notifyTable.cells.length; i++)
					notifyTable.cells[i].innerHTML = notifyTable.cells[i].innerHTML.replace(/rowid/g, randId);

				// remove nohtc class (IE6 fix)
				newNode.cells[2].innerHTML = newNode.cells[2].innerHTML.replace(/class=["']?nohtc["']? /ig, "");

				newNode.style.display = "";

				return false;
			}, true);

			rcmail.register_command('plugin.sieverules.del_action', function(props, obj){
				var actsTable = rcube_find_object('actions-table').tBodies[0];

				if (actsTable.rows.length == 2)
					return false;

				if (confirm(rcmail.gettext('actiondeleteconfirm','sieverules')))
					actsTable.deleteRow(obj.parentNode.parentNode.rowIndex);

				return false;
			}, true);

			rcmail.register_command('plugin.sieverules.save', function(){
				var rows = rcmail.sieverules_list.rows;
				var input_name = rcube_find_object('_name');
				var rule_join = document.getElementsByName('_join');
				var headers = document.getElementsByName('_header[]');
				var ops = document.getElementsByName('_operator[]');
				var targets = document.getElementsByName('_target[]');
				var advtargets = document.getElementsByName('_advtarget[]');
				var acts = document.getElementsByName('_act[]');
				var addrs = document.getElementsByName('_redirect[]');
				var rejects = document.getElementsByName('_reject[]');
				var days = document.getElementsByName('_day[]');
				var subjects = document.getElementsByName('_subject[]');
				var msgs = document.getElementsByName('_msg[]');
				var nmethods = document.getElementsByName('_nmethod[]');
				var nmsgs = document.getElementsByName('_nmsg[]');
				var size_test = new RegExp('^[0-9]+$');
				var header_test = new RegExp('^[a-zA-Z0-9\-]+$');

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

					if (targets[i] && ops[i].value.indexOf("exists") == -1 && ops[i].value.indexOf("advoptions") == -1 && targets[i].value == '') {
						alert(rcmail.gettext('noheadervalue','sieverules'));
						targets[i].focus();
						return false;
					}

					if (advtargets[i] && ops[i].value.indexOf("advoptions") != -1 && advtargets[i].value == '') {
						alert(rcmail.gettext('noheadervalue','sieverules'));
						advtargets[i].focus();
						return false;
					}

					if (headers[i].value == 'size' && !size_test.test(targets[i].value)) {
						alert(rcmail.gettext('sizewrongformat','sieverules'));
						targets[i].focus();
						return false;
					}
				}

				for (var i = 1; i < acts.length; i++) {
					var idx = acts[i].selectedIndex;

					if (acts[i][idx].value == 'redirect') {
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
					else if (acts[i][idx].value == 'reject') {
						if (rejects[i].value == '') {
							alert(rcmail.gettext('noreject','sieverules'));
							rejects[i].focus();
							return false;
						}
					}
					else if (acts[i][idx].value == 'vacation') {
						if (days[i].value == '') {
							alert(rcmail.gettext('vacnodays','sieverules'));
							days[i].focus();
							return false;
						}

						if (!size_test.test(days[i].value)) {
							alert(rcmail.gettext('vacdayswrongformat','sieverules'));
							days[i].focus();
							return false;
						}

						if (subjects[i].value == '') {
							alert(rcmail.gettext('vacnosubject','sieverules'));
							subjects[i].focus();
							return false;
						}

						if (msgs[i].value == '') {
							alert(rcmail.gettext('vacnomsg','sieverules'));
							msgs[i].focus();
							return false;
						}
					}
					else if (acts[i][idx].value == 'notify') {
						if (nmethods[i].value == '') {
							alert(rcmail.gettext('notifynomothod','sieverules'));
							nmethods[i].focus();
							return false;
						}

						if (nmsgs[i].value == '') {
							alert(rcmail.gettext('notifynomsg','sieverules'));
							nmsgs[i].focus();
							return false;
						}
					}
				}

				// enable the comparators field
				for (var i = 0; i < document.getElementsByName('_comparator[]').length; i++)
					document.getElementsByName('_comparator[]')[i].disabled = false;

				rcmail.gui_objects.editform.submit();
			}, true);

			rcmail.register_command('plugin.sieverules.delete', function(id){
				if (confirm(rcmail.gettext('filterdeleteconfirm','sieverules')))
					rcmail.goto_url('plugin.sieverules.delete', '_iid=' + rcmail.env.iid, true);
			}, true);

			rcmail.enable_command('plugin.sieverules.save', 'plugin.sieverules.delete', true);
		}
	})
}

rcmail.sieverules_select = function(rule) {
    var id;

    if (id = rule.get_single_selection())
		rcmail.sieverules_load(id, 'plugin.sieverules.edit');
}

rcmail.sieverules_load = function(id, action) {
    if (action == 'plugin.sieverules.edit' && (!id || id==rcmail.env.iid))
		return false;

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

    return true;
}

rcmail.sieverules_update_list = function(action, name, id) {
	var sid = rcmail.sieverules_list.get_single_selection();
	id = parseInt(id);

	if (action == 'moveup') {
		var rows = rcmail.sieverules_list.rows;
		var from;

		// we need only to replace filter names...
		for (var i = 0; i < rows.length; i++)
		{
			if (rows[i] == null) { // removed row
				continue;
			}
			else if (rows[i].uid == id - 1) {
				from = rows[i].obj.cells[0];
			}
			else if (rows[i].uid == id) {
				name = rows[i].obj.cells[0].innerHTML;
				rows[i].obj.cells[0].innerHTML = from.innerHTML;
				from.innerHTML = name;
				break;
			}
		}

		if (sid == id)
			rcmail.sieverules_list.highlight_row(id - 1);
		else if (sid == id - 1)
			rcmail.sieverules_list.highlight_row(id);
	}
	else if (action == 'movedown') {
		var rows = rcmail.sieverules_list.rows;
		var from;

		// we need only to replace filter names...
		for (var i = 0; i < rows.length; i++) {
			if (rows[i] == null) { // removed row
				continue;
			}
			else if (rows[i].uid == id) {
				from = rows[i].obj.cells[0];
			}
			else if (rows[i].uid == id + 1){
				name = rows[i].obj.cells[0].innerHTML;
				rows[i].obj.cells[0].innerHTML = from.innerHTML;
				from.innerHTML = name;
				break;
			}
		}

		if (sid == id)
			rcmail.sieverules_list.highlight_row(id + 1);
		else if (sid == id + 1)
			rcmail.sieverules_list.highlight_row(id);
	}

	// update iid of rule being editied
	var iid;
	if (iid = rcube_find_object('_iid')) {
		if (iid.value != "")
			iid.value = rcmail.sieverules_list.get_single_selection();
	}
}

rcmail.sieverules_rule_join_radio = function(value) {
	var rulesTable = rcube_find_object('rules-table');

	if (rulesTable.tBodies[0].rows.length == 3)
		sieverule_addrule(rulesTable.tBodies[0].rows[0]);

	rulesTable.style.display = (value == 'any' ? 'none' : '');
}

rcmail.sieverules_header_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex / 3;
    var obj = document.getElementsByName('_selheader[]')[idx];
	var testType = obj.value.split('::')[0];
	var header = obj.value.split('::')[1];
    var selIdx = 0;

	document.getElementsByName('_test[]')[idx].value = testType;
	document.getElementsByName('_header[]')[idx].value = header;
	document.getElementsByName('_target[]')[idx].style.width = '150px'
	document.getElementsByName('_operator[]')[idx].selectedIndex = 0;

    if (header == 'size') {
	    document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
	    document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
	    document.getElementsByName('_operator[]')[idx].style.display = 'none';
	    document.getElementsByName('_size_operator[]')[idx].style.display = '';
	    document.getElementsByName('_target[]')[idx].style.display = '';
	    document.getElementsByName('_target[]')[idx].style.width = '100px'
	    document.getElementsByName('_units[]')[idx].style.display = '';
    }
    else if (header.indexOf('predefined_') == 0) {
		document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
		document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		document.getElementsByName('_operator[]')[idx].style.display = 'none';
		document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
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
		if (header == 'other') {
			document.getElementsByName('_header[]')[idx].style.visibility = 'visible';
			document.getElementsByName('_headerhlp')[idx].style.visibility = 'visible';
			document.getElementsByName('_header[]')[idx].value = '';
		}
		else {
			document.getElementsByName('_header[]')[idx].style.visibility = 'hidden';
			document.getElementsByName('_headerhlp')[idx].style.visibility = 'hidden';
		}

	    document.getElementsByName('_operator[]')[idx].style.display = '';
	    document.getElementsByName('_size_operator[]')[idx].style.display = 'none';
	    document.getElementsByName('_target[]')[idx].style.display = '';
	    document.getElementsByName('_units[]')[idx].style.display = 'none';
    }

    var idx = sel.parentNode.parentNode.rowIndex;
    rcube_find_object('rules-table').tBodies[0].rows[idx + 1].style.display = 'none';
    rcube_find_object('rules-table').tBodies[0].rows[idx + 2].style.display = 'none';
}

rcmail.sieverules_rule_op_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var eidx = idx / 3;

    var obj = document.getElementsByName('_operator[]')[eidx];
    if (obj.value == 'exists' || obj.value == 'notexists' || obj.value == 'advoptions')
		document.getElementsByName('_target[]')[eidx].style.display = 'none';
    else
		document.getElementsByName('_target[]')[eidx].style.display = '';

	if (obj.value != 'exists' && obj.value != 'notexists' && document.getElementsByName('_test[]')[eidx].value == 'exists') {
    	var h_obj = document.getElementsByName('_selheader[]')[eidx];
		var testType = h_obj.value.split('::')[0];

		document.getElementsByName('_test[]')[eidx].value = testType;
	}

	var advopts_row = rcube_find_object('rules-table').tBodies[0].rows[idx + 2];
	advopts_row.style.display = (obj.value == 'advoptions' ? '' : 'none');

	return false;
}

rcmail.sieverules_rule_advop_select = function(sel) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var idx = (obj.parentNode.parentNode.rowIndex - 2) / 3;

	if (sel.value.substring(0, 5) == 'count' || sel.value.substring(0, 5) == 'value')
		document.getElementsByName('_comparator[]')[idx].disabled = false;
	else
		document.getElementsByName('_comparator[]')[idx].disabled = true;

	return false;
}

rcmail.sieverules_action_select = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex;
	var actoion_row = rcube_find_object('actions-table').tBodies[0].rows[idx];
    var obj = document.getElementsByName('_act[]')[idx];

    // hide everything
	document.getElementsByName('_folder[]')[idx].style.display = 'none';
	document.getElementsByName('_redirect[]')[idx].style.display = 'none';
	document.getElementsByName('_reject[]')[idx].style.display = 'none';
	document.getElementsByName('_imapflags[]')[idx].style.display = 'none';
	actoion_row.cells[1].childNodes[3].style.display = 'none';
	actoion_row.cells[1].childNodes[5].style.display = 'none';

    if (obj.value == 'fileinto')
		document.getElementsByName('_folder[]')[idx].style.display = '';
    else if (obj.value == 'reject')
		document.getElementsByName('_reject[]')[idx].style.display = '';
    else if (obj.value == 'vacation')
		actoion_row.cells[1].childNodes[3].style.display = '';
    else if (obj.value == 'notify')
		actoion_row.cells[1].childNodes[5].style.display = '';
    else if (obj.value == 'redirect')
		document.getElementsByName('_redirect[]')[idx].style.display = '';
    else if (obj.value == 'imapflags')
		document.getElementsByName('_imapflags[]')[idx].style.display = '';
}

rcmail.sieverules_xheaders = function(sel) {
	var idx = sel.parentNode.parentNode.rowIndex + 1;
	var xheader_row = rcube_find_object('rules-table').tBodies[0].rows[idx];
	xheader_row.style.display = (xheader_row.style.display == 'none' ? '' : 'none');
	return false;
}

rcmail.sieverules_set_xheader = function(sel) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var idx = (obj.parentNode.parentNode.rowIndex - 1) / 3;
	var headerBox = document.getElementsByName('_header[]')[idx];
	headerBox.value = sel.value;
}

rcmail.sieverules_get_index = function(list, value, fallback) {
	fallback = fallback || 0;

    for (var i = 0; i < list.length; i++) {
	    if (list[i].value == value)
    		return i;
   	}

   	return fallback;
}

rcmail.sieverules_toggle_vac_to = function(sel, id) {
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

rcmail.sieverules_toggle_vac_osubj = function(sel, id) {
	var obj = rcube_find_object('rcmfd_sievevactoh_' + id);
	obj.value = sel.checked ? sel.value : "";
}

rcmail.sieverules_toggle_notify_impt = function(sel, id) {
	var obj = rcube_find_object('rcmfd_sievenimpt_' + id);
	obj.value = sel.value;
}

rcmail.sieverules_help = function(sel, row) {
	var obj = sel.parentNode.parentNode.parentNode.parentNode;
	var help_row = obj.tBodies[0].rows[row];
	help_row.style.display = (help_row.style.display == 'none' ? '' : 'none');
	return false;
}

rcmail.sieverules_show_adv = function(sel) {
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

rcmail.sieverules_adveditor = function(sel) {
	if (sel.checked && !confirm(rcmail.gettext('switchtoadveditor','sieverules'))) {
		sel.checked = false;
		return false;
	}

	if (sel.checked)
		rcmail.goto_url('plugin.sieverules.advanced', '', true);
	else
		rcmail.goto_url('plugin.sieverules', '_override=1', true);
}