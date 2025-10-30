<?php

namespace App\Normalizer;

use App\Entity\Review;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ReviewNormalizer implements NormalizerInterface
{
    private ObjectNormalizer $normalizer;
    private Security $security;

    public function __construct( ObjectNormalizer $normalizer, Security $security)
    {
        $this->normalizer = $normalizer;
        $this->security = $security;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Review;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if ($object instanceof Review) {
            $user = $this->security->getUser();
            $data['likedByCurrentUser'] = $object->isLikedByUser($user);
            $data['totalLikes'] = $object->getLikesCount();
        }

        return $data;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Review::class => true,
        ];
    }
}
