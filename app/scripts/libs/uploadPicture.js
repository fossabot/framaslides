// Author tantaman
// License MIT
// originally http://github.com/tantaman/imgup
define(function() {
	var root = {};

;(function(root) {
	'use strict';

	function UploadHandler(xhr) {
		this._xhr = xhr;

		xhr.onload = handlerCallbacks.onload.bind(this);
		xhr.upload.onabort = xhr.onabort =
			xhr.upload.onerror = xhr.onerror =
				xhr.ontimeout = handlerCallbacks.onerror.bind(this);

		xhr.upload.onprogress = handlerCallbacks.onprogress.bind(this);

		this._progressBacks = [];
		this._errorBacks = [];
		this._thenBacks = [];
	}

	var handlerCallbacks = {
		onload: function() {
			var result = JSON.parse(this._xhr.responseText);

			if (!result) {
				handlerCallbacks.onerror.call(this, result);
			} else {
				this._thenBacks.forEach(function(cb) {
					cb(result);
				});
			}
		},

		onprogress: function(e) {
			var completed = e.loaded / e.total;
			this._progressBacks.forEach(function(cb) {
				cb(completed, e);
			});
		},

		onerror: function(e) {
			this._errorBacks.forEach(function(cb) {
				cb(e);
			});
		}
	};

	UploadHandler.prototype = {
		cancel: function() {
			this._xhr.abort();
			return this;
		},

		then: function(cb, ecb) {
			if (cb != null)
				this._thenBacks.push(cb);
			if (ecb != null)
				this._errorBacks.push(ecb);

			return this;
		},

		error: function(cb) {
			this._errorBacks.push(cb);

			return this;
		},

		progress: function(cb) {
			this._progressBacks.push(cb);

			return this;
		}
	};

	function UploadPicture(address) {
	  this.address = address
	}

  UploadPicture.prototype = {
		upload: function(file) {
			var form = new FormData();
			form.append('file', file);
			form.append('format', 'json');
			form.append('delete-day', 0);
			form.append('presentation', this.title);

			var xhr = new XMLHttpRequest();
			if (this.title != 'new-prez') {
        xhr.open('POST', this.address + '/new/' + this.title);
      } else {
        xhr.open('POST', this.address + '/new');
      }

			var handler = new UploadHandler(xhr);

			xhr.send(form);

			return handler;
		},
    setPresentationTitle: function (title) {
      this.title = title;
    }
	};

	root.UploadPicture = UploadPicture;
})(root);

	return root.UploadPicture;
});
