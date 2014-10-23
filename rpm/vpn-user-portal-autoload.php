<?php
$vendorDir = '/usr/share/php';
$pearDir   = '/usr/share/pear';
$baseDir   = dirname(__DIR__);

require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\VpnPortal'            => $baseDir.'/src',
        'fkooman\\Rest'                 => $vendorDir,
        'fkooman\\Rest\\Plugin\\Mellon' => $vendorDir,
        'fkooman\\Json'                 => $vendorDir,
        'fkooman\\Http'                 => $vendorDir,
        'fkooman\\Ini'                  => $vendorDir,
        'GuzzleHttp\\Stream'            => $vendorDir,
        'GuzzleHttp'                    => $vendorDir,
    )
);
$loader->registerPrefixes(
    array(
        'Twig_'               => array($pearDir, $vendorDir),
    )
);

$loader->register();

# Guzzle 4.0 requirement, should be gone in Guzzle 5.0?
require_once $vendorDir.'/GuzzleHttp/Stream/functions.php';
require_once $vendorDir.'/GuzzleHttp/functions.php';
