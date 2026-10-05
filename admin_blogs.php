<?php
// admin_blogs.php - Blog & Research Article Management
require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::requireAdmin();

$db = Database::getInstance();
$storeName = Settings::get('store_name', 'KAMS HEMP');

$alertSuccess = '';
$alertError = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $alertError = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // 1. ADD ARTICLE
        if ($action === 'add_blog') {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'Ayurvedic Science');
            $author = trim($_POST['author'] ?? 'Vaidya Research Team');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['Published', 'Draft']) ? $_POST['status'] : 'Published';
            $imageUrl = trim($_POST['image_url'] ?? '');

            // Handle file upload if provided
            if (!empty($_FILES['image_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $newName = 'blog_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $newName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                        $imageUrl = 'uploads/' . $newName;
                    }
                }
            }

            if (empty($title)) {
                $alertError = "Article title is required.";
            } else {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
                $slug = trim($slug, '-');
                // Check slug uniqueness
                $stmtChk = $db->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ?");
                $stmtChk->execute([$slug]);
                if ($stmtChk->fetchColumn() > 0) {
                    $slug .= '-' . rand(100, 999);
                }

                $stmt = $db->prepare("
                    INSERT INTO blogs (title, slug, category, excerpt, content, image_url, author, status, views, published_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
                ");
                $stmt->execute([$title, $slug, $category, $excerpt, $content, $imageUrl, $author, $status]);
                $alertSuccess = "Article '{$title}' created successfully!";
            }
        }
        // 2. EDIT ARTICLE
        elseif ($action === 'edit_blog') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? 'Ayurvedic Science');
            $author = trim($_POST['author'] ?? 'Vaidya Research Team');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['Published', 'Draft']) ? $_POST['status'] : 'Published';
            $imageUrl = trim($_POST['image_url'] ?? '');

            if (!empty($_FILES['image_file']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $newName = 'blog_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $newName;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                        $imageUrl = 'uploads/' . $newName;
                    }
                }
            }

            if ($id > 0 && !empty($title)) {
                if (!empty($imageUrl)) {
                    $stmt = $db->prepare("UPDATE blogs SET title = ?, category = ?, excerpt = ?, content = ?, image_url = ?, author = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $category, $excerpt, $content, $imageUrl, $author, $status, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE blogs SET title = ?, category = ?, excerpt = ?, content = ?, author = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $category, $excerpt, $content, $author, $status, $id]);
                }
                $alertSuccess = "Article updated successfully!";
            } else {
                $alertError = "Failed to update article.";
            }
        }
        // 3. TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $cur = $db->prepare("SELECT status FROM blogs WHERE id = ?");
                $cur->execute([$id]);
                $currentStatus = $cur->fetchColumn();
                $newStatus = ($currentStatus === 'Published') ? 'Draft' : 'Published';
                $db->prepare("UPDATE blogs SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $alertSuccess = "Article status changed to {$newStatus}.";
            }
        }
        // 4. DELETE ARTICLE
        elseif ($action === 'delete_blog') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
                $alertSuccess = "Article deleted successfully.";
            }
        }
    }
}

// Fetch Metrics
$totalBlogs = (int)$db->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
$publishedBlogs = (int)$db->query("SELECT COUNT(*) FROM blogs WHERE status = 'Published'")->fetchColumn();
$draftBlogs = (int)$db->query("SELECT COUNT(*) FROM blogs WHERE status = 'Draft'")->fetchColumn();
$totalViews = (int)$db->query("SELECT COALESCE(SUM(view_count), 0) FROM blogs")->fetchColumn();

// Filter & Search
$search = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? 'All');

$query = "SELECT * FROM blogs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR excerpt LIKE ? OR author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterCategory !== 'All' && !empty($filterCategory)) {
    $query .= " AND category = ?";
    $params[] = $filterCategory;
}

$query .= " ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$blogs = $stmt->fetchAll();

