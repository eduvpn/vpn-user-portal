<?php

declare(strict_types=1);

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PER' => true,
        '@PER:risky' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        'header_comment' => [
            'header' => <<< 'EOD'
                eduVPN - End-user friendly VPN.

                Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
                SPDX-License-Identifier: AGPL-3.0+
                EOD,
        ],
    ]
)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__))
;
