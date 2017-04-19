<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Test\DataFixtures\AbstractFixture;

class LoadSegmentsWithRelationsData extends AbstractFixture
{
    const FIRST_SEGMENT = 'firstSegment';
    const SECOND_SEGMENT = 'secondSegment';
    const THIRD_SEGMENT = 'thirdSegment';
    const NO_RELATIONS_SEGMENT = 'noRelationsSegment';

    private $definitions = [
        'withoutRelations' => [
            'filters' => [
                ['columnName' => 'column'],
            ]
        ],
        'withRelations' => [
            'filters' => [
                ['columnName' => 'column+SomeClass::id'],
            ]
        ]
    ];

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        foreach ([self::FIRST_SEGMENT, self::SECOND_SEGMENT, self::THIRD_SEGMENT] as $segmentName) {
            $segment = $this->createSegment($manager, $segmentName);
            $this->setReference($segmentName, $segment);
            $manager->persist($segment);
        }

        $segment = $this->createSegment($manager, self::NO_RELATIONS_SEGMENT, false);
        $this->setReference(self::NO_RELATIONS_SEGMENT, $segment);
        $manager->persist($segment);

        $manager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @param string $name
     * @param bool $hasRelations
     * @return Segment
     */
    private function createSegment(ObjectManager $manager, $name, $hasRelations = true)
    {
        $segment = new Segment();
        $segmentType = $manager->getRepository(SegmentType::class)->find(SegmentType::TYPE_DYNAMIC);

        $segment
            ->setName($name)
            ->setType($segmentType)
            ->setEntity(WorkflowAwareEntity::class);

        $segment->setDefinition(json_encode($this->definitions[$hasRelations ? 'withRelations' : 'withoutRelations']));

        return $segment;
    }
}
