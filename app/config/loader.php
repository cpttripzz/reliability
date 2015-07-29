<?php

$loader = new \Phalcon\Loader();

/**
 * We're a registering a set of directories taken from the configuration file
 */
$loader->registerNamespaces(array(
    'NatInt\Models' => $config->application->modelsDir,
    'NatInt\Controllers' => $config->application->controllersDir,
    'NatInt\Services' => $config->application->servicesDir,
    'NatInt\Plugins' => $config->application->pluginsDir
));
$loader->register();
//require_once __DIR__ . '/../../vendor/autoload.php';
