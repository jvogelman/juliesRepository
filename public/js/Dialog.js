

function Dialog() {
	
	// this is intended as a singleton, so if it already exists, return the singleton
	if ($('#dialog').size() > 1) {
		return _this;
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// PRIVATE VARIABLES
	////////////////////////////////////////////////////////////////////////////////////////////////////////////	
	
	// do a body.append here...	
		
	$('body').append('<div class="modal hide" id="dialog" tabindex="-1" role="dialog" aria-labelledby="dialogLabel" aria-hidden="true">'
	  + '<div class="modal-header">'
	  + '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>'
	  + '<h4 id="dialogLabel"></h4>'
	  + '</div>'
	  + '<div class="modal-body">'
	  + '<p class="modal-body-main"></p>'
	  + '<p class="modal-body-warning text-error"></p>'
	  + '</div>'
	  + '<div class="modal-footer">'
	  + '<button id="okayDialog" class="btn btn-primary">Okay</button> '
	  + '<button id="cancelDialog">Cancel</button>'
	  + '</div>'
	  + '</div>');

	var _this = this;

	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//PRIVATE METHODS
	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	$('#cancelDialog').live('click', function(){
		$('#dialog').modal('hide');
		$('#okayDialog').unbind('click');
	});

	/*$('#okayDialog').live('click', function(){
		$('#dialog').modal('hide');
		$('#okayDialog').unbind('click');
	});*/
	

	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//PRIVILEGED METHODS
	////////////////////////////////////////////////////////////////////////////////////////////////////////////


	this.open = function(header, body, submitFunction) {
		
		clear();
		
		$('#dialog #dialogLabel').text(header);
		$('#dialog .modal-body-main').html(body);
		
		$('#dialog').modal('show');
		// add callback to "Okay" button
		$('#okayDialog').click(submitFunction);
	};

	this.close = function() {
		$('#dialog').modal('hide');
		$('#okayDialog').unbind('click');
	};
	
	// clear header and body
	this.clear = function() {
		$('#dialog #dialogLabel').text('');
		$('#dialog .modal-body-main').html('');
		$('#dialog .modal-body-warning').html('');
	};
	
	// issue a warning at the bottom of the dialog
	this.warn = function(warning) {
		$('#dialog .modal-body-warning').html(warning);
		
	};
	
	return _this;
}
