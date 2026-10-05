<?php
// admin_contact.php - Customer Contact & Support Leads Management
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

        // 1. UPDATE STATUS
        if ($action === 'update_status') {
            $id = (int)($_POST['id'] ?? 0);
            $newStatus = in_array($_POST['status'] ?? '', ['New', 'Read', 'Replied']) ? $_POST['status'] : 'Read';
            if ($id > 0) {
                $db->prepare("UPDATE contact_submissions SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $alertSuccess = "Inquiry #{$id} status updated to {$newStatus}.";
            }
        }
        // 2. DELETE SUBMISSION
        elseif ($action === 'delete_submission') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM contact_submissions WHERE id = ?")->execute([$id]);
                $alertSuccess = "Contact message deleted successfully.";
            }
        }
    }
}

// Fetch Metrics
$totalMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_submissions")->fetchColumn();
$newMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'New'")->fetchColumn();
$readMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'Read'")->fetchColumn();
$repliedMsgs = (int)$db->query("SELECT COUNT(*) FROM contact_submissions WHERE status = 'Replied'")->fetchColumn();

// Filter & Search
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? 'All');

$query = "SELECT * FROM contact_submissions WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterStatus !== 'All' && !empty($filterStatus)) {
    $query .= " AND status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$submissions = $stmt->fetchAll();

