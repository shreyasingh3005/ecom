<?php
// admin_faqs.php - FAQ Question & Answer Management
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

        // 1. ADD FAQ
        if ($action === 'add_faq') {
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $category = trim($_POST['category'] ?? 'General');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['Published', 'Draft']) ? $_POST['status'] : 'Published';

            if (empty($question) || empty($answer)) {
                $alertError = "Both Question and Answer are required.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO faqs (category, question, answer, sort_order, status, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$category, $question, $answer, $sortOrder, $status]);
                $alertSuccess = "FAQ question added successfully!";
            }
        }
        // 2. EDIT FAQ
        elseif ($action === 'edit_faq') {
            $id = (int)($_POST['id'] ?? 0);
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $category = trim($_POST['category'] ?? 'General');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['Published', 'Draft']) ? $_POST['status'] : 'Published';

            if ($id > 0 && !empty($question) && !empty($answer)) {
                $stmt = $db->prepare("
                    UPDATE faqs 
                    SET category = ?, question = ?, answer = ?, sort_order = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category, $question, $answer, $sortOrder, $status, $id]);
                $alertSuccess = "FAQ updated successfully!";
            } else {
                $alertError = "Failed to update FAQ. Please check inputs.";
            }
        }
        // 3. TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $cur = $db->prepare("SELECT status FROM faqs WHERE id = ?");
                $cur->execute([$id]);
                $currentStatus = $cur->fetchColumn();
                $newStatus = ($currentStatus === 'Published') ? 'Draft' : 'Published';
                $db->prepare("UPDATE faqs SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $alertSuccess = "FAQ status changed to {$newStatus}.";
            }
        }
        // 4. DELETE FAQ
        elseif ($action === 'delete_faq') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM faqs WHERE id = ?")->execute([$id]);
                $alertSuccess = "FAQ deleted successfully.";
            }
        }
    }
}

// Fetch Metrics
$totalFaqs = (int)$db->query("SELECT COUNT(*) FROM faqs")->fetchColumn();
$publishedFaqs = (int)$db->query("SELECT COUNT(*) FROM faqs WHERE status = 'Published'")->fetchColumn();
$draftFaqs = (int)$db->query("SELECT COUNT(*) FROM faqs WHERE status = 'Draft'")->fetchColumn();

// Filter & Search
$search = trim($_GET['search'] ?? '');
$filterCategory = trim($_GET['category'] ?? 'All');

$query = "SELECT * FROM faqs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (question LIKE ? OR answer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterCategory !== 'All' && !empty($filterCategory)) {
    $query .= " AND category = ?";
    $params[] = $filterCategory;
}

$query .= " ORDER BY category ASC, sort_order ASC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$faqs = $stmt->fetchAll();

// Distinct categories
$categories = $db->query("SELECT DISTINCT category FROM faqs WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($categories)) {
    $categories = ['General', 'Orders', 'Payment', 'Shipping', 'Returns', 'Dosage & Usage', 'Legal & AYUSH'];
}

