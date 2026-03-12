<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\UploadedFileInterface;
use Exception;

/**
 * UploadService
 * 
 * Centralized service for handling all file uploads (images, documents, videos)
 * Provides validation, storage, and cleanup functionality
 */
class UploadService
{
    /**
     * Upload base directory
     */
    private string $uploadBaseDir;

    /**
     * Allowed file types and their configurations
     */
    private array $fileTypes = [
        'image' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'directory' => 'images',
        ],
        'banner' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'directory' => 'banners',
        ],
        'document' => [
            'mimes' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'extensions' => ['pdf', 'doc', 'docx'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'directory' => 'documents',
        ],
        'video' => [
            'mimes' => ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo'],
            'extensions' => ['mp4', 'mpeg', 'mov', 'avi'],
            'max_size' => 100 * 1024 * 1024, // 100MB
            'directory' => 'videos',
        ],
        'avatar' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 2 * 1024 * 1024, // 2MB
            'directory' => 'avatars',
        ],
        'nominee' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 2 * 1024 * 1024, // 2MB
            'directory' => 'nominees',
        ],
        'ticket' => [
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 2 * 1024 * 1024, // 2MB
            'directory' => 'tickets',
        ],
    ];

    public function __construct()
    {
        $this->uploadBaseDir = dirname(__DIR__, 2) . '/public/uploads';
    }

    /**
     * Upload a single file
     * 
     * @param UploadedFileInterface $file
     * @param string $type Type of file (image, banner, document, video, avatar)
     * @param string|null $subDirectory Optional subdirectory (e.g., 'events', 'awards')
     * @return string Full URL to uploaded file
     * @throws Exception
     */
    public function uploadFile(
        UploadedFileInterface $file,
        string $type = 'image',
        ?string $subDirectory = null
    ): string {
        // Check for upload errors
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file->getError()));
        }

        // Validate file type
        if (!isset($this->fileTypes[$type])) {
            throw new Exception("Invalid file type: {$type}");
        }

        $config = $this->fileTypes[$type];

        // Validate MIME type
        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, $config['mimes'])) {
            throw new Exception("Invalid file format. Allowed: " . implode(', ', $config['extensions']));
        }

        // Validate file size
        if ($file->getSize() > $config['max_size']) {
            $maxSizeMB = $config['max_size'] / (1024 * 1024);
            throw new Exception("File size must be less than {$maxSizeMB}MB");
        }

        // Create upload directory
        $uploadDir = $this->uploadBaseDir . '/' . $config['directory'];
        if ($subDirectory) {
            $uploadDir .= '/' . $subDirectory;
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }

        // Generate unique filename
        $extension = $this->getFileExtension($file->getClientFilename());
        if (!in_array($extension, $config['extensions'])) {
            throw new Exception("Invalid file extension. Allowed: " . implode(', ', $config['extensions']));
        }

        $filename = $this->generateUniqueFilename($type, $extension);
        $filepath = $uploadDir . '/' . $filename;

        // Move uploaded file
        try {
            $file->moveTo($filepath);
        } catch (Exception $e) {
            throw new Exception("Failed to save file: " . $e->getMessage());
        }

        // Build relative path
        $relativePath = '/uploads/' . $config['directory'];
        if ($subDirectory) {
            $relativePath .= '/' . $subDirectory;
        }
        $relativePath .= '/' . $filename;

        // Get base URL from environment
        $baseUrl = rtrim($_ENV['APP_URL'] ?: 'http://localhost:8000', '/');
        
        // Return full URL
        return $baseUrl . $relativePath;
    }

    /**
     * Upload multiple files
     * 
     * @param array $files Array of UploadedFileInterface
     * @param string $type
     * @param string|null $subDirectory
     * @return array Array of relative paths
     */
    public function uploadMultipleFiles(
        array $files,
        string $type = 'image',
        ?string $subDirectory = null
    ): array {
        $uploadedPaths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFileInterface && $file->getError() === UPLOAD_ERR_OK) {
                try {
                    $uploadedPaths[] = $this->uploadFile($file, $type, $subDirectory);
                } catch (Exception $e) {
                    // Log error but continue with other files
                    error_log("File upload failed: " . $e->getMessage());
                }
            }
        }

        return $uploadedPaths;
    }

    /**
     * Delete a file
     * 
     * @param string $fileUrl Full URL or relative path to file
     * @return bool
     */
    public function deleteFile(string $fileUrl): bool
    {
        // Extract relative path from full URL if needed
        $relativePath = $this->extractRelativePath($fileUrl);
        
        $fullPath = dirname(__DIR__, 2) . '/public' . $relativePath;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return true;
    }

    /**
     * Extract relative path from full URL or return as-is if already relative
     * 
     * @param string $fileUrl
     * @return string
     */
    private function extractRelativePath(string $fileUrl): string
    {
        // If it's a full URL, extract the path
        if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($fileUrl);
            return $parsed['path'] ?? $fileUrl;
        }
        
        // Already a relative path
        return $fileUrl;
    }

    /**
     * Delete multiple files
     * 
     * @param array $relativePaths
     * @return int Number of files deleted
     */
    public function deleteMultipleFiles(array $relativePaths): int
    {
        $deletedCount = 0;

        foreach ($relativePaths as $path) {
            if ($this->deleteFile($path)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Replace an old file with a new one
     * 
     * @param UploadedFileInterface $newFile
     * @param string|null $oldFilePath
     * @param string $type
     * @param string|null $subDirectory
     * @return string New file path
     */
    public function replaceFile(
        UploadedFileInterface $newFile,
        ?string $oldFilePath,
        string $type = 'image',
        ?string $subDirectory = null
    ): string {
        // Upload new file
        $newFilePath = $this->uploadFile($newFile, $type, $subDirectory);

        // Delete old file if it exists
        if ($oldFilePath) {
            $this->deleteFile($oldFilePath);
        }

        return $newFilePath;
    }

    /**
     * Validate file without uploading
     * 
     * @param UploadedFileInterface $file
     * @param string $type
     * @return bool
     * @throws Exception
     */
    public function validateFile(UploadedFileInterface $file, string $type = 'image'): bool
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file->getError()));
        }

        if (!isset($this->fileTypes[$type])) {
            throw new Exception("Invalid file type: {$type}");
        }

        $config = $this->fileTypes[$type];

        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, $config['mimes'])) {
            throw new Exception("Invalid file format. Allowed: " . implode(', ', $config['extensions']));
        }

        if ($file->getSize() > $config['max_size']) {
            $maxSizeMB = $config['max_size'] / (1024 * 1024);
            throw new Exception("File size must be less than {$maxSizeMB}MB");
        }

        return true;
    }

    /**
     * Get file extension from filename
     */
    private function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Generate unique filename
     */
    private function generateUniqueFilename(string $type, string $extension): string
    {
        return $type . '_' . uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Get allowed file types for a specific type
     */
    public function getAllowedTypes(string $type): array
    {
        return $this->fileTypes[$type] ?? [];
    }

    /**
     * Get maximum file size for a type
     */
    public function getMaxFileSize(string $type): int
    {
        return $this->fileTypes[$type]['max_size'] ?? 0;
    }

    /**
     * Get maximum file size in MB
     */
    public function getMaxFileSizeMB(string $type): float
    {
        $bytes = $this->getMaxFileSize($type);
        return $bytes / (1024 * 1024);
    }
}
