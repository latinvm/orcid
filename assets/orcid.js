jQuery(document).ready(function() {
	var orcid = Orcid();
	//check ORCID number on form submission
	jQuery('.comment-form').submit(orcid.validateForm);
	//bind event handlers to the textbox itself
	//should work for both comments form and User form in admin panel
	jQuery('#orcid').blur(orcid.validateId);
})


function Orcid() {
	var $ = jQuery;
	var that = Object();
	
	//validate form
	that.validateForm = function(e) {
		return that.validateId();
	}
	
	//validate the ORCID number entered by the user.
	//First by the sturcture of the input and then by checking in with ORCID API
	that.validateId = function(e) {
		var inputString = $('#orcid').val();
		//this Regex could be improved to check whole string.
		//Right now just checks for illegal characters
		var orcidRegex = new RegExp("[^A-Za-z0-9\-\:\/\.]"); 
		var illegalChar = orcidRegex.test(inputString); 
		if (!illegalChar) {
			//valid so far. Now check with ORCID API
			that.showIcon( $('#orcid-waiting') );  
			that.checkOrcidAPI(inputString);
			return true;
		} else {
			//not a valid string. Show user the fail icon.
			that.showIcon( $('#orcid-failure') );
			$('#orcid-instructions').html('e.g. 0000-0002-7299-680X');
			return false;
		}
	}
	
	//method for contacting the ORCID API. On success, shows the success icon and
	//gives the user their credit name (so they can confirm).
	//On error (number is not valid or ORCID cannot be contacted) shows the fail icon.
	that.checkOrcidAPI = function(number) {
		$.ajax({
			method: 'get',
			url: 'http://pub.orcid.org/' + number,
			dataType: 'xml',
			success: function(response) {
				that.showIcon( $('#orcid-success') );
				//find users name from XML response
				var name = $(response).find('credit-name')[0].innerHTML;
				$('#orcid-instructions').html('You are: ' + name);
			},
			error: function() {
				that.showIcon( $('#orcid-failure') );
				$('#orcid-instructions').html('Your ORCID profile could not be found');
			}
		})
	}
	
	//Shows an icon (passed in as a jQuery object.) Hides all the other icons.
	that.showIcon = function(icon) {
		$('.orcid-icon').hide();
		$(icon).show();
	}
	return that;
}
