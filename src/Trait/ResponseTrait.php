<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

trait ResponseTrait
{
    /**
     * Create a JSON response with serialized data
     * 
     * @param mixed $data Data to serialize
     * @param SerializerInterface $serializer
     * @param array $groups Serialization groups
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function jsonResponse(
        $data,
        SerializerInterface $serializer,
        array $groups = [],
        int $status = 200
    ): JsonResponse {
        $json = $serializer->serialize($data, 'json', [
            'groups' => $groups,
            'circular_reference_handler' => fn($object) => $object->getId(),
        ]);
        
        return new JsonResponse(json_decode($json), $status);
    }
    
    /**
     * Create a paginated JSON response
     * 
     * @param array $data Paginated data
     * @param int $total Total number of items
     * @param int $page Current page
     * @param int $limit Items per page
     * @return JsonResponse
     */
    protected function paginatedResponse(
        array $data,
        int $total,
        int $page,
        int $limit
    ): JsonResponse {
        $totalPages = ceil($total / $limit);
        
        return new JsonResponse([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ], 200);
    }
    
    /**
     * Create an error response
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $errors Additional error details
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $response = ['message' => $message];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return new JsonResponse($response, $status);
    }
    
    /**
     * Create a success response
     * 
     * @param string $message Success message
     * @param array $data Additional data
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse(
        string $message,
        array $data = [],
        int $status = 200
    ): JsonResponse {
        $response = ['message' => $message];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        return new JsonResponse($response, $status);
    }
} 