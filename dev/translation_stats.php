<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$localeFileList = glob(dirname(__DIR__).'/locale/*.php');

$emptyList = include dirname(__DIR__).'/locale/empty.php';
$translationCount = count($emptyList);

foreach ($localeFileList as $localeFile) {
    $translationList = include $localeFile;
    if ('empty' === basename($localeFile, '.php')) {
        continue;
    }
    $translationStats[basename($localeFile, '.php')] = sprintf('%3d', count($translationList) / $translationCount * 100);
}

arsort($translationStats);

foreach ($translationStats as $l => $cnt) {
    echo $l."\t".sprintf('%3d', $cnt).'%'.\PHP_EOL;
}
