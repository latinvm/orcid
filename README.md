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
- add a plugin settings page
- use OAuth to allow social login via orcid.org
- add ORCID to admin/update comment page