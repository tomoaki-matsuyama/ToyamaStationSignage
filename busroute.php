<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title></title>
</head>
<body>
<?php
//デバッグ用dBug読み込み
setlocale(LC_ALL,'ja_JP.UTF-8');
include "include/dBug.php";

/*------------------------------
  バス停オブジェクトの生成
------------------------------*/
// csvを読み込み配列化
$csv  = array();
$file = 'busstop.csv';
$fp   = fopen($file, "r");
while (($data = fgetcsv($fp, 0, ",")) !== FALSE) {
	$csv[] = $data;
}
fclose($fp);

// XML読み込み
$xml = json_decode(json_encode(simplexml_load_file("busstop.xml")));

$busStopArrayTmp = array();
for($i = 0; $i < count($csv); $i++) {
	$busStopArrayTmp[$i]['busStopName']    = $csv[$i][0];
	$busStopArrayTmp[$i]['busStopLocate']  = $csv[$i][1] . " " . $csv[$i][2];
	$busStopArrayTmp[$i]['busStopAddress'] = $csv[$i][3];

	$cnt = (count($xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData)-2)/2;

	// 交通事業者配列 start : 2, end : 2+$cnt
	$busStopArrayTmp[$i]['busStopComp'] = array();
	for ($j = 2; $j < 2+$cnt; $j++) {
		array_push($busStopArrayTmp[$i]['busStopComp'], $xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData[$j]);
	}
	// バス路線配列 start : 2+$cnt+1, end : 2+$cnt+$cnt+1
	$busStopArrayTmp[$i]['busStopRoutes'] = array();
	for ($j = $cnt+2; $j < 2*$cnt+2; $j++) {
		array_push($busStopArrayTmp[$i]['busStopRoutes'], $xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData[$j]);
	}
}

// 富山市以外を削除する
$busStopArray = array();
for($i = 0; $i < count($busStopArrayTmp); $i++) {
	if (strstr($busStopArrayTmp[$i]['busStopAddress'], '富山市')) {
		$busStopArray[$i]['busStopName']    = $busStopArrayTmp[$i]['busStopName'];
		$busStopArray[$i]['busStopLocate']  = $busStopArrayTmp[$i]['busStopLocate'];
		$busStopArray[$i]['busStopAddress'] = $busStopArrayTmp[$i]['busStopAddress'];

		if ( count($busStopArrayTmp[$i]['busStopComp']) > 1) {
			$busStopArray[$i]['busStopComp'] = implode(",", $busStopArrayTmp[$i]['busStopComp']);
		} else {
			$busStopArray[$i]['busStopComp'] = $busStopArrayTmp[$i]['busStopComp'][0];
		}
		if ( count($busStopArrayTmp[$i]['busStopRoutes']) > 1) {
			$busStopArray[$i]['busStopRoutes'] = implode(",", $busStopArrayTmp[$i]['busStopRoutes']);
		} else {
			$busStopArray[$i]['busStopRoutes'] = $busStopArrayTmp[$i]['busStopRoutes'][0];
		}
	}
}

/*------------------------------
  バス路線オブジェクトの生成
------------------------------*/
$busKbn = array('', '民間バス', '公営バス', 'コミュニティバス', 'デマンドバス', 'その他');
$xml = json_decode(json_encode(simplexml_load_file("busroute.xml")));
$tmp = array();
for ($i = 0; $i < count($xml->Document->Folder->Placemark); $i++) {
	$tmp[$i]['busRouteName'] = $xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData[2];
	$tmp[$i]['busRouteComp'] = $xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData[1];
	$tmp[$i]['busRouteKbn']  = $busKbn[$xml->Document->Folder->Placemark[$i]->ExtendedData->SchemaData->SimpleData[0]];
	$tmp[$i]['busRouteLine'] = array();
	// LineStringがMultiGeometryでグループ化されているか？
	if (isset($xml->Document->Folder->Placemark[$i]->MultiGeometry)) {
		$tmp[$i]['busRouteLine'] = $xml->Document->Folder->Placemark[$i]->MultiGeometry->LineString;
	} else {
		$tmp[$i]['busRouteLine'] = $xml->Document->Folder->Placemark[$i]->LineString->coordinates;
	}
}

/*------------------------------
　バス路線とバス停の結合

バス路線でループ
　└バス停でループ
　　└バス路線名がバス停にあるかを検索し、
　　　あれば路線にバス停を追加する
-------------------------------*/
for ($i = 0; $i < count($tmp); $i++) {	// バス路線でループ

	$busRouteName = $tmp[$i]['busRouteName'];	// バス路線名

	$tmp[$i]['busStop'] = array();
	for ($j = 0; $j < count($busStopArray); $j++) {		// バス停でループ

		if (isset($busStopArray[$j])){
			$busRouteNameList = $busStopArray[$j]['busStopRoutes'];		// バス停に記録されたそのバス停が属する経路文字（,区切りテキスト）

			$busStopSpec = array();
			if( strstr($busRouteNameList, $busRouteName) ) {
				$busStopSpec['busStopName']    = $busStopArray[$j]['busStopName'];
				$busStopSpec['busStopLocate']  = $busStopArray[$j]['busStopLocate'];
				$busStopSpec['busStopAddress'] = $busStopArray[$j]['busStopAddress'];

				array_push($tmp[$i]['busStop'], $busStopSpec);

			}
		}
	}

}

