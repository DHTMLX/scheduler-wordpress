function dhtmlxSchedulerConfig(init) {
	var self = this;
	this.loaded = 0;

	var base = typeof(init.parent) == 'string' ? document.getElementById(init.parent) : init.parent;
	this.url = init.url ? init.url : './';
	this.url_load = init.url_load ? init.url_load : './';

	if (init.hidden) {
		this.hidden = typeof(init.hidden) == 'string' ? document.getElementById(init.hidden) : init.hidden;
	} else {
		return false;
	}
	if (init.debug) {
		this.debug = typeof(init.debug) == 'string' ? document.getElementById(init.debug) : init.debug;
	} else {
		this.debug = null;
	}


	// user groups list
	this.groups = init.groups || [];
	this.groups.splice(0, 0, { id: -1, label: 'Default'});

	if (init.wp_specific == undefined) {
		this.wp_specific = true;
	} else {
		this.wp_specific = init.wp_specific;
	}

	this.block = document.createElement('div');
	this.block.style.cssText = "background-image: url('" + this.url + "imgs/loading.gif'); position: absolute; width: 100%; height: 100%; z-index: 9999; background-position: center center; background-repeat: no-repeat;";
	base.appendChild(this.block);

	this.acc = new dhtmlXAccordion(base);
	this.acc.setSkin("dhx_skyblue");
	this.acc.addItem("a1", this.i18n.settings.settings);
    this.acc.addItem("a2", this.i18n.access.access);
    this.acc.addItem("a3", this.i18n.templates.templates);
    this.acc.addItem("a4", this.i18n.admin_events.admin_events);
	this.acc.addItem("a5", this.i18n.customfields.customfields);
	this.acc.addItem("google", this.i18n.gcal.google_export);
	this.acc.addItem("skins", this.i18n.skins.skins);
    this.acc.openItem("a1");

	this.acc.cells("a1").attachURL(this.url + "iframes/settings.html");
	this.acc.cells("a2").attachURL(this.url + "iframes/access_rights.html");
	this.acc.cells("a3").attachURL(this.url + "iframes/templates.html");
	this.acc.cells("a5").attachURL(this.url + "iframes/custom_fields.html");
	this.acc.cells("google").attachURL(this.url + "iframes/google.html");
	this.acc.cells("skins").attachURL(this.url + "iframes/skins.html");
	
	this.acc.attachEvent("onBeforeActive", function(itemId){
		setTimeout(function() {
			self.getXML();
		}, 1);
        return true;
	});

	this.toolbar = this.acc.cells("a4").attachToolbar();
	this.toolbar.setIconsPath(this.url + "common/imgs/");
	this.toolbar.addButton('add', 1, this.i18n.admin_events.add, 'new.gif', 'new_dis.gif');
	this.toolbar.addButton('edit', 2, this.i18n.admin_events.edit, 'settings.gif', 'settings.gif');
	this.toolbar.addButton('delete', 3, this.i18n.admin_events.remove, 'close.gif', 'close.gif');
	this.toolbar.addButton('reload', 3, this.i18n.admin_events.reload, 'reload.gif', 'reload.gif');
	this.toolbar.attachEvent("onClick", function(id) {
		self._toolbarClicked(id);
	});

	this.grid = this.acc.cells("a4").attachGrid();
	this.grid.setImagePath(this.url + "imgs/");
	this.grid.enableMultiselect(true);
	this.grid.init();
	this.grid.setSkin("dhx_skyblue");

	this.dp = new dataProcessor(this.url_load + "&grid_events=true");
	this.dp.init(this.grid);
	this.grid.loadXML(this.url_load + "&grid_events=true");
}


