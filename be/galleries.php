<?php
/**
 * Gallery CRUD Page - Franken UI Components
 */

// Load required classes
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/S3Service.php';

session_start();

// Check authentication
if (!isset($_SESSION['access_token'])) {
    header('Location: login.php');
    exit;
}

// Validate token and get user info
$auth = new Auth();
$tokenData = $auth->validateToken($_SESSION['access_token']);
if (!$tokenData) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userId = $tokenData['user_id'];
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$galleryId = $_GET['id'] ?? null;

// Handle logout action (must be before any output)
if ($action === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'list';
    
    if ($action === 'create') {
        // Create new gallery item
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($title) || empty($category)) {
            $error = 'Title and category are required';
        } elseif (empty($_FILES['file']['tmp_name'])) {
            $error = 'File is required';
        } else {
            try {
                $file = $_FILES['file'];
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $key = 'galleries/' . $userId . '/' . uniqid() . '.' . $extension;
                
                $s3 = new S3Service();
                $result = $s3->uploadFile($key, $file['tmp_name'], $file['type'], false);
                
                if ($result['success']) {
                    $stmt = $db->prepare("
                        INSERT INTO galleries (user_id, s3_key, category, title, created_at)
                        VALUES (:user_id, :s3_key, :category, :title, NOW())
                    ");
                    
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':s3_key' => $key,
                        ':category' => $category,
                        ':title' => $title,
                    ]);
                    
                    // Redirect after successful creation
                    header('Location: galleries.php');
                    exit;
                } else {
                    $error = 'File upload failed: ' . ($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $error = 'Failed to create gallery item: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        // Update gallery item
        $galleryId = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (!$galleryId || empty($title) || empty($category)) {
            $error = 'All fields are required';
        } else {
            // Verify ownership
            $stmt = $db->prepare("SELECT id FROM galleries WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $galleryId, ':user_id' => $userId]);
            
            if ($stmt->fetch()) {
                $stmt = $db->prepare("
                    UPDATE galleries 
                    SET category = :category, title = :title 
                    WHERE id = :id AND user_id = :user_id
                ");
                $stmt->execute([
                    ':id' => $galleryId,
                    ':user_id' => $userId,
                    ':category' => $category,
                    ':title' => $title,
                ]);
                $success = 'Gallery item updated successfully';
                $action = 'list';
            } else {
                $error = 'Gallery item not found';
            }
        }
    } elseif ($action === 'delete') {
        // Delete gallery item
        $galleryId = $_POST['id'] ?? null;
        
        if (!$galleryId) {
            $error = 'Gallery ID is required';
        } else {
            // Get S3 key and verify ownership
            $stmt = $db->prepare("
                SELECT s3_key FROM galleries 
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->execute([':id' => $galleryId, ':user_id' => $userId]);
            $gallery = $stmt->fetch();
            
            if ($gallery) {
                try {
                    $s3 = new S3Service();
                    $s3->deleteFile($gallery['s3_key']);
                    
                    $stmt = $db->prepare("DELETE FROM galleries WHERE id = :id AND user_id = :user_id");
                    $stmt->execute([':id' => $galleryId, ':user_id' => $userId]);
                    
                    $success = 'Gallery item deleted successfully';
                } catch (Exception $e) {
                    $error = 'Failed to delete: ' . $e->getMessage();
                }
            } else {
                $error = 'Gallery item not found';
            }
            $action = 'list';
        }
    }
}

// Fetch galleries for list view
$galleries = [];
if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT id, s3_key, category, title, created_at
        FROM galleries
        WHERE user_id = :user_id
        ORDER BY created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $galleries = $stmt->fetchAll();
    
    // Generate presigned URLs for images
    try {
        $s3 = new S3Service();
        foreach ($galleries as &$gallery) {
            if (is_array($gallery) && !empty($gallery['s3_key'])) {
                $gallery['preview_url'] = $s3->getPresignedUrl($gallery['s3_key'], 3600);
            } else {
                $gallery['preview_url'] = null;
            }
        }
        unset($gallery); // Unset reference to avoid issues
    } catch (Exception $e) {
        // If S3 is not configured, preview URLs will be null
        foreach ($galleries as &$gallery) {
            if (is_array($gallery)) {
                $gallery['preview_url'] = null;
            }
        }
        unset($gallery); // Unset reference
    }
}

// Fetch single gallery for edit
$gallery = null;
if ($action === 'edit' && $galleryId) {
    $stmt = $db->prepare("
        SELECT id, s3_key, category, title, created_at
        FROM galleries
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([':id' => $galleryId, ':user_id' => $userId]);
    $gallery = $stmt->fetch();
    
    if ($gallery) {
        // Generate presigned URL for preview
        try {
            $s3 = new S3Service();
            $gallery['preview_url'] = $s3->getPresignedUrl($gallery['s3_key'], 3600);
        } catch (Exception $e) {
            $gallery['preview_url'] = null;
        }
    } else {
        $error = 'Gallery item not found';
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/franken-ui@2.1.2/dist/css/core.min.css" />
    <title>Gallery Management - Mari Berkarya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: hsl(0, 0%, 98%);
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: hsl(0, 0%, 9%);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-primary {
            background-color: hsl(0, 0%, 9%);
            color: white;
        }

        .btn-primary:hover {
            background-color: hsl(0, 0%, 15%);
        }

        .btn-danger {
            background-color: hsl(0, 84%, 60%);
            color: white;
        }

        .btn-danger:hover {
            background-color: hsl(0, 84%, 55%);
        }

        .btn-secondary {
            background-color: hsl(0, 0%, 90%);
            color: hsl(0, 0%, 9%);
        }

        .btn-secondary:hover {
            background-color: hsl(0, 0%, 85%);
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: hsl(0, 84%, 60%);
            color: white;
        }

        .alert-success {
            background-color: hsl(142, 71%, 45%);
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: hsl(0, 0%, 96%);
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid hsl(0, 0%, 90%);
        }

        th {
            font-weight: 600;
            font-size: 0.875rem;
            color: hsl(0, 0%, 9%);
        }

        td {
            font-size: 0.875rem;
            color: hsl(0, 0%, 45%);
        }

        tbody tr:hover {
            background-color: hsl(0, 0%, 98%);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(0, 0%, 9%);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid hsl(0, 0%, 80%);
            border-radius: 0.375rem;
            background: white;
            color: hsl(0, 0%, 9%);
        }

        .form-input:focus {
            outline: none;
            border-color: hsl(221, 83%, 53%);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.25rem;
            background-color: hsl(0, 0%, 90%);
            color: hsl(0, 0%, 9%);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gallery Management</h1>
            <div>
                <?php if ($action === 'list'): ?>
                    <a href="?action=create" class="btn btn-primary">+ Add New</a>
                <?php else: ?>
                    <a href="?" class="btn btn-secondary">← Back to List</a>
                <?php endif; ?>
                <a href="?action=logout" class="btn btn-secondary" style="margin-left: 0.5rem;">Logout</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- List View -->
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 80px;">Preview</th>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($galleries)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: hsl(0, 0%, 60%);">
                                        No gallery items found. <a href="?action=create">Create one</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($galleries as $item): ?>
                                    <?php if (!is_array($item) || empty($item['id'])) continue; ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($item['preview_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['preview_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 0.25rem;">
                                            <?php else: ?>
                                                <div style="width: 60px; height: 60px; background: hsl(0, 0%, 90%); border-radius: 0.25rem; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: hsl(0, 0%, 60%);">
                                                    No preview
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($item['title'] ?? ''); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($item['category'] ?? ''); ?></span></td>
                                        <td><?php echo !empty($item['created_at']) ? date('Y-m-d H:i', strtotime($item['created_at'])) : ''; ?></td>
                                        <td>
                                            <div class="actions">
                                                <a href="?action=edit&id=<?php echo htmlspecialchars($item['id'] ?? ''); ?>" class="btn btn-secondary btn-sm">Edit</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id'] ?? ''); ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($action === 'create'): ?>
            <!-- Create Form -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem; font-size: 1.25rem;">Create New Gallery Item</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" id="title" name="title" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" id="category" name="category" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="file" class="form-label">File</label>
                        <input type="file" id="file" name="file" class="form-input" required accept="image/*">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create</button>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        <?php elseif ($action === 'edit' && $gallery): ?>
            <!-- Edit Form -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem; font-size: 1.25rem;">Edit Gallery Item</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($gallery['id']); ?>">
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" id="title" name="title" class="form-input" 
                               value="<?php echo htmlspecialchars($gallery['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" id="category" name="category" class="form-input" 
                               value="<?php echo htmlspecialchars($gallery['category']); ?>" required>
                    </div>

                    <?php if (!empty($gallery['preview_url'])): ?>
                        <div class="form-group">
                            <label class="form-label">Preview</label>
                            <div>
                                <img src="<?php echo htmlspecialchars($gallery['preview_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($gallery['title']); ?>" 
                                     style="max-width: 300px; max-height: 300px; object-fit: contain; border-radius: 0.25rem; border: 1px solid hsl(0, 0%, 90%);">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">S3 Key</label>
                        <input type="text" class="form-input" 
                               value="<?php echo htmlspecialchars($gallery['s3_key']); ?>" disabled>
                        <small style="color: hsl(0, 0%, 60%); font-size: 0.75rem;">File cannot be changed after upload</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
