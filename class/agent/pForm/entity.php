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

abstract class agent_pForm_entity extends agent_pForm
{
    public $get = array ('__1__:i:1' => 0);

    static protected $entityNs = 'Entities';

    protected

    $entityUrl,
    $entityClass,
    $entity,
    $entityIsNew = false,
    $entityIdentifier = array ();


    function control()
    {
        parent::control();

        if ($this->entity) return;

        if (empty($this->entityUrl))
        {
            $u = explode('_', substr(get_class($this), 6));

            if ('new' === end($u))
            {
                $this->entityIsNew = true;
                array_pop($u);
            }

            $this->entityUrl = implode('/', $u);
        }

        $this->entityClass = self::$entityNs . "\\";

        foreach ($u as $u) $this->entityClass .= ucfirst($u); //TODO: Ugly

        if ($this->entityIsNew)
        {
            $this->entity = new $this->entityClass;
        }
        else if (!empty($this->get->__1__))
        {
            // Use this to manage composite primary keys
            $id = !empty($this->entityIdentifier)
                ? $this->entityIdentifier
                : $this->get->__1__;

            $this->entity = EM()->find($this->entityClass, $id);

            $this->entity || patchwork::forbidden();
        }

        else if ($this instanceof agent_pForm_entity_indexable)
        {
            $this->template = $this->entityUrl . '/index';
        }
        else patchwork::forbidden();
    }

    function compose($o)
    {
        if (empty($this->entity))
        {
            return $this->composeIndex($o);
        }
        else
        {
            if (!$this->entityIsNew)
            {
                $this->data += $this->getEntityData();
                foreach ($this->data as $k => $v) $o->$k = $v;
            }

            if ($this instanceof agent_pForm_entity_indexable)
            {
                $o = $this->composeEntity($o);
            }

            return parent::compose($o);
        }
    }

    protected function save($data)
    {
        $this->setEntityData($data);
        $this->entityIsNew && EM()->persist($this->entity);
        EM()->flush();

        $id = $this->getEntityMetadata($this->entityClass);
        $id = $id->getSingleIdentifierFieldName();
        $id = 'get' . Doctrine\Common\Util\Inflector::classify($id);

        return $this->entityUrl . '/' . $this->entity->$id();
    }

    /**
     * Return the ClassMetadata of an Entity
     *
     * @return ClassMetadata
     */
    protected function getEntityMetadata($entityClass)
    {
        return EM()->getClassMetadata($entityClass);
    }

    /**
     * Return an array of the entity's values
     *
     * @return object $data
     */
    protected function getEntityData($entity = null, $stringifyDates = true)
    {
        $data = array ();

        $entity || $entity = $this->entity;

        $p = $this->getEntityMetadata(get_class($entity))->getColumnNames();

        foreach ($p as $p)
        {
            $getProp = 'get' . Doctrine\Common\Util\Inflector::classify($p);

            if (method_exists($entity, $getProp))
            {
                $data[$p] = $entity->$getProp();

                if ($stringifyDates && $data[$p] instanceof DateTime)
                {
                    $data[$p . '_timestamp'] = $data[$p]->format('U');
                    $data[$p] = $data[$p]->format('c');
                }
            }
        }

        return $data;
    }

    /**
     * Inject data with entity's setters
     *
     * @param array $data
     */
    protected function setEntityData($data, $entity = null)
    {
        if (!$entity) $entity = $this->entity;
        $meta = $this->getEntityMetadata($this->entityClass);
        $id = $meta->getIdentifierFieldNames();

        foreach ($data as $f => $v)
        {
            $repoSetter = 'setEntity' . Doctrine\Common\Util\Inflector::classify($f);
            if (method_exists($meta->customRepositoryClassName, $repoSetter))
            {
                $repo = EM()->getRepository($meta->name);
                $repo->$repoSetter($entity, $v);
            }
            else if (in_array($f, $meta->fieldNames) && !in_array($f, $id))
            {
                $setter = 'set' . Doctrine\Common\Util\Inflector::classify($f);
                $entity->$setter($v);
            }
            else if (isset($meta->associationMappings[$f]))
            {
                $v || $v = null;

                $setter = 'set' . Doctrine\Common\Util\Inflector::classify($f);
                $entity->$setter($v);
            }
        }

        return $entity;
    }

