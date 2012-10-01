

// send a POST request (or optionally a GET request)
function post_to_url(path, params, method) {
    method = method || "post"; // Set method to post by default, if not specified.

    // The rest of this code assumes you are not using a library.
    // It can be made less wordy if you use one.
    var form = document.createElement("form");
    form.setAttribute("method", method);
    form.setAttribute("action", path);

    for(var key in params) {
        if(params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
         }
    }

    document.body.appendChild(form);
    form.submit();
}

// trim spaces before and after a string
String.prototype.trim = function() {
    return this.replace(/^\s+|\s+$/g, "");
};


$(document).ready(function(){
	

	$('#newStudy').live('click', function(e) {
		e.preventDefault();
		
		var modal = Dialog();
		modal.open('Please enter a name and optional description for your study',
				'<h5>Name: </h5>&nbsp;&nbsp;<input type="text" id="studyName"/>' +
				'<h5>Description: </h5>&nbsp;&nbsp;<textarea id="studyDescription"/>',
				function(e) {
					var name = $('#studyName').val().trim();
					var description = $('#studyDescription').val().trim();
					
					if (name == '') {
						modal.warn('***Name cannot be empty.***');
						return;
					}
				
					post_to_url('/owner/studylist/create/', {'name' : name, 'description' : description, 'folderId' : null}, 'post');
					
					modal.close();
				});
		
		/*
		$('#myModalLabel').text('Please enter a name and optional description for your study');
		$('#myModal .modal-body p').append('<h5>Name: </h5>&nbsp;&nbsp;<input type="text" id="studyName"/>' +
				'<h5>Description: </h5>&nbsp;&nbsp;<textarea id="studyDescription"/>');
		
		$('#myModal .btn-primary').click(function(e) {
			var name = $('#studyName').val().trim();
			var description = $('#studyDescription').val().trim();
			
			if (name == '') {
				if ($('#nameError').size() == 0) {
					$('#myModal .modal-body p').append('<p class="text-error" id="nameError">***Name cannot be empty.***</p>');
				}
				return;
			}
		
			post_to_url('/owner/studylist/create/', {'name' : name, 'description' : description, 'folderId' : null}, 'post');
			
		});*/
		
	});
	

	$('#newSurvey').live('click', function(e) {
		e.preventDefault();
		
		var modal = Dialog();
		

		
		// build list of studies to add to select
		var studiesStr = '';
		var str = $('#hiddenStudies').val();
		// this string is formatted: ID:Name;ID:Name;
		while (str != '' && str.indexOf(':') != -1 && str.indexOf(';') != -1) {
			var id = str.substr(0, str.indexOf(':'));
			str = str.substr(str.indexOf(':') + 1);
			var name = str.substr(0, str.indexOf(';'));
			str = str.substr(str.indexOf(';') + 1);
			studiesStr += '<option value=' + id + '>' + name + '</option>';
			//$('#differentStudy').append('<option value=' + id + '>' + name + '</option>');
		}
		
		
		var msgBody = '<form method="post" action="/owner/study/createsurvey">' + 
		'<p>Please enter a name and optional description for your survey</p>' +
		'<h5>Name: </h5>&nbsp;&nbsp;<input type="text" id="surveyName"/>' +
		'<h5>Description: </h5>&nbsp;&nbsp;<textarea id="surveyDescription"/><br/><hr/>' +
		'<p>Select a study to add survey to:</p>' +
		'<p><label class="radio"><input type="radio" name="study" value="currentStudy" checked>Current Study (...)</label></p>' +
		'<p><label class="radio"><input type="radio" name="study" value="selectStudy">A different study <select name="differentStudy">' + studiesStr + '</select></label></p>' +
		'<p><label class="radio"><input type="radio" name="study" value="noStudy">Not part of a study</label></p>' +
		'</form>';
		modal.open('New Survey',
				msgBody,
				function(e) {
					var name = $('#surveyName').val().trim();
					var description = $('#surveyDescription').val().trim();
					
					if (name == '') {
						modal.warn('***Name cannot be empty.***');
						return;
					}
					
					modal.close();
				});
		
	});
});



