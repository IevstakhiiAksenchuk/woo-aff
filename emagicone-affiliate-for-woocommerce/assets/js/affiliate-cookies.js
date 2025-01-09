function setCookie(name, value, days) {
	var expires = "";
	if (days) {
		var date = new Date();
		date.setTime( date.getTime() + (days * 24 * 60 * 60 * 1000) );
		expires = "; expires=" + date.toUTCString();
	}
	document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function getParameterByName(name, url) {
	if ( ! url) {
		url = window.location.href;
	}
	name        = name.replace( /[\[\]]/g, "\\$&" );
	var regex   = new RegExp( "[?&]" + name + "(=([^&#]*)|&|#|$)" ),
		results = regex.exec( url );
	if ( ! results) {
		return null;
	}
	if ( ! results[2]) {
		return '';
	}
	return decodeURIComponent( results[2].replace( /\+/g, " " ) );
}

var utmContent = getParameterByName( 'aw_affiliate' );
if (utmContent) {
	setCookie( 'affiliate_aw_affiliate', utmContent, 30 ); // Set cookie for 30 days
}
