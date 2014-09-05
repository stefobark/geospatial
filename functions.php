<?php

function convert($wkt_str)
{
    $ret_arr = array();
    $matches = array();
    
    preg_match('/\)\s*,\s*\(/', $wkt_str, $matches);
    
    if (empty($matches)) {
        $polys = array(
            trim($wkt_str)
        );
    } else {
        $polys = explode($matches[0], trim($wkt_str));
    }
    foreach ($polys as $poly) {
        $ret_arr[] = str_replace('(', '', str_replace(')', '', substr($poly, stripos($poly, '(') + 2, stripos($poly, ')') - 2)));
    }
    
    $ret_arr = str_replace(' ', ',', $ret_arr);
    
    return $ret_arr;
    
}
