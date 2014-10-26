<?php
$vendorDir = '/usr/share/php';
$pearDir   = '/usr/share/pear';
$baseDir   = dirname(__DIR__);

require_once $vendorDir.'/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();
$loader->registerNamespaces(
    array(
        'fkooman\\VpnPortal'                  => $baseDir.'/src',
        'fkooman\\Rest'                       => $vendorDir,
        'fkooman\\Rest\\Plugin\\Mellon'       => $vendorDir,
        'fkooman\\Json'                       => $vendorDir,
        'fkooman\\Http'                       => $vendorDir,
        'fkooman\\Ini'                        => $vendorDir,
        'Guzzle'                              => $vendorDir,
        'Symfony\\Component\\EventDispatcher' => $vendorDir,
    )
);
$loader->registerPrefixes(
    array(
        'Twig_'               => array($pearDir, $vendorDir),
    )
);

$loader->register();
