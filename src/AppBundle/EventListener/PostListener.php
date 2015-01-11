<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Post;
use AppBundle\Entity\PostStatus;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

class PostListener
{
    public function onFlush(OnFlushEventArgs $event)
    {
        $entityManager = $event->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Post) {
                if (!$entity->getPostStatus()) {
                    $entity->setPostStatus(
                        $entityManager->getReference('AppBundle:PostStatus', PostStatus::DRAFT)
                    );
                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $entityManager->getClassMetadata('AppBundle:Post'),
                        $entity
                    );
                }
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Post) {
                $changeSet = $unitOfWork->getEntityChangeSet($entity);
                if (isset($changeSet['postStatus'])) {
                    if (!$entity->getPostStatus()) {
                        $entity->setPostStatus(
                            $entityManager->getReference('AppBundle:PostStatus', PostStatus::DRAFT)
                        );
                    }

                    if ($entity->getDeletedAt() && $entity->getPostStatus()->getId() !== PostStatus::DELETED) {
                        $entity->setDeletedAt(null);
                    }

                    $unitOfWork->recomputeSingleEntityChangeSet(
                        $entityManager->getClassMetadata('AppBundle:Post'),
                        $entity
                    );
                }
            }
        }
    }

    public function preSoftDelete(LifecycleEventArgs $event)
    {
        $entityManager = $event->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $entity = $event->getEntity();
        if ($entity instanceof Post) {
            $currentStatus = $entity->getPostStatus();
            $newStatus = $entityManager->getReference('AppBundle:PostStatus', PostStatus::DELETED);
            $unitOfWork->propertyChanged($entity, 'postStatus', $currentStatus, $newStatus);
            $unitOfWork->scheduleExtraUpdate($entity, array(
                'postStatus' => array($currentStatus, $newStatus),
            ));
        }
    }
}
