<?php

namespace App\Repository;

use App\Entity\ReviewLike;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewLike>
 */
class ReviewLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewLike::class);
    }
    // Optional: Add custom methods here if needed
}
