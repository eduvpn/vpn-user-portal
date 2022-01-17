<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use DateTimeImmutable;
use DateTimeZone;
use RangeException;
use Vpn\Portal\Exception\TplException;
use Vpn\Portal\OAuth\ClientDb;

class Tpl implements TplInterface
{
    /** @var array<string> */
    private array $templateFolderList;

    /** @var array<string> */
    private array $translationFolderList;

    private string $uiLanguage;

    private string $assetDir;

    private ?string $activeSectionName = null;

    /** @var array<string,string> */
    private array $sectionList = [];

    /** @var array<string,array> */
    private array $layoutList = [];

    /** @var array<string,mixed> */
    private array $templateVariables = [];

    /** @var array<string,callable> */
    private array $callbackList = [];

    /** @var array<string,string> */
    private array $profileConfigDisplayNameCache = [];

    /**
     * @param array<string,mixed> $templateVariables
     */
    public function __construct(string $baseDir, ?string $styleName, string $uiLanguage, array $templateVariables = [])
    {
        $this->templateFolderList = self::templateFolderList($baseDir, $styleName);
        $this->translationFolderList = self::translationFolderList($baseDir, $styleName);
        $this->uiLanguage = $uiLanguage;
        $this->assetDir = $baseDir.'/web';
        $this->templateVariables = $templateVariables;
    }

    /**
     * @param mixed $v
     */
    public function addDefault(string $k, $v): void
    {
        $this->templateVariables[$k] = $v;
    }

    /**
     * @param array<string,mixed> $templateVariables
     */
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

    public function clientIdToDisplayName(string $clientId): string
    {
        // XXX this should probably not be here...
        $clientDb = new ClientDb();
        if (null === $clientInfo = $clientDb->get($clientId)) {
            return $this->e($clientId);
        }

        return $this->e($clientInfo->displayName());
    }

    /**
     * @param array<\Vpn\Portal\ProfileConfig> $profileConfigList
     */
    public function profileIdToDisplayName(array $profileConfigList, string $profileId): string
    {
        if (\array_key_exists($profileId, $this->profileConfigDisplayNameCache)) {
            return $this->e($this->profileConfigDisplayNameCache[$profileId]);
        }

        foreach ($profileConfigList as $profileConfig) {
            if ($profileId === $profileConfig->profileId()) {
                $this->profileConfigDisplayNameCache[$profileId] = $profileConfig->displayName();

                return $this->e($profileConfig->displayName());
            }
        }

        $this->profileConfigDisplayNameCache[$profileId] = $profileId;

        return $this->e($profileId);
    }

    public function uiLanguage(): string
    {
        return $this->uiLanguage;
    }

    public static function languageCodeToHuman(string $uiLanguage): string
    {
        $supportedLanguages = self::supportedLanguages();
        if (!\array_key_exists($uiLanguage, $supportedLanguages)) {
            throw new TplException(sprintf('unsupported UI language "%s"', $uiLanguage));
        }

        return $supportedLanguages[$uiLanguage];
    }

    public function bth(int $byteSize): string
    {
        $KiB = 1024;
        $MiB = $KiB * 1024;
        $GiB = $MiB * 1024;
        $TiB = $GiB * 1024;
        $PiB = $TiB * 1024;

        if ($byteSize >= $PiB) {
            return sprintf('%1$.2f PiB', $byteSize / $PiB);
        }
        if ($byteSize >= $TiB) {
            return sprintf('%1$.2f TiB', $byteSize / $TiB);
        }
        if ($byteSize >= $GiB) {
            return sprintf('%1$.2f GiB', $byteSize / $GiB);
        }
        if ($byteSize >= $MiB) {
            return sprintf('%1$.2f MiB', $byteSize / $MiB);
        }
        if ($byteSize >= $KiB) {
            return sprintf('%1$.2f KiB', $byteSize / $KiB);
        }

        return sprintf('%1$.0f B', $byteSize);
    }

    /**
     * @param array<string,mixed> $templateVariables
     */
    public function insert(string $templateName, array $templateVariables = []): string
    {
        return $this->render($templateName, $templateVariables);
    }

    /**
     * Get a URL with cache busting query parameter.
     */
    public function getAssetUrl(string $requestRoot, string $assetPath): string
    {
        if (false === $mTime = @filemtime($this->assetDir.'/'.$assetPath)) {
            // can't find file or determine last modified time, do not include
            // cache busting query parameter
            return $this->e($requestRoot.$assetPath);
        }

        return $this->e($requestRoot.$assetPath.'?mTime='.$mTime);
    }

    public function start(string $sectionName): void
    {
        if (null !== $this->activeSectionName) {
            throw new TplException(sprintf('section "%s" already started', $this->activeSectionName));
        }

        $this->activeSectionName = $sectionName;
        ob_start();
    }

    public function stop(string $sectionName): void
    {
        if (null === $this->activeSectionName) {
            throw new TplException('no section started');
        }

        if ($sectionName !== $this->activeSectionName) {
            throw new TplException(sprintf('attempted to end section "%s" but current section is "%s"', $sectionName, $this->activeSectionName));
        }

        $this->sectionList[$this->activeSectionName] = ob_get_clean();
        $this->activeSectionName = null;
    }

