<?php
/**
 * Gallery API endpoint - Returns all gallery entries with presigned S3 URLs
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/S3Service.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Router.php';

$router = new Router();
$router->setBasePath('/be/galleries');

// Get all gallery items with presigned URLs (public API)
$router->get('/', function() {
    $db = Database::getInstance()->getConnection();
    
    // Fetch all galleries (public access)
    $stmt = $db->prepare("
        SELECT galleries.id, users.email, galleries.s3_key, galleries.category, galleries.title, galleries.created_at
        FROM galleries
        JOIN users ON galleries.user_id = users.id
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $galleries = $stmt->fetchAll();

    // Generate presigned URLs for each gallery item
    $s3 = new S3Service();
    foreach ($galleries as &$gallery) {
        if (!empty($gallery['s3_key'])) {
            $gallery['presigned_url'] = $s3->getPresignedUrl($gallery['s3_key'], 3600); // 1 hour expiry
        } else {
            $gallery['presigned_url'] = null;
        }
    }
    unset($gallery); // Unset reference

    Response::success([
        'galleries' => $galleries,
        'count' => count($galleries),
    ]);
});

$router->dispatch();
