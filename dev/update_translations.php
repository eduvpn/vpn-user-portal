<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$localeFileList = glob(dirname(__DIR__).'/locale/*.php');
$tplFileList = glob(dirname(__DIR__).'/views/*.php');

// extract all translatable strings from the PHP templates and put them in an
// array
$sourceStr = [];
foreach ($tplFileList as $tplFile) {
    $phpFile = file_get_contents($tplFile);
    // find all translatable strings in the template...
    preg_match_all("/this->t\('(.*?)'\)/", $phpFile, $matches);
    foreach ($matches[1] as $m) {
        if (!in_array($m, $sourceStr, true)) {
            $sourceStr[] = $m;
        }
    }
}

foreach ($localeFileList as $localeFile) {
    $localeData = include $localeFile;

    // check which keys are missing from translation file
    foreach ($sourceStr as $k) {
        if (!array_key_exists($k, $localeData)) {
            // adding them to translation file
            $localeData[$k] = '';
        }
    }

    $deletedList = [];
    // check which translations are there, but are no longer needed...
    foreach ($localeData as $k => $v) {
        if (!in_array($k, $sourceStr, true)) {
            // remove them from the translation file, add them to the
            // "deleted" array
            unset($localeData[$k]);
            $deletedList[$k] = $v;
        }
    }

    // sort the translations
    ksort($localeData);
    ksort($deletedList);

    // create the locale file
    $output = '<?php'.PHP_EOL.PHP_EOL.'return ['.PHP_EOL;
    foreach ($localeData as $k => $v) {
        $k = quoteStr($k);
        $v = quoteStr($v);
        if (empty($v)) {
            $output .= sprintf("    //'%s' => '%s',", $k, $v).PHP_EOL;
        } else {
            $output .= sprintf("    '%s' => '%s',", $k, $v).PHP_EOL;
        }
    }
    // add the deleted entries as comments
    foreach ($deletedList as $k => $v) {
        $k = quoteStr($k);
        $v = quoteStr($v);
        $output .= sprintf("    // [DELETED] '%s' => '%s',", $k, $v).PHP_EOL;
    }
    $output .= '];';

    // write locale file
    file_put_contents($localeFile, $output);
}

function quoteStr($str)
{
    return str_replace("'", "\'", $str);
}
