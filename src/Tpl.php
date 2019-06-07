<?php

declare(strict_types=1);

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
     */
    public function __construct(array $templateFolderList, ?string $translationFile = null)
    {
        $this->templateFolderList = $templateFolderList;
        $this->translationFile = $translationFile;
        $this->addCallback('bytes_to_human', [__CLASS__, 'toHuman']);
    }

    public function addDefault(array $templateVariables): void
    {
        $this->templateVariables = array_merge($this->templateVariables, $templateVariables);
    }

    public function addCallback(string $callbackName, callable $cb): void
    {
        $this->callbackList[$callbackName] = $cb;
    }

    public function render(string $templateName, array $templateVariables = []): string
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

    public static function toHuman(int $byteSize): string
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

    private function insert(string $templateName, array $templateVariables = []): string
    {
        return $this->render($templateName, $templateVariables);
    }

    private function start(string $sectionName): void
    {
        if (null !== $this->activeSectionName) {
            throw new TplException(sprintf('section "%s" already started', $this->activeSectionName));
        }

        $this->activeSectionName = $sectionName;
        ob_start();
    }

    private function stop(): void
    {
        if (null === $this->activeSectionName) {
            throw new TplException('no section started');
        }

        $this->sectionList[$this->activeSectionName] = ob_get_clean();
        $this->activeSectionName = null;
    }

    private function layout(string $layoutName, array $templateVariables = []): void
    {
        $this->layoutList[$layoutName] = $templateVariables;
    }

    private function section(string $sectionName): string
    {
        if (!\array_key_exists($sectionName, $this->sectionList)) {
            throw new TplException(sprintf('section "%s" does not exist', $sectionName));
        }

        return $this->sectionList[$sectionName];
    }

    private function e(string $v, ?string $cb = null): string
    {
        if (null !== $cb) {
            $v = $this->batch($v, $cb);
        }

        return htmlentities($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function batch(string $v, string $cb): string
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
     */
    private function d(string $d, string $f = 'Y-m-d H:i:s'): string
    {
        return $this->e(date_format(new DateTime($d), $f));
    }

    private function t(string $v): string
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

    private function exists(string $templateName): bool
    {
        foreach ($this->templateFolderList as $templateFolder) {
            $templatePath = sprintf('%s/%s.php', $templateFolder, $templateName);
            if (file_exists($templatePath)) {
                return true;
            }
        }

        return false;
    }

    private function templatePath(string $templateName): string
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
