<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class EntityService
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    
    /**
     * Find an entity by ID or return null
     * 
     * @param string $entityClass Fully qualified entity class name
     * @param int $id Entity ID
     * @return object|null
     */
    public function findEntity(string $entityClass, int $id): ?object
    {
        return $this->entityManager->getRepository($entityClass)->find($id);
    }
    
    /**
     * Find an entity by criteria or return null
     * 
     * @param string $entityClass Fully qualified entity class name
     * @param array $criteria Search criteria
     * @return object|null
     */
    public function findOneBy(string $entityClass, array $criteria): ?object
    {
        return $this->entityManager->getRepository($entityClass)->findOneBy($criteria);
    }
    
    /**
     * Save an entity to the database
     * 
     * @param object $entity Entity to save
     * @return void
     */
    public function saveEntity(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
    
    /**
     * Remove an entity from the database
     * 
     * @param object $entity Entity to remove
     * @return void
     */
    public function removeEntity(object $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
    
    /**
     * Get form validation errors
     * 
     * @param FormInterface $form Form to validate
     * @return array Array of error messages
     */
    public function getFormErrors(FormInterface $form): array
    {
        $errors = [];
        
        foreach ($form->getErrors(true) as $error) {
            $errors[] = [
                'field' => $error->getOrigin()->getName(),
                'message' => $error->getMessage(),
                'invalid_value' => $error->getOrigin()->getData(),
            ];
        }
        
        return $errors;
    }
    
    /**
     * Get paginated results
     * 
     * @param string $entityClass Fully qualified entity class name
     * @param array $criteria Search criteria
     * @param array $orderBy Order criteria
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Array with 'items' and 'total' keys
     */
    public function getPaginatedResults(
        string $entityClass,
        array $criteria = [],
        array $orderBy = [],
        int $page = 1,
        int $limit = 20
    ): array {
        $offset = ($page - 1) * $limit;
        
        $items = $this->entityManager->getRepository($entityClass)->findBy(
            $criteria,
            $orderBy,
            $limit,
            $offset
        );
        
        $total = $this->entityManager->getRepository($entityClass)->count($criteria);
        
        return [
            'items' => $items,
            'total' => $total
        ];
    }
} 