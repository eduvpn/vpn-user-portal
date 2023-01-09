<?php

declare(strict_types=1);

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PER' => true,
        '@PER:risky' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,

        // Unused use statements must be removed.
        'no_unused_imports' => true,
        // Ordering use statements.
        'ordered_imports' => true,
        // Orders the elements of classes/interfaces/traits/enums.
        'ordered_class_elements' => true,
        // Annotations in PHPDoc should be grouped together so that annotations
        // of the same type immediately follow each other. Annotations of a
        // different type are separated by a single blank line.
        'phpdoc_separation' => true,

        'header_comment' => [
            'header' => <<< 'EOD'
                eduVPN - End-user friendly VPN.

                Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
                SPDX-License-Identifier: AGPL-3.0+
                EOD,
        ],
    ]
)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__))
;
