<?php
/**
 * GAPI3-php
 *
 * https://github.com/kunikiya/GAPI3-php
 *
 * @author kunikiya <y-harada@kunikiya.jp>
 * @version 1.0
 *
 */

class gapi
{
  const http_interface = 'auto';
  const client_login_url = 'https://www.google.com/accounts/ClientLogin';
  const account_data_url = 'https://www.googleapis.com/analytics/v3/management/accounts';
  const report_data_url = 'https://www.googleapis.com/analytics/v3/data/ga';
  const interface_name = 'GAPI-3.0';
  const dev_mode = false;

  private $auth_token = null;
  private $report_aggregate_metrics = array();
  private $report_root_parameters = array();

  private $language = 'en';
  private $api_key;


  /**
   * Constructor function for all new gapi instances
   *
   * Set up authenticate with Google and get auth_token
   *
   * @param String $email
   * @param String $password
   * @param String $api_key
   * @param String $language
   * @param String $token
   * @return gapi
   */
  public function __construct($email, $password, $api_key = null, $language = null, $token = null)
  {
    if($token)
    {
      $this->auth_token = $token;
    }
    else
    {
      $this->authenticateUser($email,$password);
    }

    if($api_key)
    {
      $this->api_key = $api_key;
    }
    if($language)
    {
      $this->language = $language;
    }
  }

  /**
   * Return the auth token, used for storing the auth token in the user session
   *
   * @return String
   */
  public function getAuthToken()
  {
    print_r($this->auth_token);
    return $this->auth_token;
  }

  /** 追記
   * アカウント名付きでAnalyticsのプロファイルを取得
   *
   * Enter description here ...
   * @param unknown_type $start_index
   * @param unknown_type $max_results
   * @throws Exception
   */
  public function requestAnalyticsData($start_index=1, $max_results=50)
  {
    // アカウントを取得
    $responseAccountData = $this->httpRequest(gapi::account_data_url, array('start-index'=>$start_index,'max-results'=>$max_results), null, $this->generateAuthHeader());
    if ( substr( $responseAccountData['code'], 0, 1 ) == '2' )
    {
      $jsonAccountData = $responseAccountData['body'];
      $AccountData = json_decode($jsonAccountData);
    }
    else
    {
      if('ja' === $this->language){
        throw new Exception('エラー: GAPIへのアカウンのデータのリクエストが失敗しました: "' . strip_tags($responseAccountData['body']) . '"');
      }else{
        throw new Exception('GAPI: Failed to request account data. Error: "' . strip_tags($responseAccountData['body']) . '"');
      }
    }

    // アカウントに紐づくウェブプロパティ（プロファイルの親）を取得
    for ($i=0; $i<count($AccountData->items); $i++)
    {
      // アカウントの抽出
      @$returnData[$i]->AccountId = $AccountData->items[$i]->id;
      $returnData[$i]->AccountName = $AccountData->items[$i]->name;

      // ウェブプロパティの取得URL
      $uriWebproperties = $AccountData->items[$i]->childLink->href;
      $responseWebproperties = $this->httpRequest($uriWebproperties, array('start-index'=>$start_index,'max-results'=>$max_results), null, $this->generateAuthHeader());
      if ( substr( $responseWebproperties['code'], 0, 1 ) == '2' )
      {
        $jsonWebproperties = $responseWebproperties['body'];
        $webproperties = json_decode($jsonWebproperties);
      }
      else
      {
        throw new Exception('GAPI: Failed to request account data. Error: "' . strip_tags($responseWebproperties['body']) . '"');
      }

      // アカウントに紐づくウェブプロパティ（プロファイルの親）に紐づくプロファイルを取得
      for ($j=0; $j<count($webproperties->items); $j++)
      {
        // ウェブプロパティの抽出
        @$returnData[$i]->webProperty[$j]->Id = $webproperties->items[$j]->id;

        // プロファイルの取得URL
        $uriProfileData = $webproperties->items[$j]->childLink->href;
        $responseProfileData = $this->httpRequest($uriProfileData, array('start-index'=>$start_index,'max-results'=>$max_results), null, $this->generateAuthHeader());

        if ( substr( $responseProfileData['code'], 0, 1 ) == '2' )
        {
          $jsonProfileData = $responseProfileData['body'];
          $profileData = json_decode($jsonProfileData);
        }
        else
        {
          throw new Exception('GAPI: Failed to request account data. Error: "' . strip_tags($responseProfileData['body']) . '"');
        }

        for ($k=0; $k<count($profileData->items); $k++)
        {
          // プロファイルの抽出
          @$returnData[$i]->webProperty[$j]->profile[$k]->id = $profileData->items[$k]->id;
          $returnData[$i]->webProperty[$j]->profile[$k]->name = $profileData->items[$k]->name;
        }
      }
    }

    return $returnData;
  }

