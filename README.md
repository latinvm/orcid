ORCID WordPress Plugin
=====

This is a WordPress plugin that displays ORCIDs in comments and posts.

TODO:

- implement regular expression JavaScript to validate ORCIDs
	var orcidRegex = new RegExp("(\\d{4}-){3,}\\d{3}[\\dX]");
	var regexResult = orcidRegex.exec(inputString);
	if(regexResult){
		// Got an orcid
	}
- add a settings page?
- use OAuth to allow social login via orcid.org
- make sure logged in users comments display orcid with comments.
- add delete all ORCID metadata when uninstalling plugin