$currentAdminPage = 'faqs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ Management | <?= htmlspecialchars($storeName) ?> Admin</title>
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
            vertical-align: top;
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
            max-width: 640px;
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
            min-height: 120px;
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
                    <h1 class="page-title">Frequently Asked Questions (FAQ)</h1>
                    <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 4px;">
                        Manage customer questions, AYUSH compliance clarifications, dosage guides, and order policies.
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="faq.php" target="_blank" class="btn btn-secondary">
                    View Live FAQ ↗
                </a>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    + Add New FAQ
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
                <div class="stat-val" style="color: #fff;"><?= $totalFaqs ?></div>
                <div class="stat-lbl">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #4ade80;"><?= $publishedFaqs ?></div>
                <div class="stat-lbl">Active & Published</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #facc15;"><?= $draftFaqs ?></div>
                <div class="stat-lbl">Drafts</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: var(--accent-cyan);"><?= count($categories) ?></div>
                <div class="stat-lbl">Active Categories</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="admin_faqs.php" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="Search question or answer..." value="<?= htmlspecialchars($search) ?>" style="flex-grow: 1; min-width: 220px;">
            <select name="category" class="filter-input" onchange="this.form.submit()">
                <option value="All">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($search) || $filterCategory !== 'All'): ?>
                <a href="admin_faqs.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <!-- FAQs Table -->
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 140px;">Category</th>
                        <th>Question & Answer</th>
                        <th style="width: 80px;">Order</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 180px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faqs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No FAQ questions found. Click "+ Add New FAQ" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faqs as $f): ?>
                            <tr>
                                <td>
                                    <span style="font-size: 11px; font-weight: 700; color: var(--accent-cyan); background: rgba(0,255,204,0.08); padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(0,255,204,0.2);">
                                        <?= htmlspecialchars($f['category'] ?: 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: #fff; font-size: 14.5px; display: block; margin-bottom: 6px;">
                                        <?= htmlspecialchars($f['question']) ?>
                                    </strong>
                                    <div style="font-size: 13px; color: var(--text-secondary); line-height: 1.5; max-width: 650px;">
                                        <?= nl2br(htmlspecialchars($f['answer'])) ?>
                                    </div>
                                </td>
                                <td style="font-weight: bold; color: #fff;"><?= (int)$f['sort_order'] ?></td>
                                <td>
                                    <span class="badge-status <?= $f['status'] === 'Published' ? 'badge-published' : 'badge-draft' ?>">
                                        <?= htmlspecialchars($f['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick='openEditModal(<?= json_encode($f) ?>)'>
                                        Edit
                                    </button>

                                    <form method="POST" action="admin_faqs.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;">
                                            <?= $f['status'] === 'Published' ? 'Unpublish' : 'Publish' ?>
                                        </button>
                                    </form>

                                    <form method="POST" action="admin_faqs.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this FAQ question?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_faq">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
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

    <!-- Modal for Add / Edit FAQ -->
    <div class="modal" id="faqModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add New FAQ Question</h3>
                <button type="button" onclick="closeFaqModal()" style="background:transparent; border:none; color:#aaa; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" action="admin_faqs.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="faqFormAction" value="add_faq">
                <input type="hidden" name="id" id="faqId" value="">

                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <input type="text" name="category" id="faqCategory" list="catList" class="form-input" placeholder="e.g. Legal & AYUSH, Orders, Dosage" required>
                        <datalist id="catList">
                            <option value="General">
                            <option value="Orders & Shipping">
                            <option value="Payment & COD">
                            <option value="Legal & AYUSH Compliance">
                            <option value="Dosage & Usage">
                            <option value="Returns & Refunds">
                            <option value="B2B Wholesale">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="faqSortOrder" class="form-input" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label>Question *</label>
                    <input type="text" name="question" id="faqQuestion" class="form-input" placeholder="e.g. Is Vijaya extract legal in India?" required>
                </div>

                <div class="form-group">
                    <label>Answer *</label>
                    <textarea name="answer" id="faqAnswer" class="form-input" placeholder="Comprehensive, clear answer for customers..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="faqStatus" class="form-input">
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeFaqModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('faqModal');
        const formAction = document.getElementById('faqFormAction');
        const faqId = document.getElementById('faqId');
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');

        function openAddModal() {
            formAction.value = 'add_faq';
            faqId.value = '';
            modalTitle.innerText = 'Add New FAQ Question';
            submitBtn.innerText = 'Save Question';
            document.getElementById('faqCategory').value = 'General';
            document.getElementById('faqSortOrder').value = '0';
            document.getElementById('faqQuestion').value = '';
            document.getElementById('faqAnswer').value = '';
            document.getElementById('faqStatus').value = 'Published';
            modal.style.display = 'flex';
        }

        function openEditModal(faq) {
            formAction.value = 'edit_faq';
            faqId.value = faq.id;
            modalTitle.innerText = 'Edit FAQ Question #' + faq.id;
            submitBtn.innerText = 'Save Changes';
            document.getElementById('faqCategory').value = faq.category || 'General';
            document.getElementById('faqSortOrder').value = faq.sort_order || 0;
            document.getElementById('faqQuestion').value = faq.question || '';
            document.getElementById('faqAnswer').value = faq.answer || '';
            document.getElementById('faqStatus').value = faq.status || 'Published';
            modal.style.display = 'flex';
        }

        function closeFaqModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target === modal) {
                closeFaqModal();
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