  /**
   * Request report data from Google Analytics
   *
   * $report_id is the Google report ID for the selected account
   *
   * $parameters should be in key => value format
   *
   * @param String $report_id
   * @param Array $dimensions Google Analytics dimensions e.g. array('browser')
   * @param Array $metrics Google Analytics metrics e.g. array('pageviews')
   * @param Array $sort_metric OPTIONAL: Dimension or dimensions to sort by e.g.('-visits')
   * @param String $filter OPTIONAL: Filter logic for filtering results
   * @param String $start_date OPTIONAL: Start of reporting period
   * @param String $end_date OPTIONAL: End of reporting period
   * @param Int $start_index OPTIONAL: Start index of results
   * @param Int $max_results OPTIONAL: Max results returned
   *
   * OK
   */
  public function requestReportData($report_id, $dimensions, $metrics, $sort_metric=null, $filter=null, $start_date=null, $end_date=null, $start_index=1, $max_results=30)
  {
    $parameters = array('ids'=> 'ga:'.$report_id);

    if(is_array($dimensions))
    {
      $dimensions_string = '';
      foreach($dimensions as $dimesion)
      {
        $dimensions_string .= ',ga:' . $dimesion;
      }
      $parameters['dimensions'] = substr($dimensions_string,1);
    }
    else
    {
      $parameters['dimensions'] = 'ga:'.$dimensions;
    }

    if(is_array($metrics))
    {
      $metrics_string = '';
      foreach($metrics as $metric)
      {
        $metrics_string .= ',ga:' . $metric;
      }
      $parameters['metrics'] = substr($metrics_string,1);
    }
    else
    {
      $parameters['metrics'] = 'ga:'.$metrics;
    }

    if($sort_metric==null&&isset($parameters['metrics']))
    {
      $parameters['sort'] = $parameters['metrics'];
    }
    elseif(is_array($sort_metric))
    {
      $sort_metric_string = '';

      foreach($sort_metric as $sort_metric_value)
      {
        //Reverse sort - Thanks Nick Sullivan
        if (substr($sort_metric_value, 0, 1) == "-")
        {
          $sort_metric_string .= ',-ga:' . substr($sort_metric_value, 1); // Descending
        }
        else
        {
          $sort_metric_string .= ',ga:' . $sort_metric_value; // Ascending
        }
      }

      $parameters['sort'] = substr($sort_metric_string, 1);
    }
    else
    {
      if (substr($sort_metric, 0, 1) == "-")
      {
        $parameters['sort'] = '-ga:' . substr($sort_metric, 1);
      }
      else
      {
        $parameters['sort'] = 'ga:' . $sort_metric;
      }
    }

    if($filter!=null)
    {
      $filter = $this->processFilter($filter);
      if($filter!==false)
      {
        $parameters['filters'] = $filter;
      }
    }

    if($start_date==null)
    {
      $start_date=date('Y-m-d',strtotime('1 month ago'));
    }

    $parameters['start-date'] = $start_date;

    if($end_date==null)
    {
      $end_date=date('Y-m-d');
    }

    $parameters['end-date'] = $end_date;


    $parameters['start-index'] = $start_index;
    $parameters['max-results'] = $max_results;

    $parameters['prettyprint'] = gapi::dev_mode ? 'true' : 'false';
    $parameters['key'] = $this->api_key;

    $response = $this->httpRequest(gapi::report_data_url, $parameters, null, $this->generateAuthHeader());

    //HTTP 2xx
    if(substr($response['code'],0,1) == '2')
    {
      return json_decode($response['body']);
    }
    else
    {
      throw new Exception('GAPI: Failed to request report data. Error: "' . strip_tags($response['body']) . '"');
    }
  }

  /**
   * Process filter string, clean parameters and convert to Google Analytics
   * compatible format
   *
   * @param String $filter
   * @return String Compatible filter string
   */
  protected function processFilter($filter)
  {
    $valid_operators = '(!~|=~|==|!=|>|<|>=|<=|=@|!@)';

    $filter = preg_replace('/\s\s+/',' ',trim($filter)); //Clean duplicate whitespace
    $filter = str_replace(array(',',';'),array('\,','\;'),$filter); //Escape Google Analytics reserved characters
    $filter = preg_replace('/(&&\s*|\|\|\s*|^)([a-z]+)(\s*' . $valid_operators . ')/i','$1ga:$2$3',$filter); //Prefix ga: to metrics and dimensions
    $filter = preg_replace('/[\'\"]/i','',$filter); //Clear invalid quote characters
    $filter = preg_replace(array('/\s*&&\s*/','/\s*\|\|\s*/','/\s*' . $valid_operators . '\s*/'),array(';',',','$1'),$filter); //Clean up operators

    if(strlen($filter)>0)
    {
      return urlencode($filter);
    }
    else
    {
      return false;
    }
  }