// Distinct categories
$categories = $db->query("SELECT DISTINCT category FROM blogs WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);

$currentAdminPage = 'blogs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog & Science Management | <?= htmlspecialchars($storeName) ?> Admin</title>
    <style>
        :root {
            --admin-bg: #07090e;
            --admin-surface: #0e121a;
            --admin-card: rgba(18, 24, 38, 0.7);
            --admin-border: rgba(255, 255, 255, 0.08);
            --admin-border-focus: rgba(0, 255, 204, 0.4);
            --accent-cyan: #00ffcc;
            --accent-purple: #8a2be2;
            --accent-gold: #e5c378;
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-display: "Cinzel", Georgia, serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: var(--admin-bg);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
        }

        .admin-sidebar {
            width: 270px;
            background: rgba(12, 14, 20, 0.95);
            border-right: 1px solid var(--admin-border);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            flex-shrink: 0;
            min-height: 100vh;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .brand-logo {
            padding: 0 24px 20px;
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .brand-logo span {
            font-size: 11px;
            color: var(--accent-cyan);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .admin-sidebar-close {
            display: none;
            position: absolute;
            top: 15px;
            right: 15px;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 16px 12px;
            flex-grow: 1;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(0, 255, 204, 0.08);
            color: var(--accent-cyan);
        }
        .nav-link.active {
            border: 1px solid rgba(0, 255, 204, 0.25);
            font-weight: 600;
        }
        .nav-link svg {
            width: 17px;
            height: 17px;
            stroke-width: 2;
            fill: none;
            stroke: currentColor;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--admin-border);
        }
        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            color: #000;
            font-weight: 800;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-main {
            flex-grow: 1;
            padding: 32px 40px;
            overflow-y: auto;
            max-width: calc(100vw - 270px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #fff;
        }
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00ffcc 0%, #00b4d8 100%);
            color: #000;
        }
        .btn-primary:hover {
            box-shadow: 0 0 20px rgba(0, 255, 204, 0.4);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--admin-border);
            color: #fff;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .btn-danger {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff6666;
        }
        .btn-danger:hover {
            background: rgba(255, 77, 77, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--admin-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 20px;
        }
        .stat-val {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .stat-lbl {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
        }

        .filter-bar {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            gap: 14px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-input {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            padding: 10px 14px;
            color: #fff;
            font-size: 13.5px;
            outline: none;
        }
        .filter-input:focus {
            border-color: var(--accent-cyan);
        }

        .data-table-container {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 14px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        th {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 14px 18px;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--admin-border);
        }
        td {
            padding: 16px 18px;
            border-bottom: 1px solid var(--admin-border);
            vertical-align: middle;
            color: #e2e8f0;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-published {
            background: rgba(74, 222, 128, 0.15);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }
        .badge-draft {
            background: rgba(250, 204, 21, 0.15);
            color: #facc15;
            border: 1px solid rgba(250, 204, 21, 0.3);
        }

        .thumb-preview {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            background: #1e293b;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: #0f141f;
            border: 1px solid var(--admin-border);
            border-radius: 18px;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.8);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-title {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .form-input {
            width: 100%;
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--admin-border);
            border-radius: 10px;
            padding: 12px 14px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
        }
        .form-input:focus {
            border-color: var(--accent-cyan);
            outline: none;
        }
        textarea.form-input {
            resize: vertical;
            min-height: 100px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .alert-msg {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(74, 222, 128, 0.15);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: #4ade80;
        }
        .alert-error {
            background: rgba(255, 77, 77, 0.15);
            border: 1px solid rgba(255, 77, 77, 0.3);
            color: #ff6666;
        }

        @media (max-width: 900px) {
            .admin-sidebar {
                position: fixed;
                left: -270px;
                z-index: 100;
                transition: left 0.3s ease;
            }
            .admin-sidebar.show {
                left: 0;
            }
            .admin-sidebar-close {
                display: block;
            }
            .admin-main {
                max-width: 100vw;
                padding: 20px;
            }
            .mobile-toggle {
                display: inline-flex !important;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 14px;">
                <button type="button" class="btn btn-secondary mobile-toggle" style="display:none;" onclick="document.getElementById('mainAdminSidebar').classList.toggle('show')">
                    ☰ Menu
                </button>
                <div>
                    <h1 class="page-title">Blog & Research Management</h1>
                    <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 4px;">
                        Publish clinical studies, dosage guides, and Himalayan Ayurvedic cannabis research.
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="blog.php" target="_blank" class="btn btn-secondary">
                    View Live Blog ↗
                </a>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    + Add New Article
                </button>
            </div>
        </div>

        <?php if (!empty($alertSuccess)): ?>
            <div class="alert-msg alert-success"><?= htmlspecialchars($alertSuccess) ?></div>
        <?php endif; ?>
        <?php if (!empty($alertError)): ?>
            <div class="alert-msg alert-error"><?= htmlspecialchars($alertError) ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-val" style="color: #fff;"><?= $totalBlogs ?></div>
                <div class="stat-lbl">Total Articles</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #4ade80;"><?= $publishedBlogs ?></div>
                <div class="stat-lbl">Published</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #facc15;"><?= $draftBlogs ?></div>
                <div class="stat-lbl">Drafts</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--accent-cyan);"><?= number_format($totalViews) ?></div>
                <div class="stat-lbl">Total Article Views</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="admin_blogs.php" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="Search by title, author, topic..." value="<?= htmlspecialchars($search) ?>" style="flex-grow: 1; min-width: 220px;">
            <select name="category" class="filter-input" onchange="this.form.submit()">
                <option value="All">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($search) || $filterCategory !== 'All'): ?>
                <a href="admin_blogs.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Articles Table -->
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 70px;">Image</th>
                        <th>Title & Category</th>
                        <th>Author</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No articles found. Click "+ Add New Article" to write your first post.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($blogs as $b): ?>
                            <tr>
                                <td>
                                    <?php $blogImg = !empty($b['featured_image']) ? $b['featured_image'] : ($b['image_url'] ?? ''); ?>
                                    <?php if (!empty($blogImg)): ?>
                                        <img src="<?= htmlspecialchars($blogImg) ?>" alt="thumb" class="thumb-preview" onerror="this.src='logo.png'">
                                    <?php else: ?>
                                        <div class="thumb-preview" style="display:flex; align-items:center; justify-content:center; color:#555; font-size:11px;">No Img</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #fff; font-size: 14.5px;"><?= htmlspecialchars($b['title']) ?></strong>
                                    <div style="font-size: 12px; color: var(--accent-cyan); margin-top: 2px;">
                                        <?= htmlspecialchars($b['category'] ?: 'Ayurvedic Science') ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($b['author'] ?: 'Vaidya Research') ?></td>
                                <td style="font-weight: 600; color: #fff;"><?= number_format((int)($b['view_count'] ?? $b['views'] ?? 0)) ?></td>
                                <td>
                                    <span class="badge-status <?= $b['status'] === 'Published' ? 'badge-published' : 'badge-draft' ?>">
                                        <?= htmlspecialchars($b['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: var(--text-secondary);">
                                    <?= date('M d, Y', strtotime($b['created_at'])) ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="blog_post.php?slug=<?= urlencode($b['slug']) ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" title="Preview">
                                        View ↗
                                    </a>

                                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick='openEditModal(<?= json_encode($b) ?>)'>
                                        Edit
                                    </button>

                                    <form method="POST" action="admin_blogs.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" title="Toggle Status">
                                            <?= $b['status'] === 'Published' ? 'Unpublish' : 'Publish' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="admin_blogs.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this article?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_blog">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 12px;">
                                            ✕
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal for Add / Edit Blog -->
    <div class="modal" id="blogModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Research Article</h3>
                <button type="button" onclick="closeBlogModal()" style="background:transparent; border:none; color:#aaa; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" action="admin_blogs.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="blogFormAction" value="add_blog">
                <input type="hidden" name="id" id="blogId" value="">

                <div class="form-group">
                    <label>Article Title *</label>
                    <input type="text" name="title" id="blogTitle" class="form-input" placeholder="e.g. Clinical Applications of Vijaya in Chronic Pain" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" id="blogCategory" class="form-input" placeholder="e.g. Ayurvedic Science, Sleep & Stress" value="Ayurvedic Science">
                    </div>
                    <div class="form-group">
                        <label>Author</label>
                        <input type="text" name="author" id="blogAuthor" class="form-input" placeholder="e.g. Dr. A. Sharma, BAMS" value="Vaidya Research Team">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="blogStatus" class="form-input">
                            <option value="Published">Published</option>
                            <option value="Draft">Draft</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Featured Image URL (or upload below)</label>
                        <input type="text" name="image_url" id="blogImageUrl" class="form-input" placeholder="https://... or uploads/...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Upload Featured Image</label>
                    <input type="file" name="image_file" class="form-input" accept="image/*">
                </div>

                <div class="form-group">
                    <label>Short Excerpt (Summary)</label>
                    <textarea name="excerpt" id="blogExcerpt" class="form-input" placeholder="Brief summary of the article for listings and meta descriptions..."></textarea>
                </div>

                <div class="form-group">
                    <label>Article Content (HTML / Markdown supported)</label>
                    <textarea name="content" id="blogContent" class="form-input" style="min-height: 220px;" placeholder="Full body of the article..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeBlogModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Article</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('blogModal');
        const formAction = document.getElementById('blogFormAction');
        const blogId = document.getElementById('blogId');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');

        function openAddModal() {
            formAction.value = 'add_blog';
            blogId.value = '';
            modalTitle.innerText = 'Create New Research Article';
            submitBtn.innerText = 'Publish Article';
            document.getElementById('blogTitle').value = '';
            document.getElementById('blogCategory').value = 'Ayurvedic Science';
            document.getElementById('blogAuthor').value = 'Vaidya Research Team';
            document.getElementById('blogStatus').value = 'Published';
            document.getElementById('blogImageUrl').value = '';
            document.getElementById('blogExcerpt').value = '';
            document.getElementById('blogContent').value = '';
            modal.style.display = 'flex';
        }

        function openEditModal(blog) {
            formAction.value = 'edit_blog';
            blogId.value = blog.id;
            modalTitle.innerText = 'Edit Article #' + blog.id;
            submitBtn.innerText = 'Save Changes';
            document.getElementById('blogTitle').value = blog.title || '';
            document.getElementById('blogCategory').value = blog.category || 'Ayurvedic Science';
            document.getElementById('blogAuthor').value = blog.author || '';
            document.getElementById('blogStatus').value = blog.status || 'Published';
            document.getElementById('blogImageUrl').value = blog.image_url || '';
            document.getElementById('blogExcerpt').value = blog.excerpt || '';
            document.getElementById('blogContent').value = blog.content || '';
            modal.style.display = 'flex';
        }

        function closeBlogModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target === modal) {
                closeBlogModal();
            }
        };

        const closeBtn = document.getElementById('adminSidebarCloseBtn');
        if (closeBtn) {
            closeBtn.onclick = function() {
                document.getElementById('mainAdminSidebar').classList.remove('show');
            };
        }
    </script>
</body>
</html>
