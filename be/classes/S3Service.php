<?php
/**
 * S3-Compatible Storage Service class
 * Supports AWS S3, MinIO, DigitalOcean Spaces, Wasabi, Backblaze B2, and other S3-compatible services
 * Uses local AWS SDK from lib/aws/ directory
 */
class S3Service {
    private $s3Client;
    private $bucket;
    private $endpoint;
    private $usePathStyle;

    public function __construct() {
        // Load local AWS SDK autoloader
        $awsAutoloader = __DIR__ . '/../lib/aws/aws-autoloader.php';
        if (!file_exists($awsAutoloader)) {
            throw new Exception('AWS SDK not found. Please ensure lib/aws/aws-autoloader.php exists');
        }
        
        // Only require once to avoid multiple autoloader registrations
        if (!class_exists('Aws\S3\S3Client')) {
            require_once $awsAutoloader;
        }
        
        // Verify AWS SDK is loaded
        if (!class_exists('Aws\S3\S3Client')) {
            throw new Exception('Failed to load AWS SDK. Please check lib/aws/ directory');
        }

        $this->bucket = AWS_S3_BUCKET;
        
        // Get custom endpoint if configured (for S3-compatible services)
        $this->endpoint = defined('S3_ENDPOINT') && !empty(S3_ENDPOINT) ? S3_ENDPOINT : null;
        $this->usePathStyle = defined('S3_USE_PATH_STYLE') ? S3_USE_PATH_STYLE : false;
        
        // Build S3 client configuration
        $config = [
            'version' => 'latest',
            'region' => AWS_REGION,
            'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY,
            ],
        ];
        
        // Add custom endpoint for S3-compatible services
        if ($this->endpoint) {
            $config['endpoint'] = $this->endpoint;
            // Disable SSL verification for local/development endpoints if needed
            if (strpos($this->endpoint, 'http://') === 0) {
                $config['http'] = [
                    'verify' => false,
                ];
            }
        }
        
        // Use path-style URLs if configured (required for some S3-compatible services)
        if ($this->usePathStyle) {
            $config['use_path_style_endpoint'] = true;
        }
        
        // Initialize S3 client
        $this->s3Client = new Aws\S3\S3Client($config);
    }

    /**
     * Upload a file to S3
     * 
     * @param string $key The S3 object key (path)
     * @param string $filePath Local file path or file content
     * @param string $contentType MIME type of the file
     * @param bool $isContent If true, $filePath is treated as content
     * @return array Result with success status and URL
     */
    public function uploadFile($key, $filePath, $contentType = 'application/octet-stream', $isContent = false) {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'ContentType' => $contentType,
            ];

            if ($isContent) {
                $params['Body'] = $filePath;
            } else {
                $params['SourceFile'] = $filePath;
            }

            $result = $this->s3Client->putObject($params);

            // Build URL based on endpoint configuration
            $url = $this->buildObjectUrl($key);

            return [
                'success' => true,
                'url' => $url,
                'key' => $key,
                'etag' => $result['ETag'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Download a file from S3
     * 
     * @param string $key The S3 object key
     * @param string $savePath Local path to save the file
     * @return array Result with success status
     */
    public function downloadFile($key, $savePath) {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SaveAs' => $savePath,
            ]);

            return [
                'success' => true,
                'contentType' => $result['ContentType'],
                'size' => $result['ContentLength'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a presigned URL for temporary access
     * 
     * @param string $key The S3 object key
     * @param int $expiration Expiration time in seconds (default 1 hour)
     * @return string Presigned URL
     */
    public function getPresignedUrl($key, $expiration = 3600) {
        try {
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $request = $this->s3Client->createPresignedRequest($cmd, '+' . $expiration . ' seconds');
            return (string) $request->getUri();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Delete a file from S3
     * 
     * @param string $key The S3 object key
     * @return array Result with success status
     */
    public function deleteFile($key) {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if a file exists in S3
     * 
     * @param string $key The S3 object key
     * @return bool True if file exists
     */
    public function fileExists($key) {
        try {
            return $this->s3Client->doesObjectExist($this->bucket, $key);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * List files in S3 with optional prefix
     * 
     * @param string $prefix Optional prefix to filter files
     * @return array List of file keys
     */
    public function listFiles($prefix = '') {
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $files[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'lastModified' => $object['LastModified'],
                    ];
                }
            }

            return $files;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Build object URL based on endpoint configuration
     * 
     * @param string $key The S3 object key
     * @return string Object URL
     */
    private function buildObjectUrl($key) {
        if ($this->endpoint) {
            // Custom endpoint (S3-compatible service)
            $endpoint = rtrim($this->endpoint, '/');
            if ($this->usePathStyle) {
                // Path-style: http://endpoint/bucket/key
                return $endpoint . '/' . $this->bucket . '/' . $key;
            } else {
                // Virtual-hosted-style: http://bucket.endpoint/key
                $host = parse_url($endpoint, PHP_URL_HOST);
                $scheme = parse_url($endpoint, PHP_URL_SCHEME);
                $port = parse_url($endpoint, PHP_URL_PORT);
                $portStr = $port ? ':' . $port : '';
                return $scheme . '://' . $this->bucket . '.' . $host . $portStr . '/' . $key;
            }
        } else {
            // AWS S3 default URL format
            return sprintf(
                'https://%s.s3.%s.amazonaws.com/%s',
                $this->bucket,
                AWS_REGION,
                $key
            );
        }
    }
}
