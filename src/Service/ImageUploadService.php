<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Exception;

class ImageUploadService
{
    private string $uploadsPath;

    public function __construct(string $uploadsPath)
    {
        $this->uploadsPath = $uploadsPath;
    }

    /**
     * Upload an image and return its relative path
     *
     * @param UploadedFile $file
     * @param string $directory e.g., 'profile_pictures' or 'book_covers'
     * @return string relative file path e.g., /uploads/profile_pictures/filename.jpg
     * @throws Exception if upload fails
     */
    public function uploadImage(UploadedFile $file, string $directory): string
    {
        // You can add more validation here if needed (file size, mime type)

        // Generate a unique filename
        $filename = uniqid() . '.' . $file->guessExtension();

        // Define the target directory (inside public/uploads)
        $targetDirectory = $this->uploadsPath . '/' . $directory;

        // Make sure the directory exists
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        try {
            // Move the file to the target directory
            $file->move($targetDirectory, $filename);
        } catch (FileException $e) {
            throw new Exception('File upload failed: ' . $e->getMessage());
        }

        // Return the public path (relative URL)
        return '/uploads/' . $directory . '/' . $filename;
    }
}