/*------------------------------
　バス路線配列の正規化
バス路線配列から、バス停のないものを
削除する。
&& 交通事業者が地鉄||富山市でフィルタ
-------------------------------*/
$busRouteArray = array();
for ($i = 0; $i < count($tmp); $i++) {
	if (count($tmp[$i]['busStop']) != 0 && ( $tmp[$i]['busRouteComp'] == '富山地方鉄道（株）' || $tmp[$i]['busRouteComp'] == '富山市') ) {
		array_push($busRouteArray,$tmp[$i]);
	}
}

// 路線緯度経度の正規化
for ($i = 0; $i < count($busRouteArray); $i++) {
echo(gettype($busRouteArray[$i]['busRouteLine']) . "<br />");

	if (gettype($busRouteArray[$i]['busRouteLine']) == "string") {
		$aTmp = array();
		$aTmp = explode(" ", $busRouteArray[$i]['busRouteLine']);
		for ($j = 0; $j < count($aTmp); $j++) {
			$aTmp[$j] = chgLonLng($aTmp[$j]);
		}
	} elseif (gettype($busRouteArray[$i]['busRouteLine']) == "array") {
		for ($j = 0; count($busRouteArray[$i]['busRouteLine']); $j++) {
			new dBug($busRouteArray[$i]['busRouteLine'][$j]);
		}
	}

}

/*------------------------------
念のためjson形式でファイルを
保存しておく。
-------------------------------*/
$json = json_encode($busRouteArray);

$fn = "busRouteArray.json";
$fp = fopen("./data/" . $fn, "w");
flock($fp, LOCK_EX);
fputs($fp, $json);
flock($fp, LOCK_UN);
fclose($fp);


/*------------------------------
路線ごとにKML形式で書き出す
-------------------------------*/

$kml_header = <<<EOM
<?xml version='1.0' encoding='UTF-8'?>
<kml xmlns='http://www.opengis.net/kml/2.2'>
	<Document>
EOM;

$kml_footer = <<<EOM
	<Style id='icon-503-DB4436'>
		<IconStyle>
			<color>ff3644DB</color>
			<scale>1.1</scale>
			<Icon>
				<href>http://www.gstatic.com/mapspro/images/stock/503-wht-blank_maps.png</href>
			</Icon>
		</IconStyle>
	</Style>
	<Style id='line-DB4436-4'>
		<LineStyle>
			<color>ff3644DB</color>
			<width>4</width>
		</LineStyle>
	</Style>
</Document>
</kml>
EOM;


$menu_out = "<ol>"."\n";

for ($i = 0; $i < count($busRouteArray); $i++) {

	$kml_out  = $kml_header;
	$kml_out .= "<name>" . $busRouteArray[$i]['busRouteName'] . "</name>";

	// バス停マーカーの付与
	$kml_out .= "<Placemark>"."\n";
	$kml_out .= "<styleUrl>#line-DB4436-4</styleUrl>"."\n";
	$kml_out .= "<name>" . $busRouteArray[$i]['busRouteName'] . "</name>"."\n";
	$kml_out .= "<ExtendedData>"."\n";
	$kml_out .= "<Data name='string'>"."\n";
	$kml_out .= "<displayName>事業者名</displayName>"."\n";
	$kml_out .= "<value>" . $busRouteArray[$i]['busRouteComp'] . "</value>"."\n";
	$kml_out .= "</Data>"."\n";
	$kml_out .= "</ExtendedData>"."\n";
	$kml_out .= "<description>(説明文)</description>"."\n";
	if (isset($busRouteArray[$i]['busRouteLine'][0]->coordinates)) {
		if (count($busRouteArray[$i]['busRouteLine']) == 1) {	//経路ラインが1の場合
			$kml_out .= "<LineString>"."\n";
			$kml_out .= "<tessellate>0</tessellate>"."\n";
			$kml_out .= "<coordinates>" . $busRouteArray[$i]['busRouteLine'][0]->coordinates . "</coordinates>"."\n";
			$kml_out .= "</LineString>"."\n";
		} else {	//経路ラインが複数の場合
			$kml_out .= "<MultiGeometry>"."\n";
			for ($k = 0; $k < count($busRouteArray[$i]['busRouteLine']); $k++) {
				$kml_out .= "<LineString>"."\n";
				$kml_out .= "<tessellate>0</tessellate>"."\n";
				$kml_out .= "<coordinates>" . $busRouteArray[$i]['busRouteLine'][$k]->coordinates . "</coordinates>"."\n";
				$kml_out .= "</LineString>"."\n";
			}
			$kml_out .= "</MultiGeometry>"."\n";
		}
	} else {
		continue;
	}
	$kml_out .= "</Placemark>"."\n";
	$kml_out .= $kml_footer;

	$fn = $i . ".kml";
	$fp = fopen("./kmls/" . $fn, "w");
    flock($fp, LOCK_EX);
	fputs($fp, $kml_out);
    flock($fp, LOCK_UN);
    fclose($fp);

	$menu_out .= "<li><a href=\"#\" onclick=\"chgmap('" . $i . ".kml')\">" . $busRouteArray[$i]['busRouteName'] . " (" . $busRouteArray[$i]['busRouteComp'] . ")</a></li>"."\n";


}
$menu_out .= "</ol>"."\n";
$fn = "menu.txt";
$fp = fopen("menu.txt", "w");
flock($fp, LOCK_EX);
fputs($fp, $menu_out);
flock($fp, LOCK_UN);
fclose($fp);

function chgLonLng($str) {
	$tmp = explode(",", $str);
	return $tmp[1] . "," . $tmp[0] . "," . $tmp[2];
}

?>
</body>
</html>