$currentAdminPage = 'contact';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Leads & Inquiries | <?= htmlspecialchars($storeName) ?> Admin</title>
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
        .btn-whatsapp {
            background: rgba(37, 211, 102, 0.15);
            border: 1px solid rgba(37, 211, 102, 0.4);
            color: #25d366;
        }
        .btn-whatsapp:hover {
            background: rgba(37, 211, 102, 0.3);
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
        .badge-new {
            background: rgba(255, 77, 77, 0.15);
            color: #ff6666;
            border: 1px solid rgba(255, 77, 77, 0.4);
        }
        .badge-read {
            background: rgba(96, 165, 250, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(96, 165, 250, 0.4);
        }
        .badge-replied {
            background: rgba(74, 222, 128, 0.15);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.4);
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
                    <h1 class="page-title">Contact Leads & Inquiries</h1>
                    <p style="color: var(--text-secondary); font-size: 13.5px; margin-top: 4px;">
                        Manage customer inquiries, medical dosage questions, and support messages submitted from the contact page.
                    </p>
                </div>
            </div>
            <div class="header-actions">
                <a href="cbd-contact.php" target="_blank" class="btn btn-secondary">
                    View Contact Page ↗
                </a>
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
                <div class="stat-val" style="color: #fff;"><?= $totalMsgs ?></div>
                <div class="stat-lbl">Total Inquiries</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #ff6666;"><?= $newMsgs ?></div>
                <div class="stat-lbl">New & Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #60a5fa;"><?= $readMsgs ?></div>
                <div class="stat-lbl">In Review (Read)</div>
            </div>
            <div class="stat-card">
                <div class="stat-val" style="color: #4ade80;"><?= $repliedMsgs ?></div>
                <div class="stat-lbl">Resolved & Replied</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="admin_contact.php" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="Search sender, email, phone, subject..." value="<?= htmlspecialchars($search) ?>" style="flex-grow: 1; min-width: 220px;">
            <select name="status" class="filter-input" onchange="this.form.submit()">
                <option value="All" <?= $filterStatus === 'All' ? 'selected' : '' ?>>All Inquiries</option>
                <option value="New" <?= $filterStatus === 'New' ? 'selected' : '' ?>>New</option>
                <option value="Read" <?= $filterStatus === 'Read' ? 'selected' : '' ?>>Read</option>
                <option value="Replied" <?= $filterStatus === 'Replied' ? 'selected' : '' ?>>Replied</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($search) || $filterStatus !== 'All'): ?>
                <a href="admin_contact.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Inquiries Table -->
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 170px;">Sender</th>
                        <th style="width: 190px;">Contact Details</th>
                        <th>Subject & Message Excerpt</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 110px;">Date</th>
                        <th style="width: 220px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                No contact submissions found. All inquiries have been handled!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td>
                                    <strong style="color: #fff; font-size: 14.5px;"><?= htmlspecialchars($sub['name']) ?></strong>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: var(--accent-cyan); margin-bottom: 2px;">
                                        <a href="mailto:<?= htmlspecialchars($sub['email']) ?>" style="color: inherit; text-decoration: none;">
                                            <?= htmlspecialchars($sub['email']) ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($sub['phone'])): ?>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            <?= htmlspecialchars($sub['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #fff; font-size: 13.5px; display: block; margin-bottom: 4px;">
                                        <?= htmlspecialchars($sub['subject'] ?: 'Website Inquiry') ?>
                                    </strong>
                                    <div style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.4; max-width: 450px;">
                                        <?= htmlspecialchars(mb_strimwidth($sub['message'], 0, 120, '...')) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-status badge-<?= strtolower($sub['status']) ?>">
                                        <?= htmlspecialchars($sub['status']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: var(--text-secondary);">
                                    <?= date('M d, Y', strtotime($sub['created_at'])) ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-secondary" style="padding: 6px 10px; font-size: 12px;" onclick='openViewModal(<?= json_encode($sub) ?>)'>
                                        View
                                    </button>

                                    <a href="mailto:<?= htmlspecialchars($sub['email']) ?>?subject=<?= urlencode('Re: ' . ($sub['subject'] ?: 'KAMS HEMP Inquiry')) ?>" class="btn btn-primary" style="padding: 6px 10px; font-size: 12px;" title="Reply via Email">
                                        Email ✉
                                    </a>

                                    <?php if (!empty($sub['phone'])): ?>
                                        <?php 
                                            $cleanPhone = preg_replace('/[^0-9]/', '', $sub['phone']);
                                            if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                                        ?>
                                        <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= urlencode('Hello ' . $sub['name'] . ', thanks for reaching out to KAMS HEMP.') ?>" target="_blank" class="btn btn-whatsapp" style="padding: 6px 10px; font-size: 12px;" title="Chat on WhatsApp">
                                            WA
                                        </a>
                                    <?php endif; ?>

                                    <form method="POST" action="admin_contact.php" style="display: inline-block;" onsubmit="return confirm('Delete this inquiry?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_submission">
                                        <input type="hidden" name="id" value="<?= $sub['id'] ?>">
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

    <!-- Modal to View Inquiry Details -->
    <div class="modal" id="viewModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="viewSubject">Inquiry Details</h3>
                <button type="button" onclick="closeViewModal()" style="background:transparent; border:none; color:#aaa; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <div>
                <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--admin-border); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <div>
                            <strong style="color: #fff; font-size: 16px;" id="viewName"></strong>
                            <div style="font-size: 13px; color: var(--accent-cyan); margin-top: 2px;" id="viewEmail"></div>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="viewPhone"></div>
                        </div>
                        <div style="text-align: right;">
                            <span id="viewStatusBadge" class="badge-status"></span>
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 6px;" id="viewDate"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); font-weight: 700; display: block; margin-bottom: 8px;">Full Message</label>
                    <div id="viewFullMessage" style="background: rgba(0,0,0,0.4); border: 1px solid var(--admin-border); border-radius: 12px; padding: 16px; font-size: 14px; line-height: 1.6; color: #fff; white-space: pre-wrap;"></div>
                </div>

                <form method="POST" action="admin_contact.php" id="statusForm" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--admin-border); padding-top: 20px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="statusFormId" value="">

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 13px; color: var(--text-secondary);">Update Status:</label>
                        <select name="status" id="viewStatusSelect" class="filter-input" style="padding: 8px 12px;">
                            <option value="New">New</option>
                            <option value="Read">Read</option>
                            <option value="Replied">Replied</option>
                        </select>
                        <button type="submit" class="btn btn-secondary" style="padding: 8px 14px;">Update</button>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <a id="viewReplyBtn" href="#" class="btn btn-primary">Reply via Email ✉</a>
                        <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('viewModal');

        function openViewModal(sub) {
            document.getElementById('statusFormId').value = sub.id;
            document.getElementById('viewSubject').innerText = sub.subject || 'Website Inquiry';
            document.getElementById('viewName').innerText = sub.name || 'Anonymous';
            document.getElementById('viewEmail').innerText = sub.email || '';
            document.getElementById('viewPhone').innerText = sub.phone ? 'Phone: ' + sub.phone : 'No phone provided';
            document.getElementById('viewDate').innerText = sub.created_at || '';
            document.getElementById('viewFullMessage').innerText = sub.message || 'No content';
            
            const badge = document.getElementById('viewStatusBadge');
            badge.className = 'badge-status badge-' + sub.status.toLowerCase();
            badge.innerText = sub.status;

            document.getElementById('viewStatusSelect').value = sub.status;
            document.getElementById('viewReplyBtn').href = 'mailto:' + encodeURIComponent(sub.email) + '?subject=' + encodeURIComponent('Re: ' + (sub.subject || 'KAMS HEMP Inquiry'));

            modal.style.display = 'flex';
        }

        function closeViewModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target === modal) {
                closeViewModal();
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