  /**
   * Authenticate Google Account with Google
   *
   * @param String $email
   * @param String $password
   */
  protected function authenticateUser($email, $password)
  {
    $post_variables = array(
        'accountType' => 'GOOGLE',
        'Email' => $email,
        'Passwd' => $password,
        'source' => gapi::interface_name,
        'service' => 'analytics'
    );

    $response = $this->httpRequest(gapi::client_login_url,null,$post_variables);

    //Convert newline delimited variables into url format then import to array
    parse_str(str_replace(array("\n","\r\n"),'&',$response['body']),$auth_token);

    if(substr($response['code'],0,1) != '2' || !is_array($auth_token) || empty($auth_token['Auth']))
    {
      throw new Exception('GAPI: Failed to authenticate user. Error: "' . strip_tags($response['body']) . '"');
    }

    $this->auth_token = $auth_token['Auth'];
  }

  /**
   * Generate authentication token header for all requests
   *
   * @return Array
   */
  protected function generateAuthHeader()
  {
    return array('Authorization: GoogleLogin auth=' . $this->auth_token);
  }

  /**
   * Perform http request
   *
   *
   * @param Array $get_variables
   * @param Array $post_variables
   * @param Array $headers
   *
   * OK
   */
  protected function httpRequest($url, $get_variables=null, $post_variables=null, $headers=null)
  {
    $interface = gapi::http_interface;

    if(gapi::http_interface =='auto')
    {
      if(function_exists('curl_exec'))
      {
        $interface = 'curl';
      }
      else
      {
        $interface = 'fopen';
      }
    }

    if($interface == 'curl')
    {
      return $this->curlRequest($url, $get_variables, $post_variables, $headers);
    }
    elseif($interface == 'fopen')
    {
      return $this->fopenRequest($url, $get_variables, $post_variables, $headers);
    }
    else
    {
      throw new Exception('Invalid http interface defined. No such interface "' . gapi::http_interface . '"');
    }
  }

  /**
   * HTTP request using PHP CURL functions
   * Requires curl library installed and configured for PHP
   *
   * @param Array $get_variables
   * @param Array $post_variables
   * @param Array $headers
   *
   * OK
   */
  private function curlRequest($url, $get_variables=null, $post_variables=null, $headers=null)
  {
    $ch = curl_init();

    if(is_array($get_variables))
    {
      $get_variables = '?' . str_replace('&amp;','&',urldecode(http_build_query($get_variables)));
    }
    else
    {
      $get_variables = null;
    }

    curl_setopt($ch, CURLOPT_URL, $url . $get_variables);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //CURL doesn't like google's cert

    if(is_array($post_variables))
    {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post_variables);
    }

    if(is_array($headers))
    {
      curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch,CURLINFO_HTTP_CODE);

    curl_close($ch);