    /**
     * @param array<string,mixed> $templateVariables
     */
    public function layout(string $layoutName, array $templateVariables = []): void
    {
        $this->layoutList[$layoutName] = $templateVariables;
    }

    public function section(string $sectionName): string
    {
        if (!\array_key_exists($sectionName, $this->sectionList)) {
            throw new TplException(sprintf('section "%s" does not exist', $sectionName));
        }

        return $this->sectionList[$sectionName];
    }

    public static function escape(string $v): string
    {
        return htmlentities($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function e(string $v, ?string $cb = null): string
    {
        if (null !== $cb) {
            $v = $this->batch($v, $cb);
        }

        return self::escape($v);
    }

    /**
     * Trim a string to a specified lenght and escape it.
     *
     * @throws \RangeException
     */
    public function etr(string $inputString, int $maxLen, ?string $cb = null): string
    {
        if ($maxLen < 3) {
            throw new RangeException('"maxLen" must be >= 3');
        }

        $strLen = Binary::safeStrlen($inputString);
        if ($strLen <= $maxLen) {
            return $inputString;
        }

        $partOne = Binary::safeSubstr($inputString, 0, (int) ceil(($maxLen - 1) / 2));
        $partTwo = Binary::safeSubstr($inputString, (int) -floor(($maxLen - 1) / 2));

        return $this->e($partOne.'…'.$partTwo, $cb);
    }

    public function batch(string $v, string $cb): string
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
    public function d(DateTimeImmutable $dateTime, string $dateFormat = 'Y-m-d H:i:s T'): string
    {
        $dateTime = $dateTime->setTimeZone(new DateTimeZone(date_default_timezone_get()));

        return $this->e($dateTime->format($dateFormat));
    }

    /**
     * Format a date, UTC.
     */
    public function du(DateTimeImmutable $dateTime, string $dateFormat = 'Y-m-d H:i:s T'): string
    {
        $dateTime = $dateTime->setTimeZone(new DateTimeZone('UTC'));

        return $this->e($dateTime->format($dateFormat));
    }

    public function t(string $v): string
    {
        // use original, unless it is found in any of the translation files...
        $translatedText = $v;
        if ('en-US' !== $this->uiLanguage) {
            foreach ($this->translationFolderList as $translationFolder) {
                $translationFile = $translationFolder.'/'.$this->uiLanguage.'.php';
                if (!file_exists($translationFile)) {
                    continue;
                }

                /** @var array<string,string> $translationData */
                $translationData = include $translationFile;
                if (\array_key_exists($v, $translationData)) {
                    // translation found, run with it, we don't care if we find
                    // it in other file(s) as well!
                    $translatedText = $translationData[$v];

                    break;
                }
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

    public function exists(string $templateName): bool
    {
        foreach ($this->templateFolderList as $templateFolder) {
            $templatePath = sprintf('%s/%s.php', $templateFolder, $templateName);
            if (file_exists($templatePath)) {
                return true;
            }
        }

        return false;
    }

    public function templatePath(string $templateName): string
    {
        foreach (array_reverse($this->templateFolderList) as $templateFolder) {
            $templatePath = sprintf('%s/%s.php', $templateFolder, $templateName);
            if (file_exists($templatePath)) {
                return $templatePath;
            }
        }

        throw new TplException(sprintf('template "%s" does not exist', $templateName));
    }

    /**
     * @return array<string,string>
     */
    public static function supportedLanguages(): array
    {
        return [
            'en-US' => 'English',
            'ar-MA' => 'العربية',
            'da-DK' => 'Dansk',
            'de-DE' => 'Deutsch',
            'es-LA' => 'español',
            'et-EE' => 'Eesti',
            'fr-FR' => 'Français',
            'nb-NO' => 'norsk bokmål',
            'nl-NL' => 'Nederlands',
            'pl-PL' => 'polski',
            'pt-PT' => 'Português',
            'ro-RO' => 'română',
            'uk-UA' => 'Українська',
        ];
    }

    public function textDir(): string
    {
        if (\in_array($this->uiLanguage, ['ar-MA'], true)) {
            return 'rtl';
        }

        return 'ltr';
    }

    public function reset(): void
    {
        ob_clean();
        $this->activeSectionName = null;
        $this->sectionList = [];
        $this->layoutList = [];
        $this->callbackList = [];
    }

    /**
     * @return array<string>
     */
    private static function templateFolderList(string $baseDir, ?string $styleName): array
    {
        $templateDirs = [
            $baseDir.'/views',
            $baseDir.'/config/views',
        ];
        if (null !== $styleName) {
            $templateDirs[] = $baseDir.'/views/'.$styleName;
            $templateDirs[] = $baseDir.'/config/views/'.$styleName;
        }

        return $templateDirs;
    }

    /**
     * @return array<string>
     */
    private static function translationFolderList(string $baseDir, ?string $styleName): array
    {
        $translationDirs = [
            $baseDir.'/locale',
            $baseDir.'/config/locale',
        ];
        if (null !== $styleName) {
            $translationDirs[] = $baseDir.'/locale/'.$styleName;
            $translationDirs[] = $baseDir.'/config/locale/'.$styleName;
        }

        return $translationDirs;
    }
}
