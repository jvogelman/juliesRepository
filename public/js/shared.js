

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
		
		$('#myModalLabel').text('Please enter a name and optional description for your study');
		var size = $('#myModal .modal-body p').size();
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
			
		});
		
	});
});