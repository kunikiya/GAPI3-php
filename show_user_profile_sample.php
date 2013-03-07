<?php

// ユーザーに属するプロファイルデータを表示

define('ga_email','your GoogleAnalytics mail address');
define('ga_password','your password');
define('api_key', 'your api key');

require 'gapi.class.php';


$ga = new gapi(ga_email, ga_password, api_key);
$ProfileData = $ga->requestAnalyticsData();

print_r($ProfileData);