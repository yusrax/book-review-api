<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GoogleBooksService
{
    private const GOOGLE_BOOKS_API_URL = 'https://www.googleapis.com/books/v1/volumes';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(); // You could also inject this if preferred
    }

    public function searchBooks(string $query, int $page = 1, int $limit = 20): array
    {
        try {
            $startIndex = ($page - 1) * $limit;
            $response = $this->client->request('GET', self::GOOGLE_BOOKS_API_URL, [
                'query' => [
                    'q' => $query,
                    'maxResults' => $limit,
                    'startIndex' => $startIndex
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return [
                'items' => $this->normalizeResults($data),
                'totalItems' => $data['totalItems'] ?? 0
            ];
        } catch (GuzzleException $e) {
            return [
                'items' => [],
                'totalItems' => 0
            ];
        }
    }

    public function fetchBookById(string $googleBookId): ?array
    {
        try {
            $response = $this->client->request('GET', self::GOOGLE_BOOKS_API_URL . '/' . $googleBookId);
            $data = json_decode($response->getBody()->getContents(), true);
            return $this->normalizeBook($data);
        } catch (GuzzleException $e) {
            return null;
        }
    }

    private function normalizeResults(array $data): array
    {
        if (!isset($data['items'])) {
            return [];
        }

        $normalizedBooks = [];
        $seenIds = [];

        foreach ($data['items'] as $item) {
            $book = $this->normalizeBook($item);
            $bookId = $book['googleBookId'];

            // Skip if we've already seen this book ID
            if ($bookId && !isset($seenIds[$bookId])) {
                $seenIds[$bookId] = true;
                $normalizedBooks[] = $book;
            }
        }

        return $normalizedBooks;
    }

    private function cleanCategories(array $categories): array
    {
        // Split categories by slash and flatten the array
        $allCategories = [];
        foreach ($categories as $category) {
            $parts = array_map('trim', explode('/', $category));
            $allCategories = array_merge($allCategories, $parts);
        }

        // Remove duplicates and empty values
        $uniqueCategories = array_filter(array_unique($allCategories));

        // Sort alphabetically
        sort($uniqueCategories);

        return array_values($uniqueCategories);
    }

    private function normalizeBook(array $item): array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];

        return [
            'googleBookId' => $item['id'] ?? null,
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'authors' => $volumeInfo['authors'] ?? [],
            'thumbnail' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
            'description' => isset($volumeInfo['description'])
                ? strip_tags($volumeInfo['description'])
                : null,
            'pageCount' => $volumeInfo['pageCount'] ?? null,
            'categories' => $this->cleanCategories($volumeInfo['categories'] ?? []),
            'averageRating' => $volumeInfo['averageRating'] ?? null,
        ];
    }
}

