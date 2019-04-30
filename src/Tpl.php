<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use DateTime;
use LC\Portal\Exception\TplException;

class Tpl implements TplInterface
{
    /** @var array<string> */
    private $templateFolderList;

    /** @var string|null */
    private $translationFile;

    /** @var string|null */
    private $activeSectionName = null;

    /** @var array */
    private $sectionList = [];

    /** @var array */
    private $layoutList = [];

    /** @var array */
    private $templateVariables = [];

    /** @var array */
    private $callbackList = [];

    /**
     * @param array<string> $templateFolderList
     * @param string        $translationFile
     */
    public function __construct(array $templateFolderList, $translationFile = null)
    {
        $this->templateFolderList = $templateFolderList;
        $this->translationFile = $translationFile;
        $this->addCallback('bytes_to_human', [__CLASS__, 'toHuman']);
    }

    /**
     * @param array $templateVariables
     *
     * @return void
     */
    public function addDefault(array $templateVariables)
    {
        $this->templateVariables = array_merge($this->templateVariables, $templateVariables);
    }

    /**
     * @param string   $callbackName
     * @param callable $cb
     *
     * @return void
     */
    public function addCallback($callbackName, callable $cb)
    {
        $this->callbackList[$callbackName] = $cb;
    }

    /**
     * @param string $templateName
     * @param array  $templateVariables
     *
     * @return string
     */
    public function render($templateName, array $templateVariables = [])
    {
        $this->templateVariables = array_merge($this->templateVariables, $templateVariables);
        extract($this->templateVariables);
        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        include $this->templatePath($templateName);
        $templateStr = ob_get_clean();
        if (0 === \count($this->layoutList)) {
            // we have no layout defined, so simple template...
            return $templateStr;
        }

        foreach ($this->layoutList as $templateName => $templateVariables) {
            unset($this->layoutList[$templateName]);
            $templateStr .= $this->render($templateName, $templateVariables);
        }

        return $templateStr;
    }

    /**
     * @param int $byteSize
     *
     * @return string
     */
    public static function toHuman($byteSize)
    {
        $kB = 1024;
        $MB = $kB * 1024;
        $GB = $MB * 1024;
        $TB = $GB * 1024;
        if ($byteSize > $TB) {
            return sprintf('%0.2f TiB', $byteSize / $TB);
        }
        if ($byteSize > $GB) {
            return sprintf('%0.2f GiB', $byteSize / $GB);
        }
        if ($byteSize > $MB) {
            return sprintf('%0.2f MiB', $byteSize / $MB);
        }

        return sprintf('%0.0f kiB', $byteSize / $kB);
    }

    /**
     * @param string $templateName
     * @param array  $templateVariables
     *
     * @return string
     */
    private function insert($templateName, array $templateVariables = [])
    {
        return $this->render($templateName, $templateVariables);
    }

    /**
     * @param string $sectionName
     *
     * @return void
     */
    private function start($sectionName)
    {
        if (null !== $this->activeSectionName) {
            throw new TplException(sprintf('section "%s" already started', $this->activeSectionName));
        }

        $this->activeSectionName = $sectionName;
        ob_start();
    }

    /**
     * @return void
     */
    private function stop()
    {
        if (null === $this->activeSectionName) {
            throw new TplException('no section started');
        }

        $this->sectionList[$this->activeSectionName] = ob_get_clean();
        $this->activeSectionName = null;
    }

    /**
     * @param string $layoutName
     * @param array  $templateVariables
     *
     * @return void
     */
    private function layout($layoutName, array $templateVariables = [])
    {
        $this->layoutList[$layoutName] = $templateVariables;
    }

    /**
     * @param string $sectionName
     *
     * @return string
     */
    private function section($sectionName)
    {
        if (!\array_key_exists($sectionName, $this->sectionList)) {
            throw new TplException(sprintf('section "%s" does not exist', $sectionName));
        }

        return $this->sectionList[$sectionName];
    }

    /**
     * @param string      $v
     * @param string|null $cb
     *
     * @return string
     */
    private function e($v, $cb = null)
    {
        if (null !== $cb) {
            $v = $this->batch($v, $cb);
        }

        return htmlentities($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param string $v
     * @param string $cb
     *
     * @return string
     */
    private function batch($v, $cb)
    {
        $functionList = explode('|', $cb);
        foreach ($functionList as $f) {
            if ('escape' === $f) {
                $v = $this->e($v);
                continue;
            }
            if (\array_key_exists($f, $this->callbackList)) {
                $f = $this->callbackList[$f];
            } else {
                if (!\function_exists($f)) {
                    throw new TplException(sprintf('function "%s" does not exist', $f));
                }
            }
            $v = \call_user_func($f, $v);
        }

        return $v;
    }

    /**
     * Format a date.
     *
     * @param string $d
     * @param string $f
     *
     * @return string
     */
    private function d($d, $f = 'Y-m-d H:i:s')
    {
        return $this->e(date_format(new DateTime($d), $f));
    }

    /**
     * @param string $v
     *
     * @return string
     */
    private function t($v)
    {
        if (null === $this->translationFile) {
            // no translation file, use original
            $translatedText = $v;
        } else {
            /** @psalm-suppress UnresolvableInclude */
            $translationData = include $this->translationFile;
            if (\array_key_exists($v, $translationData)) {
                // translation found
                $translatedText = $translationData[$v];
            } else {
                // not found, use original
                $translatedText = $v;
            }
        }

        // find all string values, wrap the key, and escape the variable
        $escapedVars = [];
        foreach ($this->templateVariables as $k => $v) {
            if (\is_string($v)) {
                $escapedVars['%'.$k.'%'] = $this->e($v);
            }
        }

        return str_replace(array_keys($escapedVars), array_values($escapedVars), $translatedText);
    }

    /**
     * @param string $templateName
     *
     * @return bool
     */
    private function exists($templateName)
    {
        foreach ($this->templateFolderList as $templateFolder) {
            $templatePath = sprintf('%s/%s.php', $templateFolder, $templateName);
            if (file_exists($templatePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $templateName
     *
     * @return string
     */
    private function templatePath($templateName)
    {
        foreach (array_reverse($this->templateFolderList) as $templateFolder) {
            $templatePath = sprintf('%s/%s.php', $templateFolder, $templateName);
            if (file_exists($templatePath)) {
                return $templatePath;
            }
        }

        throw new TplException(sprintf('template "%s" does not exist', $templateName));
    }
}
