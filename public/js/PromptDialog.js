

function PromptDialog() {
	
	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// PRIVATE VARIABLES
	////////////////////////////////////////////////////////////////////////////////////////////////////////////	
	
	// do a body.append here...	
		
	$('body').append('<div class="modal hide" id="promptDialog" tabindex="-1" role="dialog" aria-labelledby="promptDialogLabel" aria-hidden="true">'
	  + '<div class="modal-header">'
	  + '<button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>'
	  + '<h4 id="promptDialogLabel"></h4>'
	  + '</div>'
	  + '<div class="modal-body">'
	  + '<p><input type="text" id="promptTextField" size="70"/></p>'
	  + '</div>'
	  + '<div class="modal-footer">'
	  + '<button id="okayPromptDialog" class="btn btn-primary">Okay</button> '
	  + '<button id="cancelPromptDialog">Cancel</button>'
	  + '</div>'
	  + '</div>');

	var _this = this;

	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//PRIVATE METHODS
	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	$('#cancelPromptDialog').live('click', function(){
		//_dlg.dialog('close');
		//_dlg.unbind('submit');
		$('#promptDialog').modal('hide');
		$('#okayPromptDialog').unbind('click');
	});

	$('#okayPromptDialog').live('click', function(){
		$('#promptDialog').modal('hide');
		$('#okayPromptDialog').unbind('click');
	});
	

	////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//PRIVILEGED METHODS
	////////////////////////////////////////////////////////////////////////////////////////////////////////////


	this.open = function(prompt, textFieldVal, submitFunction) {
		/*
		_dlg.dialog('option', 'title', prompt);
		$('#promptTextField').val(textFieldVal);

		_dlg.dialog('open');

		$('#okayPromptDialog').click(submitFunction);*/
		$('#promptDialogLabel').text(prompt);
		$('#promptTextField').val(textFieldVal);
		$('#promptDialog').modal('show');
		$('#okayPromptDialog').click(submitFunction);
	};

	
}
