<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\CliException;

class CliParser
{
    /** @var string */
    private $programDescription;

    /** @var array */
    private $optionList;

    /**
     * Create CliParser object.
     *
     * @param string $programDescription a short sentence indicating what the
     *                                   program is about
     * @param array  $optionList         an array with all options, whether
     *                                   they are required and whether they
     *                                   take values
     *
     * Examples:
     * $optionList = [
     *     ['foo', 'Foo Description', false, false], // no value, not required
     *     ['bar', 'Bar Description', false, true ], // no value, required
     *     ['baz', 'Baz Description', true,  false], // value, not required
     *     ['xyz', 'Xyz Description', true,  true ], // value, required
     * ];
     *
     * The first field indicates the name of the parameter, the user will have
     * to provide --<OPT>, e.g. --foo, --bar, ...
     *
     * The second describes the parameter, that is used when the --help
     * parameter is used.
     *
     * The third indicates whether or not the parameter requires a value, e.g.
     * --baz abc where 'abc' is the value.
     *
     * The fourth indicates whether or not the parameter is required
     */
    public function __construct($programDescription, array $optionList)
    {
        $this->programDescription = $programDescription;
        $this->optionList = $optionList;
    }

    /**
     * @return string
     */
    public function help()
    {
        $helpText = $this->programDescription.PHP_EOL;
        $helpText .= 'Options:'.PHP_EOL;
        foreach ($this->optionList as $k => $v) {
            if ($v[1]) {
                $helpText .= sprintf("  --%s %s\t\t%s", $k, $k, $v[0]);
            } else {
                $helpText .= sprintf("  --%s\t\t%s", $k, $v[0]);
            }
            if ($v[2]) {
                $helpText .= ' (REQUIRED)';
            }
            $helpText .= PHP_EOL;
        }

        return $helpText;
    }

    /**
     * @return \LC\Portal\Config
     */
    public function parse(array $argv)
    {
        $argc = \count($argv);
        $optionValues = [];

        for ($i = 1; $i < $argc; ++$i) {
            if (0 === strpos($argv[$i], '--')) {
                // it is an option selector
                $p = substr($argv[$i], 2);  // strip the dashes
                $pO = [];
                while ($i + 1 < $argc && false === strpos($argv[$i + 1], '--')) {
                    $pO[] = $argv[++$i];
                }
                if (1 === \count($pO)) {
                    $optionValues[$p] = $pO[0];
                } else {
                    $optionValues[$p] = $pO;
                }
            }
        }

        // --help is special
        if (\array_key_exists('help', $optionValues)) {
            return new Config(['help' => true]);
        }

        // check if any of the required keys is missing
        foreach (array_keys($this->optionList) as $opt) {
            if ($this->optionList[$opt][2]) {
                // required
                if (!\array_key_exists($opt, $optionValues)) {
                    throw new CliException(sprintf('missing required parameter "--%s"', $opt));
                }
            }
        }

        // check if any of the options that require a value has no value
        foreach (array_keys($this->optionList) as $opt) {
            if ($this->optionList[$opt][1]) {
                // check if it is actually there
                if (\array_key_exists($opt, $optionValues)) {
                    // must have value
                    if (\is_array($optionValues[$opt])) {
                        if (0 === \count($optionValues[$opt])) {
                            throw new CliException(sprintf('missing required parameter value for option "--%s"', $opt));
                        }
                    }
                }
            }
        }

        return new Config($optionValues);
    }
}
