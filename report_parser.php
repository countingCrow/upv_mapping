<?php
// countingCrow; MIT license;

// google takeout mail file path
$file_path_mbox = 'dr.mbox';
// where to place your parsing result file
$file_path_result = 'result.json';
$agent_codename = 'countingCrow';
// --- end of config ---

// indicates are we in the html mail part
$is_html = false;
// count amout of mail we had found
$mail_counter = 0;
// store one html mail message
$msg = '';
// store all portal we had found
$result = array();
// create this only once
$str_owner_identify = ">$agent_codename</span></div>";
$upc_counter = 0;

list($start_usec, $start_sec) = explode(' ', microtime());
$fr = fopen($file_path_mbox, 'r');
if (!$fr) {
  echo "could not open file\n";
  exit;
}
// read line
while (($buffer = fgets($fr, 4096)) !== false) {
  if ($is_html) {
    // end of mail will have --[some kind of hash]
    if (strpos($buffer, '--') === 0) {
      $is_html = false;
      parse_mail($msg);
    }
    // not end of mail, safe to add
    else {
      $msg .= $buffer;
    }
  }
  else {
    // damage report html email starts with <div
    if (strpos($buffer, '<div') === 0) {
      $msg = '';
      $is_html = true;
    }
  }
}
if (!feof($fr)) {
  echo "unknown error\n";
  exit;
}
fclose($fr);
ksort($result);
// though json_encode does not have a limit at size or length
// but the portal name(s) we are going to face at here will make you a great pain
// since the structure are simple, geohash => portal_data, we 'encode' them one by one
// (also since we are going to loop through it, we do some little tricks in it.. merge attacker)
$json_result = crow_json_encode($result);
file_put_contents($file_path_result, $json_result);
$portal_count = count($result);
list($end_usec, $end_sec) = explode(' ', microtime());
$dt = ($end_sec - $start_sec + $end_usec - $start_usec) * 1e3;
echo "\n\nparsed $mail_counter mail(s), found $portal_count portal(s), with $upc_counter upc(s), used $dt ms\n";

function parse_mail($msg) {
  global $mail_counter, $result, $str_owner_identify;
  
  // short key makes json result less length
  $portal = array(
    // attacker list
    'e'   => array(),
    // geohash
    'g'   => '',
    // image url
    'i'   => '',
    'lat' => 1.0,
    'lng' => 1.0,
    // portal name
    'n'   => '',
    // is upc
    'upc' => false
  );
  // remove all line break; replace all = back from =3D;
  $msg = str_replace('=3D', '=', str_replace("=\r\n", '', $msg));
  // first <div> after DAMAGE REPORT would be portal name
  $msg = substr($msg, strpos($msg, 'DAMAGE REPORT'));
  $msg = substr($msg, strpos($msg, '<div>') + 5);
  // it is encoded in mail since possible containg chinese character(s)
  // quoted_printable_decode will make them back from =xx=xx=xx=oo=oo=oo
  $portal['n'] = quoted_printable_decode(substr($msg, 0, strpos($msg, '</div>')));
  // following interest would be portal lat lng
  $msg = substr($msg, strpos($msg, 'www.ingress.com/intel?ll=') + 25);
  $pos_comma = strpos($msg, ',');
  // store as float will make less " when encoded to json
  $portal['lat'] *= substr($msg, 0, $pos_comma);
  $portal['lng'] *= substr($msg, $pos_comma + 1, strpos($msg, '&') - $pos_comma - 1);
  $portal['g'] = $geohash = crow_geohash_encode($portal['lat'], $portal['lng']);
  // next would be portal image url
  $msg = substr($msg, strpos($msg, '<img src'));
  $msg = substr($msg, strpos($msg, '"') + 1);
  $portal['i'] = substr($msg, 0, strpos($msg, '"'));
  // now we fetch attacker name
  $msg = substr($msg, strpos($msg, 'DAMAGE'));
  $damage_fragment = substr($msg, 0, strpos($msg, '</span>'));
  $portal['e'][] = substr($damage_fragment, strpos($damage_fragment, '">') + 2);
  // if after attacker had self codename, it would be owner information, should be upc
  $portal['upc'] = strpos($msg, $str_owner_identify) !== false;
  // if entry not exist, create
  if (empty($result[$geohash])) {
    $result[$geohash] = $portal;
  }
  // else append attacker list and check upc
  else {
    $result[$geohash]['e'][] = $portal['e'][0];
    $result[$geohash]['upc'] |= $portal['upc'];
  }
  $mail_counter++;
  // make some noise
  if ($mail_counter % 1e3 === 0) {
    echo "$mail_counter.. ";
  }
}
function crow_json_encode($data) {
  global $upc_counter;
  
  $result_portal_list = array();
  foreach ($data as $geohash => $portal_data) {
    // merge same attacker codename
    // first filter out possible null or false to make array_count_values shut up
    $portal_data['e'] = array_filter($portal_data['e'], function($value) { return is_string($value) || is_int($value); });
    $count_value_result = array_count_values($portal_data['e']);
    $new_e = array();
    foreach ($count_value_result as $e_name => $e_counter) {
      $new_e[] = $e_counter > 1 ? "$e_name ($e_counter)" : "$e_name";
    }
    $portal_data['e'] = $new_e;
    $jsoned_portal_data = json_encode($portal_data);
    // when this happen, we will lose some hair :<
    if (json_last_error()) {
      // force to make them obedience =3=
      $portal_data['n'] = base64_encode($portal_data['n']);
      $jsoned_portal_data = json_encode($portal_data);
      // should not happen
      if ($json_err = json_last_error()) {
        var_dump($portal_data);
        echo "still json error \"$json_err\" at $geohash\n";
        continue;
      }
    }
    $result_portal_list[] = "\"$geohash\":$jsoned_portal_data";
    if ($portal_data['upc']) {
      $upc_counter++;
    }
  }
  return '{'.implode(',', $result_portal_list).'}';
}
// https://en.wikipedia.org/wiki/Geohash
function crow_geohash_encode($lat, $lng) {
  $base32_char_list = '0123456789bcdefghjkmnpqrstuvwxyz';
  $bit_list = array(16, 8, 4, 2, 1);
  $bit_pos = 0;
  $fragment = 0;
  $is_even = true;
  $precision = min((max(strlen(strstr($lat, '.')), strlen(strstr($lng, '.'))) - 1) * 2, 12);
  $range_lat = array(-90.0, 90.0);
  $range_lng = array(-180.0, 180.0);
  $result = '';
  
  do {
    if ($is_even) {
      $mid = array_sum($range_lng) / 2;
      // greater, select higher interval, use 1 at crt_pos
      if ($lng > $mid) {
        $fragment |= $bit_list[$bit_pos];
        $range_lng[0] = $mid;
      }
      else {
        $range_lng[1] = $mid;
      }
    }
    else {
      $mid = array_sum($range_lat) / 2;
      // lat case when odd
      if ($lat > $mid) {
        $fragment |= $bit_list[$bit_pos];
        $range_lat[0] = $mid;
      }
      else {
        $range_lat[1] = $mid;
      }
    }
    $is_even = !$is_even;
    if ($bit_pos < 4) {
      $bit_pos++;
      continue;
    }
    $result .= $base32_char_list{$fragment};
    if (strlen($result) < $precision) {
      $bit_pos = 0;
      $fragment = 0;
      continue;
    }
    break;
  } while (true);
  return $result;
}

?>