    return array('body'=>$response,'code'=>$code);
  }

  /**
   * HTTP request using native PHP fopen function
   * Requires PHP openSSL
   *
   * @param Array $get_variables
   * @param Array $post_variables
   * @param Array $headers
   */
  private function fopenRequest($url, $get_variables=null, $post_variables=null, $headers=null)
  {
    $http_options = array('method'=>'GET','timeout'=>3);

    if(is_array($headers))
    {
      $headers = implode("\r\n",$headers) . "\r\n";
    }
    else
    {
      $headers = '';
    }

    if(is_array($get_variables))
    {
      $get_variables = '?' . str_replace('&amp;','&',urldecode(http_build_query($get_variables)));
    }
    else
    {
      $get_variables = null;
    }

    if(is_array($post_variables))
    {
      $post_variables = str_replace('&amp;','&',urldecode(http_build_query($post_variables)));
      $http_options['method'] = 'POST';
      $headers = "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($post_variables) . "\r\n" . $headers;
      $http_options['header'] = $headers;
      $http_options['content'] = $post_variables;
    }
    else
    {
      $post_variables = '';
      $http_options['header'] = $headers;
    }

    $context = stream_context_create(array('http'=>$http_options));
    $response = @file_get_contents($url . $get_variables, null, $context);

    return array('body'=>$response!==false?$response:'Request failed, fopen provides no further information','code'=>$response!==false?'200':'400');
  }

  /**
   * Case insensitive array_key_exists function, also returns
   * matching key.
   *
   * @param String $key
   * @param Array $search
   * @return String Matching array key
   */
  public static function array_key_exists_nc($key, $search)
  {
    if (array_key_exists($key, $search))
    {
      return $key;
    }
    if (!(is_string($key) && is_array($search)))
    {
      return false;
    }
    $key = strtolower($key);
    foreach ($search as $k => $v)
    {
      if (strtolower($k) == $key)
      {
        return $k;
      }
    }
    return false;
  }

  /**
   * Get an array of the metrics and the matchning
   * aggregate values for the current result
   *
   * @return Array
   */
  public function getMetrics()
  {
    return $this->report_aggregate_metrics;
  }

  /**
   * Call method to find a matching root parameter or
   * aggregate metric to return
   *
   * @param $name String name of function called
   * @return String
   * @throws Exception if not a valid parameter or aggregate
   * metric, or not a 'get' function
   */
  public function __call($name,$parameters)
  {
    if(!preg_match('/^get/',$name))
    {
    throw new Exception('No such function "' . $name . '"');
    }

    $name = preg_replace('/^get/','',$name);

    $parameter_key = gapi::array_key_exists_nc($name,$this->report_root_parameters);

    if($parameter_key)
    {
    return $this->report_root_parameters[$parameter_key];
    }

    $aggregate_metric_key = gapi::array_key_exists_nc($name,$this->report_aggregate_metrics);

    if($aggregate_metric_key)
    {
    return $this->report_aggregate_metrics[$aggregate_metric_key];
    }

    throw new Exception('No valid root parameter or aggregate metric called "' . $name . '"');
  }
}

/**
 * Class gapiAccountEntry
 *
 * Storage for individual gapi account entries
 *
 */
class gapiAccountEntry
{
  private $properties = array();

  public function __construct($properties)
  {
    $this->properties = $properties;
  }

  /**
   * toString function to return the name of the account
   *
   * @return String
   */
  public function __toString()
  {
    if(isset($this->properties['title']))
    {
      return $this->properties['title'];
    }
    else
    {
      return;
    }
  }

  /**
   * Get an associative array of the properties
   * and the matching values for the current result
   *
   * @return Array
   */
  public function getProperties()
  {
    return $this->properties;
  }

  /**
   * Call method to find a matching parameter to return
   *
   * @param $name String name of function called
   * @return String
   * @throws Exception if not a valid parameter, or not a 'get' function
   */
  public function __call($name,$parameters)
  {
    if(!preg_match('/^get/',$name))
    {
      throw new Exception('No such function "' . $name . '"');
    }

    $name = preg_replace('/^get/','',$name);

    $property_key = gapi::array_key_exists_nc($name,$this->properties);

    if($property_key)
    {
      return $this->properties[$property_key];
    }

    throw new Exception('No valid property called "' . $name . '"');
  }
}

/**
 * Class gapiReportEntry
 *
 * Storage for individual gapi report entries
 *
 */
class gapiReportEntry
{
  private $metrics = array();
  private $dimensions = array();

  public function __construct($metrics,$dimesions)
  {
    $this->metrics = $metrics;
    $this->dimensions = $dimesions;
  }

  /**
   * toString function to return the name of the result
   * this is a concatented string of the dimesions chosen
   *
   * For example:
   * 'Firefox 3.0.10' from browser and browserVersion
   *
   * @return String
   */
  public function __toString()
  {
    if(is_array($this->dimensions))
    {
      return implode(' ',$this->dimensions);
    }
    else
    {
      return '';
    }
  }

  /**
   * Get an associative array of the dimesions
   * and the matching values for the current result
   *
   * @return Array
   */
  public function getDimesions()
  {
    return $this->dimensions;
  }

  /**
   * Get an array of the metrics and the matchning
   * values for the current result
   *
   * @return Array
   */
  public function getMetrics()
  {
    return $this->metrics;
  }

  /**
   * Call method to find a matching metric or dimension to return
   *
   * @param $name String name of function called
   * @return String
   * @throws Exception if not a valid metric or dimensions, or not a 'get' function
   */
  public function __call($name,$parameters)
  {
    if(!preg_match('/^get/',$name))
    {
      throw new Exception('No such function "' . $name . '"');
    }

    $name = preg_replace('/^get/','',$name);

    $metric_key = gapi::array_key_exists_nc($name,$this->metrics);

    if($metric_key)
    {
      return $this->metrics[$metric_key];
    }

    $dimension_key = gapi::array_key_exists_nc($name,$this->dimensions);

    if($dimension_key)
    {
      return $this->dimensions[$dimension_key];
    }

    throw new Exception('No valid metric or dimesion called "' . $name . '"');
  }
}