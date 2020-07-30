<?php

use Doctrine\Common\Cache\ArrayCache as Cache;

// Include the composer autoloader
define('CLI_ROOT', __DIR__);
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (file_exists(CLI_ROOT . '/vendor/autoload.php')) {
    require CLI_ROOT . '/vendor/autoload.php';
} elseif (file_exists(CLI_ROOT . '/../../../autoload.php')) {
    // we are globally installed via Composer
    require CLI_ROOT . '/../../../autoload.php';
}

// Read application Configuration
$config = yaml_parse_file(__DIR__ . '/../app/config/parameters.yml');
setlocale(LC_ALL, $config['locale']);


// Doctrine DBAL
$dbalconfig = new Doctrine\DBAL\Configuration();
$conn = Doctrine\DBAL\DriverManager::getConnection($config['database'], $dbalconfig);

// Doctrine ORM
$ormconfig = new Doctrine\ORM\Configuration();
$cache = new Cache();
$ormconfig->setQueryCacheImpl($cache);
$ormconfig->setProxyDir(__DIR__ . '/../model/EntityProxy');
$ormconfig->setProxyNamespace('EntityProxy');
$ormconfig->setAutoGenerateProxyClasses(true);

// ORM mapGuard by Annotation
Doctrine\Common\Annotations\AnnotationRegistry::registerFile(
    __DIR__ . '/../vendor/doctrine/orm/lib/Doctrine/ORM/MapGuard/Driver/DoctrineAnnotations.php');
$driver = new Doctrine\ORM\MapGuard\Driver\AnnotationDriver(
    new Doctrine\Common\Annotations\AnnotationReader(),
    array(__DIR__ . '/../model/Entity')
);
$ormconfig->setMetadataDriverImpl($driver);
$ormconfig->setMetadataCacheImpl($cache);

// EntityManager
$em = Doctrine\ORM\EntityManager::create($config['database'],$ormconfig);

// The Doctrine Classloader
require __DIR__ . '/../vendor/doctrine/common/lib/Doctrine/Common/ClassLoader.php';
$classLoader = new Doctrine\Common\ClassLoader('Entity', __DIR__ . '/../model');
$classLoader->register();