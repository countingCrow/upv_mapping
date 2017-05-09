<?php
// countingCrow; MIT license;

// our parser created json file
$file_path_json = 'result.json';
// --- end config ---

$raw_data = file_get_contents($file_path_json);
$data = json_decode($raw_data, true);
$crt_layer = '';
$item_counter = 0;
$file_counter = 0;
$xml_ending = init_xml_ending();

foreach ($data as $key => $portal_data) {
  $item_counter++;
  $latlng = $portal_data['lat'].','.$portal_data['lng'];
  $url = "http://www.ingress.com/intel?ll=$latlng&amp;z=17&amp;pll=$latlng";
  $enemy = implode(', ', $portal_data['e']);
  $is_upc = $portal_data['upc'];
  $is_upc_text = $is_upc ? 'true' : 'false';
  $color = $is_upc ? 'icon-1574-097138-nodesc' : 'icon-1731-F57C00-nodesc';
  // <![CDATA[]]> used at portal name was a key point, this could avoid us making mistake on escaping them
  $crt_marker = "<Placemark><name><![CDATA[{$portal_data['n']}]]></name>"
      ."<description><![CDATA[$url]]></description><styleUrl>#$color</styleUrl><ExtendedData>"
      ."<Data name='gx_media_links'><value>{$portal_data['i']}</value></Data>"
      ."<Data name='is_upc'><value>$is_upc_text</value></Data><Data name='enemy'><value>$enemy</value></Data></ExtendedData>"
      // the new line here is key point!! google mymap seem to have difficult at parsing single line kml..
      ."<Point><coordinates>{$portal_data['lng']},{$portal_data['lat']}</coordinates></Point></Placemark>\n"
      ;
  $crt_layer .= $crt_marker;
  // google mymap can only import 2000 markers by single file
  if ($item_counter == 2000) {
    write_kml_file();
    $crt_layer = '';
    $item_counter = 0;
    $file_counter++;
  }
}
if (!empty($crt_layer)) {
  write_kml_file($item_counter);
}

function write_kml_file($left_count = 0) {
  global $file_counter, $crt_layer, $xml_ending;
  
  $min = $file_counter * 2000 + 1;
  $max = $left_count ? $left_count : ($file_counter + 1) * 2000;
  $layer_name = "upc upv $file_counter ($min~$max)";
  file_put_contents("result_$file_counter.kml",
    "<?xml version='1.0' encoding='UTF-8'?>"
    ."<kml xmlns='http://www.opengis.net/kml/2.2'>"
    ."<Document><name>$layer_name</name>"
    ."<description><![CDATA[]]></description>"
    ."<Folder><name>$layer_name</name>$crt_layer</Folder>$xml_ending"
  );
}
// hide them here =.=
function init_xml_ending() {
  $xml_style_middle = "</color><scale>1.0</scale><Icon><href>http://www.gstatic.com/mapspro/images/stock/503-wht-blank_maps.png</href></Icon></IconStyle><LabelStyle><scale>";
  $xml_style_ending = "</scale></LabelStyle><BalloonStyle><text><![CDATA[<h3>$[name]</h3>]]></text></BalloonStyle></Style>";
  return "<Style id='icon-1574-097138-nodesc-normal'><IconStyle><color>ff387109"
    .$xml_style_middle.'0.0'.$xml_style_ending
    ."<Style id='icon-1574-097138-nodesc-highlight'><IconStyle><color>ff387109"
    .$xml_style_middle.'1.0'.$xml_style_ending
    ."<StyleMap id='icon-1574-097138-nodesc'>"
    ."<Pair><key>normal</key><styleUrl>#icon-1574-097138-nodesc-normal</styleUrl></Pair>"
    ."<Pair><key>highlight</key><styleUrl>#icon-1574-097138-nodesc-highlight</styleUrl></Pair></StyleMap>"
    ."<Style id='icon-1731-F57C00-nodesc-normal'><IconStyle><color>ff007CF5"
    .$xml_style_middle.'0.0'.$xml_style_ending
    ."<Style id='icon-1731-F57C00-nodesc-highlight'><IconStyle><color>ff007CF5"
    .$xml_style_middle.'1.0'.$xml_style_ending
    ."<StyleMap id='icon-1731-F57C00-nodesc'>"
    ."<Pair><key>normal</key><styleUrl>#icon-1731-F57C00-nodesc-normal</styleUrl></Pair>"
    ."<Pair><key>highlight</key><styleUrl>#icon-1731-F57C00-nodesc-highlight</styleUrl></Pair></StyleMap>"
    ."</Document></kml>"
    ;
}

?>
