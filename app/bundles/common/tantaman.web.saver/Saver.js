define(function() {
	'use strict';

	function Saver(exportables, storageInterface, model) {
		this.storageInterface = storageInterface;
		if (Array.isArray(exportables)) {
			this.exportables = exportables;
		} else {
			this.exportables = [exportables];
		}
		this.model = model;
	}

	Saver.prototype = {
		__save: function() {
			// var data = exportable.export();
			// var identifier = exportable.identifier();
			this.exportables.forEach(function(exportable) {
				var data = exportable.export();
				var identifier = exportable.identifier();
				this.storageInterface.store(identifier, data, null, false, this.model);
			}, this);
		},

		save: function() {
			this.__save();
		}
	};

	return Saver;
});
