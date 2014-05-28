jQuery(document).ready(function() {
	var orcid = Orcid();
	jQuery('.comment-form').submit(orcid.validateForm);
	jQuery('#orcid').blur(orcid.validateId);
})


function Orcid() {
	var $ = jQuery;
	var that = Object();
	that.validateForm = function(e) {
		return that.validateId();
	}
	that.validateId = function(e) {
		var inputString = $('#orcid').val();
		var id = $('#orcid').val();
		var orcidRegex = new RegExp("[^A-Za-z0-9\-\:\/\.]"); 
		var illegalChar = orcidRegex.test(inputString); 
		if (!illegalChar) {
			$('#orcid-failure').hide();
			$('#orcid-success').show();  
			return true;
		} else {
			$('#orcid-failure').show();
			$('#orcid-success').hide();
			return false;
		}
	}
	return that;
}