dhtmlxSchedulerConfig.prototype = {

	_toolbarClicked: function(id) {
		switch (id) {

			case 'add':
				this._addEvent();
				break;

			case 'edit':
				var sel = this.grid.getSelectedRowId();
				if (sel === null) {
					alert('Event should be selected...');
					break;
				}
				this.grid.selectCell(sel, 0);
				var self = this;
				setTimeout(function() {
					self.grid.editCell();
					self = null;
				}, 1);
				this.grid.editCell();
				break;

			case 'delete':
				var sel = this.grid.getSelectedRowId();
				if (sel == null) {
					alert('Event should be selected...');
					break;
				}
				sel = sel.split(',');
				if (confirm('Do you want to delete this event?')) {
					for (var i = 0; i < sel.length; i++)
						if (this.grid.doesRowExist(sel[i]))
							this.grid.deleteRow(sel[i]);
				}
				break;
			case 'reload':
				this.grid.clearAll();
				this.grid._colls_loaded = false;
				this.grid.loadXML(this.url_load + "&grid_events=true");
				break;
		}
	},


	_addEvent: function() {
		var d = new Date();
		var std = this._dateToStr(d);
		d.setMinutes(d.getMinutes() + 5);
		var endd = this._dateToStr(d);
		this.grid.addRow(this.grid.uid(), this.i18n.admin_events.new_event + ',' + std + ',' + endd);
	},
	
	_dateToStr: function(d) {
		str = d.getFullYear().toString() + '-';
		var t = (d.getMonth() + 1).toString();
		if (t.length < 2) t = '0' + t;
		str += t + '-';

		t = d.getDate().toString();
		if (t.length < 2) t = '0' + t;
		str += t + ' ';

		t = d.getHours().toString();
		if (t.length < 2) t = '0' + t;
		str += t + ':';

		t = d.getMinutes().toString();
		if (t.length < 2) t = '0' + t;
		str += t + ':';

		t = d.getSeconds().toString();
		if (t.length < 2) t = '0' + t;
		str += t;
		return str;
	},

	getXML: function() {
		var xml = '<config>';
		xml += '<active_tab>' + this.getActiveCell() + '</active_tab>';
		xml += '<settings>' + this.acc.cells("a1").getFrame().contentWindow.getOptions() + "</settings>";
		xml += '<access>' + this.acc.cells("a2").getFrame().contentWindow.getOptions() + "</access>";
		xml += '<templates>' + this.acc.cells("a3").getFrame().contentWindow.getOptions() + '</templates>';
		var isMap = this.acc.cells("a1").getFrame().contentWindow.isMap();
		xml += '<customfields>' + this.acc.cells("a5").getFrame().contentWindow.getOptions(isMap) + '</customfields>';
		xml += '<google>' + this.acc.cells("google").getFrame().contentWindow.getOptions() + '</google>';
		xml += '<skins>' + this.acc.cells("skins").getFrame().contentWindow.getOptions() + '</skins>';
		xml += '</config>';

		var reg1=/</g;
		var reg2=/>/g; 
		xml = xml.replace(reg1, '&ltesc;');
		xml = xml.replace(reg2, '&gtesc;');

		this.hidden.value = xml;
		if (this.debug) {
			var reg1=/</g;
			var reg2=/>/g; 
			var reg3 = /&lt;&gt;</;
			xml = xml.replace(reg3, '');
			xml = xml.replace(reg1, '&lt;');
			xml = xml.replace(reg2, '&gt;');
			xml = xml.replace(reg3, '&lt;<br>&gt;');
			this.debug.innerHTML = xml;
		}
	},

	loadingCheck: function() {
		if (this.loaded >= 6) {
			this.loadXML();
		}
	},

	loadXML: function() {
		var self = this;
		dhtmlxAjax.get(this.url_load + "&config_xml=true?config_xml=true&nocache=" + new Date().getTime(), function(loader) {
			self.hidden.value = loader.xmlDoc.responseText;
			self.active_cell = loader.xmlDoc.responseXML.childNodes[0].childNodes[0].childNodes[0].nodeValue;
			self.acc.cells(self.active_cell).open();
			self.acc.cells("a1").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[1]);
			self.acc.cells("a2").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[2]);
			self.acc.cells("a3").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[3]);
			self.acc.cells("a5").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[4]);
			self.acc.cells("google").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[5]);
			self.acc.cells("skins").getFrame().contentWindow.setOptions(loader.xmlDoc.responseXML.childNodes[0].childNodes[6]);
			self.getXML();
			self.block.style.display = 'none';
		});
	},

	getActiveCell: function() {
		var self = this;
		this.active_cell = false;
		this.acc.forEachItem(function(item){
			if (item.isOpened()) {
				self.active_cell = item.getId();
			}
		});
		return this.active_cell;
	}

}