    protected function getRepository()
    {
        return EM()->getRepository($this->entityClass);
    }

    /**
     * Load an entity in the agent
     *
     * @param object $o
     * @param object $entity
     * @param string $prefix The prefix of the entity
     * @return $o
     */
    public function loadEntity($o, $entity, $prefix)
    {
        if ($entity)
        {
            $meta = $this->getEntityMetadata(get_class($entity));

            $data = $this->getEntityData($entity);

            foreach ($data as $k => $v)
            {
                if (0 === strpos($k, $prefix . '_'))
                {
                    $k = substr($k, strlen($prefix) + 1);
                }

                $o->{"{$prefix}_{$k}"} = $v;
            }

            if (!$meta->isIdentifierComposite)
            {
                $o->{$prefix} = $this->data[$prefix] = $data[$meta->getSingleIdentifierColumnName()];
            }
        }

        return $o;
    }

    /**
     * Load a collection loop in the agent
     *
     * @param object $o
     * @param object $entity
     * @param string $collection
     * @param array  $params
     * @return object $o
     */
    public function loadCollectionLoop($o, $entity, $collection)
    {
        $data = array ();

        $filter = 'filterPersistentCollection';

        if ($entity)
        {
            $meta = $this->getEntityMetadata(get_class($entity));

            $params = func_get_args();

            unset($params[0], $params[2]);

            $repoGetColl = 'getEntity' . Doctrine\Common\Util\Inflector::classify($collection);
            $getColl = 'get' . Doctrine\Common\Util\Inflector::classify($collection);

            if (method_exists($meta->customRepositoryClassName, $repoGetColl))
            {
                $repo = EM()->getRepository($meta->name);
                $coll = call_user_func_array(array ($repo, $repoGetColl), $params);
                $data = $coll->toArray();
            }
            else if (method_exists($entity, $getColl))
            {
                $coll = call_user_func_array(array ($entity, $getColl), $params);
                $data = $coll->toArray();
            }
            else
            {
                throw new \InvalidArgumentException("The getter : {$getColl} does not exists in {$meta->name} or {$meta->customRepositoryClassName}");
            }

            if ($coll instanceof \Doctrine\Common\Collections\ArrayCollection)
            {
                $filter = 'filterArrayCollection';
            }
            else if ($coll instanceof \Doctrine\ORM\PersistentCollection)
            {
                $filter = 'filterPersistentCollection';
            }
        }

        $o->{$collection} = new loop_array($data, array ($this, $filter));

        return $o;
    }

    function getQueryLoop(Doctrine\ORM\Query $query)
    {
        return new loop_array($query->getArrayResult(), 'filter_rawArray');
    }

    function filterPersistentCollection($o)
    {
        if (is_object($o->VALUE))
            $o = (object)$this->getEntityData($o->VALUE);

        return $o;
    }

    function filterArrayCollection($o)
    {
        $o = $o->VALUE;

        if (isset($o[0]))
        {
            foreach ($o[0] as $k => $v) $o[$k] = $v;
            unset($o[0]);
        }

        foreach ($o as $key => $value)
        {
            if (is_array($value))
            {
                foreach ($value as $k => $v)
                {
                    if (0 === strpos($k, $key . '_'))
                    {
                        $k = substr($k, strlen($key) + 1);
                    }

                    $o[$key . '_' . $k] = $v;
                    unset($o[$key]);
                }
            }
        }

        $o = (object)$o;

        return $this->filterDateTime($o);
    }

    function filterDateTime($o)
    {
        foreach ($o as $k => &$v)
        {
            if ($v instanceof DateTime)
            {
                $k .= '_timestamp';
                $o->$k = $v->format('U');
                $v = $v->format('c');
            }
            else if (is_array($v))
            {
                $v = $this->filterDateTime($v);
            }
        }

        return $o;
    }
}

interface agent_pForm_entity_indexable
{
    function composeIndex($o);

    function composeEntity($o);
}
