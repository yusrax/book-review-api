<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OpenLibraryService
{
    private const BASE_URL = 'https://openlibrary.org';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 10.0,
            'headers' => [
                'User-Agent' => 'BookReviewAPI/1.0',
                'Accept' => 'application/json'
            ]
        ]);
    }

    private function makeRequest(string $url, int $maxRetries = 3): ?string
    {
        $retryCount = 0;
        $lastError = null;

        while ($retryCount < $maxRetries) {
            try {
                $response = $this->client->get($url, [
                    'timeout' => 5.0 // Reduce timeout to 5 seconds
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                $lastError = $e;
                $retryCount++;
                
                if ($e->getCode() === 503) {
                    // Wait before retrying (exponential backoff)
                    sleep(pow(2, $retryCount));
                    continue;
                }
                
                throw $e;
            }
        }

        throw $lastError;
    }

    public function fetchBookByKey(string $key): ?array
    {
        try {
            $response = $this->client->get("/api/books", [
                'query' => [
                    'bibkeys' => "OLID:{$key}",
                    'format' => 'json',
                    'jscmd' => 'data'
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (empty($data)) {
                return null;
            }

            $bookData = $data["OLID:{$key}"];
            
            return [
                'googleBookId' => $key,
                'title' => $bookData['title'] ?? '',
                'authors' => array_map(fn($author) => $author['name'], $bookData['authors'] ?? []),
                'description' => $bookData['description'] ?? '',
                'pageCount' => $bookData['number_of_pages'] ?? 0,
                'categories' => $bookData['subjects'] ?? [],
                'thumbnail' => $bookData['cover']['large'] ?? $bookData['cover']['medium'] ?? $bookData['cover']['small'] ?? null,
                'averageRating' => $bookData['ratings']['average'] ?? 0,
            ];
        } catch (GuzzleException $e) {
            return null;
        }
    }

    private function normalizeWork(array $work): array
    {
        $description = $work['description'] ?? null;
        if (is_array($description) && isset($description['value'])) {
            $description = $description['value'];
        }

        $coverId = $work['cover_id'] ?? $work['covers'][0] ?? null;
        $coverUrl = null;
        if ($coverId) {
            $coverUrl = "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg";
        }

        return [
            'title' => $work['title'] ?? 'Unknown Title',
            'key' => $work['key'] ?? null,
            'firstPublished' => $work['first_publish_date'] ?? null,
            'subjects' => $work['subjects'] ?? [],
            'description' => $description,
            'coverId' => $coverId,
            'coverUrl' => $coverUrl
        ];
    }

    public function searchAuthor(string $authorName): ?array
    {
        try {
            $searchUrl = "/search/authors.json?q=" . urlencode($authorName);
            $responseBody = $this->makeRequest($searchUrl);
            $data = json_decode($responseBody, true);
            
            if (empty($data['docs'])) {
                return null;
            }

            $author = $data['docs'][0];
            $authorKey = $author['key'];

            $authorUrl = "/authors/" . $authorKey;
            $authorResponseBody = $this->makeRequest($authorUrl);
            $authorDetails = json_decode($authorResponseBody, true);

            $worksUrl = "/authors/" . $authorKey . "/works.json";
            $worksResponseBody = $this->makeRequest($worksUrl);
            $worksData = json_decode($worksResponseBody, true);

            $works = [];
            $maxWorks = 10; // Limit to first 10 works to prevent timeout
            
            foreach (array_slice($worksData['entries'] ?? [], 0, $maxWorks) as $work) {
                $workKey = $work['key'];
                $workUrl = $workKey . ".json";
                
                try {
                    $workResponseBody = $this->makeRequest($workUrl);
                    $workDetails = json_decode($workResponseBody, true);
                    $works[] = $this->normalizeWork($workDetails);
                } catch (GuzzleException $e) {
                    // If we can't get detailed work info, use the basic info
                    $works[] = $this->normalizeWork($work);
                }
            }

            $photo = null;
            if (isset($authorDetails['photos']) && !empty($authorDetails['photos'])) {
                $photo = "https://covers.openlibrary.org/a/id/{$authorDetails['photos'][0]}-L.jpg";
            }

            $bio = $authorDetails['bio'] ?? null;
            if (is_array($bio) && isset($bio['value'])) {
                $bio = $bio['value'];
            }

            return [
                'name' => $authorDetails['name'] ?? $authorName,
                'key' => $authorKey,
                'birthDate' => $authorDetails['birth_date'] ?? null,
                'deathDate' => $authorDetails['death_date'] ?? null,
                'bio' => $bio,
                'photo' => $photo,
                'works' => $works,
                'topSubjects' => $author['top_subjects'] ?? [],
                'workCount' => $author['work_count'] ?? 0,
                'ratingsAverage' => $author['ratings_average'] ?? null,
                'ratingsCount' => $author['ratings_count'] ?? 0
            ];
        } catch (GuzzleException $e) {
            return null;
        }
    }
} 