dhtmlxSchedulerConfig.prototype.i18n = {

	settings: {
		settings: "Settings",
		sizes: "Sizes",
		width: "Scheduler width",
		height: "Scheduler height",
		wp: "Wordpress Specific",
		debug_panel: "Debug",
		debug: "Debug mode",
		global: "General settings",
		repeat: "Repeat events",
		hr12: "12-hour time mode",
		firstday: "Sunday is the first day of the week",
		multiday: "Multiday events in day and week views",
		fullday: "Full day events",
		marknow: "Mark now",
		singleclick: "Create events by single-click",
		modes: "Modes",
		day: "Day",
		week: "Week",
		month: "Month",
		agenda: "Agenda",
		week_agenda: "Week agenda",
		year: "Year",
		map: "Map",
		defaultmode: "Default mode",
		posts: "Show blog posts as events",
		events_number: "Number of events in widget",
		link: "Link to the Scheduler",
		collision: "Prevent events overlapping",
		expand: "Expand button",
		print: "Print to PDF",
		ical: "Export to iCal",
		minical: "Mini-calendar navigation",
		about_ext: "See more about extensions",
		here: "here"
	},

	access: {
		access: "Access rights",
		guests: "Guest",
		registred: "Registred user",
		subscriber: "Subscriber",
		contributor: "Contributor",
		author: "Author",
		editor: "Editor",
		publisher: "Publisher",
		manager: "Manager",
		administrator: "Administrator",
		superadministrator: "Super administrator",
		view: "View",
		add: "Add",
		edit: "Edit",
		off: "Off",
		privatemode: "Private mode",
		privatemodeext: "Private extended mode"
	},

	templates: {
		templates: "Templates",
		dateformats: "Date formats",
		scales: "Scales",
		minmin: "Minimal step of event duration (in minutes)",
		hourheight: "Height of 1 hour in pixels",
		starthour: "Starting time (in hours) for time scale in day and week views",
		endhour: "Ending time (in hours) for time scale in day and week views",
		eventtemplates: "Event templates",
		about_formats: "See more about date formats",
		about_scales: "See more about scales",
		about_templates: "See more about event templates",
		here: "here",
		agendatime: "Time period for Agenda and Map views (in days)",
		default_date: "Default date",
		month_date: "Month date",
		week_date: "Week date",
		day_date: "Day date",
		hour_date: "Hour date",
		month_day: "Month day",
		username: "Render event author name"
	},

	admin_events: {
		admin_events: "Events administration",
		add: "Add",
		edit: "Edit",
		remove: "Delete",
		new_event: "New event",
		reload: "Reload"
	},

	customfields: {
		customfields: "Custom fields",
		name: "Name",
		dsc: "Description",
		type: "Type",
		height: "Height",
		colors: "Use colors for events",
		textarea: "Textarea",
		select: "Select list",
		label: "Option name",
		color: "Color",
		hide: "Hide",
		add_new_field: "Add new field",
		delete_field: "Delete field",
		add_option: "Add new option",
		units: "Use as Units",
		about_customfields: "See more about customization details form",
		here: "here",
		timeline: "Timeline",
		off: "Off",
		day: "Day",
		threedays: "3 days",
		working_day: "Working day",
		week: "Week",
		working_week: "Working week",
		month: "Month",
		year: "Year",
		timeline_type: "Timeline type",
		second_scale: "Timeline second scale"
	},

	gcal: {
		google_export: "Google calendar export",
		account: "Account information",
		email: "Email",
		pass: "Password*",
		cal: "Calendar id**",
		pass_notice: "*This password is not saved in the configuration.<br>It's used in real time to load events from Google calendar.",
		cal_notice: "**Keep the field empty to apply the default calendar",
		controls: "Import/Export",
		error_curl: "Curl php extension is required",
		error_auth: "Authentication is failed",
		error_cal: "Incorrect calendar id",
		success: "Well done"
	},

	skins: {
		skins: "Skining",
		base_colors: "Base colors",
		use_custom: "Use custom style",
		bg: "Background color",
		event: "Event color",
		apply: "Apply",
		preview: "Preview",
		presets: "Presets:",
		confirm: "Custom colors will be lost. Continue?"
	}

}