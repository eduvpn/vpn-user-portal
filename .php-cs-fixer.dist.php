<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        'no_alternative_syntax' => false,
        'echo_tag_syntax' => ['format' => 'short'],
        'header_comment' => [
            'header' => <<< 'EOD'
                eduVPN - End-user friendly VPN.

                Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
                SPDX-License-Identifier: AGPL-3.0+
                EOD,
        ],
    ]
)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__))
;
