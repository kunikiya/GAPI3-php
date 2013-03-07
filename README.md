#Google Analytics PHP interface for GAPI version3.0;

https://github.com/kunikiya/GAPI3-php

##Features:

* Supports CURL and fopen HTTP access methods, with autodetection
* This code Forked by https://code.google.com/p/gapi-google-analytics-php-interface/

##Use sample.
`
define('ga_email','your GoogleAnalytics mail address');
define('ga_password','your password');
define('api_key', 'your api key');

require 'gapi.class.php';

$ga = new gapi(ga_email, ga_password, api_key);
`

more code...

