<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SURFnet\VPN\Portal;

use RuntimeException;
use SURFnet\VPN\Common\TplInterface;
use Twig_Environment;
use Twig_Extensions_Extension_I18n;
use Twig_Loader_Filesystem;
use Twig_SimpleFilter;

class TwigTpl implements TplInterface
{
    /** @var Twig_Environment */
    private $twig;

    /** @var array */
    private $defaultVariables;

    /**
     * Create TwigTemplateManager.
     *
     * @param array  $templateDirs template directories to look in where later
     *                             paths override the earlier paths
     * @param string $cacheDir     the writable directory to store the cache
     */
    public function __construct(array $templateDirs, $cacheDir = null)
    {
        $existingTemplateDirs = array();
        foreach ($templateDirs as $templateDir) {
            if (false !== is_dir($templateDir)) {
                $existingTemplateDirs[] = $templateDir;
            }
        }
        $existingTemplateDirs = array_reverse($existingTemplateDirs);

        $environmentOptions = array(
            'strict_variables' => true,
        );

        if (null !== $cacheDir) {
            if (false === is_dir($cacheDir)) {
                if (false === @mkdir($cacheDir, 0700, true)) {
                    throw new RuntimeException('unable to create template cache directory');
                }
            }
            $environmentOptions['cache'] = $cacheDir;
        }

        $this->twig = new Twig_Environment(
            new Twig_Loader_Filesystem(
                $existingTemplateDirs
            ),
            $environmentOptions
        );

        $this->defaultVariables = array();
    }

    public function setDefault(array $templateVariables)
    {
        $this->defaultVariables = $templateVariables;
    }

    public function addDefault(array $templateVariables)
    {
        $this->defaultVariables = array_merge(
            $this->defaultVariables, $templateVariables
        );
    }

    public function setI18n($appName, $languageStr, $localeDir)
    {
        putenv(sprintf('LC_ALL=%s', $languageStr));

        if (false === setlocale(LC_ALL, [$languageStr, sprintf('%s.UTF-8', $languageStr)])) {
            throw new RuntimeException(sprintf('unable to set locale "%s"', $languageStr));
        }

        if ($localeDir !== bindtextdomain($appName, $localeDir)) {
            throw new RuntimeException('unable to bind text domain');
        }

        if (!is_string(bind_textdomain_codeset($appName, 'UTF-8'))) {
            throw new RuntimeException('unable to bind text domain codeset');
        }

        if ($appName !== textdomain($appName)) {
            throw new RuntimeException('unable to set text domain');
        }

        $this->twig->addExtension(new Twig_Extensions_Extension_I18n());
    }

    public function addFilter(Twig_SimpleFilter $filter)
    {
        $this->twig->addFilter($filter);
    }

    /**
     * Render the template.
     *
     * @param string $templateName      the name of the template
     * @param array  $templateVariables the variables to be used in the
     *                                  template
     *
     * @return string the rendered template
     */
    public function render($templateName, array $templateVariables)
    {
        $templateVariables = array_merge($this->defaultVariables, $templateVariables);

        return $this->twig->render(
            sprintf(
                '%s.twig',
                $templateName
            ),
            $templateVariables
        );
    }
}
