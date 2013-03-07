<?php

// 先月までの訪問数等を表示

define('ga_email','your GoogleAnalytics mail address');
define('ga_password','your password');
define('api_key', 'your api key');
define('profile_id', 'your profile id');

require 'gapi.class.php';

// 昨日までの一週間を設定
// $startDate = date( 'Y-m-d',strtotime( "-8 day" ,time()));
// $endDate   = date( 'Y-m-d',strtotime( "-1 day" ,time()));

// 先月までの一か月を設定
// $startDate = date( 'Y-m-01',strtotime( "-1 month"));
// $endDate   = date( 'Y-m-t',strtotime( "-1 month"));

// 先月までの一年間を設定
$startDate = date( 'Y-m-01',strtotime( "-1 year"));
$endDate   = date( 'Y-m-t',strtotime( "-1 month"));



// インスタンスを生成
$ga = new gapi(ga_email, ga_password, api_key);

// 訪問数のデータを取得
$response =  $ga->requestReportData(
  profile_id,    // プロファイルID
  array ('year', 'month'),    // ディメンション（横軸）
  array ('visits', 'visitors', 'pageviews', 'pageviewsPerVisit'),    // メトリクス（欲しい項目）
  array ('year', 'month'),    // 並び替え条件
  'keyword!=(not set)',    // フィルタ（例は検索ワードのないアクセスの場合）
  $startDate,    // 対象期間の始め
  $endDate,    // 対象期間の終わり
  1,    // 取得開始位置
  50    // 取得件数
);

?>
<h1><?php echo $response->profileInfo->profileName; ?>のアクセス解析</h1>
<?php echo $startDate; ?>から<?php echo $endDate; ?>までの期間のデータ<br>

<table>
  <tr>
    <th>年/月</th>
    <th>訪問数</th>
    <th>ユーザー数</th>
    <th>PV数</th>
    <th>訪問別PV数</th>
  </tr>
<?php foreach($response->rows as $row){ ?>
  <tr>
    <th><?php echo $row[0].'/'.$row[1]; ?></th>
    <th><?php echo $row[2]; ?></th>
    <th><?php echo $row[3]; ?></th>
    <th><?php echo $row[4]; ?></th>
    <th><?php echo round($row[5], 2); ?></th>
  </tr>
<?php } ?>
</table>
