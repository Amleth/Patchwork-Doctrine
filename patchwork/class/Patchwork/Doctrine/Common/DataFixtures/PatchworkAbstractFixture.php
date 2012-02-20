<?php

namespace Patchwork\Doctrine\Common\DataFixtures;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\DataFixtures\AbstractFixture;

abstract class PatchworkAbstractFixture extends AbstractFixture
{
    /* @var $em \Doctrine\ORM\EntityManager */
    protected $em;

    /* @var array $relationships */
    private $relationshipsRepository;

    public function setRelationshipsRepository($it)
    {
        $this->relationshipsRepository = $it;
    }

    /* @var array $persist_queue */
    private $persist_queue;

    public function setPersistQueue($it)
    {
        $this->persist_queue = $it;
    }

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function census($key, $it)
    {
        array_unshift($this->persist_queue, $it);
        $this->addReference($key, $it);
    }

    public function addRelationship($entity, $field, $reference)
    {
        $this->relationshipsRepository[] = array(
            'entity' => $entity,
            'field' => $field,
            'ref' => $reference,
        );
    }

    public function setFKC($check)
    {
        if (!is_bool($check))
        {
            throw new \Exception('Boolean expected');
        }

        $this->em->getConnection()->exec('SET FOREIGN_KEY_CHECKS=' . (int) $check);
    }

    public function persistAll()
    {
        $cmf = $this->em->getMetadataFactory();

        while ($this->persist_queue)
        {
            $entity = array_pop($this->persist_queue);

            foreach ($this->relationshipsRepository as $r)
            {
                if ($r['ref'] && $this->getReference($r['ref']))
                {
                    $class = $cmf->getMetadataFor(get_class($r['entity']));
                    $class->setFieldValue($r['entity'], $r['field'], $this->getReference($r['ref']));
                }
            }

            $this->em->persist($entity);
        }
    }
}
