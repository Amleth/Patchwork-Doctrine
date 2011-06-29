<?php /****************** vi: set fenc=utf-8 ts=4 sw=4 et: *****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


class adapter_EM
{
    protected static $em = array();

    static function connect($dsn)
    {
        $hash = md5(implode(';', $dsn));

        if (isset(self::$em[$hash])) return self::$em[$hash];

        $config = new \Doctrine\ORM\Configuration;

        $cache = new $CONFIG['doctrine.cache'];

        $driver = $config->newDefaultAnnotationDriver(array($CONFIG['doctrine.entities.dir']));

        $config->setMetadataCacheImpl($cache);
        $config->setMetadataDriverImpl($driver);
        $config->setQueryCacheImpl($cache);
        $config->setProxyDir($CONFIG['doctrine.proxy.dir']);
        $config->setAutoGenerateProxyClasses($CONFIG['doctrine.proxy.dir']);
        $config->setProxyNamespace("Proxies");

        if (!empty($CONFIG['doctrine.dbal.logger']))
        {
            $config->setSQLLogger(new $CONFIG['doctrine.dbal.logger']);
        }

        self::$em[$hash] = \Doctrine\ORM\EntityManager::create($dsn, $config);

        \Doctrine\DBAL\Types\Type::addType('action', 'BrevetTypes\ActionType');
        \Doctrine\DBAL\Types\Type::addType('etat_realisation', 'BrevetTypes\EtatRealisationType');
        \Doctrine\DBAL\Types\Type::addType('type_propriete', 'BrevetTypes\ProcedureProprieteType');
        \Doctrine\DBAL\Types\Type::addType('reference_date', 'BrevetTypes\ReferenceDateType');
        \Doctrine\DBAL\Types\Type::addType('reference_delai_report', 'BrevetTypes\ReferenceDelaiReportType');
        \Doctrine\DBAL\Types\Type::addType('role', 'BrevetTypes\RoleType');
        \Doctrine\DBAL\Types\Type::addType('severite', 'BrevetTypes\SeveriteType');

        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('ActionType', 'action');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('EtatRealisationType', 'etat_realisation');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('ProcedureProprieteType', 'type_propriete');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('ReferenceDateType', 'reference_date');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('ReferenceDelaiReportType', 'reference_delai_report');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('RoleType', 'role');
        self::$em[$hash]->getConnection()->getDatabasePlatform()
            ->registerDoctrineTypeMapping('SeveriteType', 'severite');

        return self::$em[$hash];
    }
}
