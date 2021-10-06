<?php

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP80Migration' => true,
	  //  '@PHP80Migration:risky' => true,
	    '@PHPUnit84Migration:risky' => true,
	    'no_alternative_syntax' => false,
	    'echo_tag_syntax' => ['format' => 'short'],
        // breaks src/Http/Auth/PhpSamlSpAuthModule.php null assignment
	    'no_unset_on_property' => false,
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
