<?php

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PSR12' => true,
        'visibility_required' => ['elements' => ['method', 'property']],
        'ordered_imports' => true,
        'ordered_class_elements' => true,
        'array_syntax' => ['syntax' => 'short'],
        'phpdoc_order' => true,
        'no_unused_imports' => true,
        'phpdoc_no_empty_return' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'php_unit_strict' => true,
        'header_comment' => [
            'header' => <<< 'EOD'
eduVPN - End-user friendly VPN.

Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
SPDX-License-Identifier: AGPL-3.0+
EOD,
        ],
    ]
)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__))
;
