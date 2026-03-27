<?php
// ─── DATABASE CONFIG ───────────────────────────────────────────────
$db_host = '172.16.29.45';
$db_user = 'DB_Admin';
$db_pass = 'luthasdbsrv@2022';
$db_name = 'resigned_emp';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('<div style="font-family:sans-serif;padding:40px;color:#c1440e;">
        <h2>Database Connection Failed</h2>
        <p>' . mysqli_connect_error() . '</p>
        <p>Make sure you have run <strong>setup_database.sql</strong> in phpMyAdmin first.</p>
    </div>');
}

mysqli_set_charset($conn, 'utf8');

// ─── HANDLE ACTIONS ───────────────────────────────────────────────
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // ── SINGLE ADD ──────────────────────────────────────────────
        if ($_POST['action'] === 'add') {
            $employee_id = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
            $name        = mysqli_real_escape_string($conn, trim($_POST['name']));
            $department  = mysqli_real_escape_string($conn, trim($_POST['department']));
            $position    = mysqli_real_escape_string($conn, trim($_POST['position']));
            $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
            $hire_date   = mysqli_real_escape_string($conn, $_POST['hire_date']);
            $resign_date = mysqli_real_escape_string($conn, $_POST['resign_date']);
            $reason      = mysqli_real_escape_string($conn, trim($_POST['reason']));

            // Check duplicate employee_id
            $check = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$employee_id'");
            if (mysqli_num_rows($check) > 0) {
                $message = "Employee ID '$employee_id' already exists in the database.";
                $messageType = 'error';
            } else {
                $sql = "INSERT INTO res (employee_id, name, department, position, email, hire_date, resign_date, reason)
                        VALUES ('$employee_id','$name','$department','$position','$email','$hire_date','$resign_date','$reason')";

                if (mysqli_query($conn, $sql)) {
                    $message = "Employee '$name' (ID: $employee_id) has been added successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error adding employee: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            }

        // ── DELETE ──────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['delete_id'];
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, employee_id FROM res WHERE id = $id"));
            if ($row) {
                if (mysqli_query($conn, "DELETE FROM res WHERE id = $id")) {
                    $message = "Employee '{$row['name']}' (ID: {$row['employee_id']}) has been removed.";
                    $messageType = 'info';
                } else {
                    $message = "Error deleting: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            } else {
                $message = "Employee not found.";
                $messageType = 'error';
            }

        // ── EXCEL IMPORT ────────────────────────────────────────────
        } elseif ($_POST['action'] === 'import_excel') {
            if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
                $tmpFile  = $_FILES['excel_file']['tmp_name'];
                $origName = $_FILES['excel_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                    $message = "Invalid file type. Please upload an .xlsx, .xls, or .csv file.";
                    $messageType = 'error';
                } else {
                    // ── Parse file ──────────────────────────────────
                    $rows = [];

                    if ($ext === 'csv') {
                        // CSV parsing
                        if (($handle = fopen($tmpFile, 'r')) !== false) {
                            $header = null;
                            while (($line = fgetcsv($handle)) !== false) {
                                if (!$header) { $header = $line; continue; }
                                $rows[] = array_combine($header, $line);
                            }
                            fclose($handle);
                        }
                    } else {
                        // XLSX parsing — requires PhpSpreadsheet
                        // Install via: composer require phpoffice/phpspreadsheet
                        $autoload = __DIR__ . '/vendor/autoload.php';
                        if (!file_exists($autoload)) {
                            $message = "PhpSpreadsheet not found. Run: <code>composer require phpoffice/phpspreadsheet</code> in your project directory.";
                            $messageType = 'error';
                            goto skip_import;
                        }
                        require_once $autoload;
                        try {
                            $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpFile);
                            $reader->setReadDataOnly(true);
                            $spreadsheet = $reader->load($tmpFile);
                            $sheet       = $spreadsheet->getActiveSheet();
                            $data        = $sheet->toArray(null, true, true, false);

                            if (empty($data)) {
                                $message = "The uploaded file is empty.";
                                $messageType = 'error';
                                goto skip_import;
                            }

                            // First row = headers
                            $headers = array_map('trim', $data[0]);
                            for ($i = 1; $i < count($data); $i++) {
                                if (array_filter($data[$i])) { // skip blank rows
                                    $rows[] = array_combine($headers, $data[$i]);
                                }
                            }
                        } catch (Exception $e) {
                            $message = "Error reading file: " . $e->getMessage();
                            $messageType = 'error';
                            goto skip_import;
                        }
                    }

                    // ── Insert rows ─────────────────────────────────
                    // Expected columns (case-insensitive):
                    //   employee_id, name, department, position, email, hire_date, resign_date, reason
                    $imported = 0;
                    $skipped  = 0;
                    $errors   = [];

                    foreach ($rows as $rowNum => $row) {
                        // Normalise keys to lowercase
                        $row = array_change_key_case($row, CASE_LOWER);

                        $emp_id      = trim($row['employee_id'] ?? $row['emp_id'] ?? $row['id'] ?? '');
                        $r_name      = trim($row['name'] ?? $row['full_name'] ?? '');
                        $r_dept      = trim($row['department'] ?? $row['dept'] ?? '');
                        $r_position  = trim($row['position'] ?? $row['job_title'] ?? $row['title'] ?? '');
                        $r_email     = trim($row['email'] ?? $row['email_address'] ?? '');
                        $r_hire      = trim($row['hire_date'] ?? $row['hiredate'] ?? $row['date_hired'] ?? '');
                        $r_resign    = trim($row['resign_date'] ?? $row['resigndate'] ?? $row['date_resigned'] ?? '');
                        $r_reason    = trim($row['reason'] ?? $row['resignation_reason'] ?? '');

                        // Skip completely empty rows
                        if (!$emp_id && !$r_name) continue;

                        // Validate required fields
                        if (!$emp_id || !$r_name || !$r_dept || !$r_position || !$r_hire || !$r_resign) {
                            $skipped++;
                            $errors[] = "Row " . ($rowNum + 2) . ": Missing required fields (employee_id, name, department, position, hire_date, resign_date).";
                            continue;
                        }

                        // Normalise dates (Excel numeric or string)
                        $r_hire   = normalise_date($r_hire);
                        $r_resign = normalise_date($r_resign);

                        // Escape
                        $emp_id   = mysqli_real_escape_string($conn, $emp_id);
                        $r_name   = mysqli_real_escape_string($conn, $r_name);
                        $r_dept   = mysqli_real_escape_string($conn, $r_dept);
                        $r_position = mysqli_real_escape_string($conn, $r_position);
                        $r_email  = mysqli_real_escape_string($conn, $r_email);
                        $r_reason = mysqli_real_escape_string($conn, $r_reason);

                        // Duplicate check
                        $dup = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$emp_id'");
                        if (mysqli_num_rows($dup) > 0) {
                            $skipped++;
                            $errors[] = "Row " . ($rowNum + 2) . ": Employee ID '$emp_id' already exists — skipped.";
                            continue;
                        }

                        $sql = "INSERT INTO res (employee_id, name, department, position, email, hire_date, resign_date, reason)
                                VALUES ('$emp_id','$r_name','$r_dept','$r_position','$r_email','$r_hire','$r_resign','$r_reason')";
                        if (mysqli_query($conn, $sql)) {
                            $imported++;
                        } else {
                            $skipped++;
                            $errors[] = "Row " . ($rowNum + 2) . ": DB error — " . mysqli_error($conn);
                        }
                    }

                    $message = "Import complete: <strong>$imported</strong> record(s) added, <strong>$skipped</strong> skipped.";
                    if (!empty($errors)) {
                        $message .= "<ul style='margin-top:8px;padding-left:18px;font-size:0.82rem'>";
                        foreach ($errors as $err) $message .= "<li>$err</li>";
                        $message .= "</ul>";
                    }
                    $messageType = $skipped > 0 ? 'info' : 'success';
                }
            } else {
                $message = "No file uploaded or upload error occurred.";
                $messageType = 'error';
            }
            skip_import:;
        }
    }
}

// ─── HELPER: normalise date ────────────────────────────────────────
function normalise_date($val) {
    if (is_numeric($val)) {
        // Excel serial date
        $unix = ($val - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : $val;
}

// ─── FETCH DATA ───────────────────────────────────────────────────
$employees = array();
$result = mysqli_query($conn, "SELECT * FROM res ORDER BY resign_date DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}

$totalResigned = count($employees);

$deptCounts = array();
foreach ($employees as $emp) {
    $dept = $emp['department'];
    $deptCounts[$dept] = isset($deptCounts[$dept]) ? $deptCounts[$dept] + 1 : 1;
}

$recent = 0;
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
foreach ($employees as $emp) {
    if ($emp['resign_date'] >= $sixMonthsAgo) $recent++;
}

$totalDays = 0;
foreach ($employees as $emp) {
    $hire   = new DateTime($emp['hire_date']);
    $resign = new DateTime($emp['resign_date']);
    $totalDays += $hire->diff($resign)->days;
}
$avgTenureYears = $totalResigned > 0 ? round($totalDays / $totalResigned / 365, 1) : 0;

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resigned Employee Registry</title>
    <link rel="stylesheet" href="css/fonts.css">
    <style>
        :root {
            --ink: #0d0d0d;
            --paper: #f5f2eb;
            --card: #ffffff;
            --accent: #c1440e;
            --accent2: #e8855a;
            --muted: #888;
            --border: #e2ddd5;
            --success: #2d7a4f;
            --info: #1e5fa3;
            --shadow: 0 2px 16px rgba(0,0,0,0.07);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
        }

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 240px;
            background: var(--ink);
            color: #fff;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid #2a2a2a;
        }

        .sidebar-brand h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .sidebar-brand span {
            display: block;
            font-size: 0.7rem;
            color: var(--accent2);
            font-weight: 400;
            margin-top: 3px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .sidebar-nav { padding: 16px 0; flex: 1; }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 11px 24px;
            color: #aaa;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            gap: 10px;
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover { color: #fff; background: #1a1a1a; }
        .nav-item.active { color: #fff; border-left-color: var(--accent); background: #1a1a1a; }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #2a2a2a;
            font-size: 0.75rem;
            color: #555;
        }

        .main { flex: 1; padding: 36px 40px; overflow-y: auto; }

        .page-header { margin-bottom: 32px; }

        .page-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .page-header p { color: var(--muted); font-size: 0.9rem; margin-top: 4px; }

        .alert {
            padding: 13px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.88rem;
            font-weight: 500;
        }
        .alert-success { background: #e9f7ef; color: var(--success); border: 1px solid #b7dfc9; }
        .alert-info    { background: #e8f0fb; color: var(--info);    border: 1px solid #b8d0ef; }
        .alert-error   { background: #fdf0ee; color: var(--accent);  border: 1px solid #f5c3b5; }

        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 22px 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            flex: 1;
            min-width: 160px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.orange::before { background: var(--accent); }
        .stat-card.blue::before   { background: var(--info); }
        .stat-card.green::before  { background: var(--success); }
        .stat-card.purple::before { background: #7c4dff; }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 8px;
        }

        .stat-value {
            font-family: 'Syne', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
        }

        .stat-sub { font-size: 0.78rem; color: var(--muted); margin-top: 6px; }

        .dept-section {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 32px;
        }

        .section-title {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .dept-bar-row { margin-bottom: 14px; }

        .dept-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.83rem;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .dept-bar-track { background: #f0ece4; border-radius: 4px; height: 8px; overflow: hidden; }
        .dept-bar-fill  { height: 100%; border-radius: 4px; background: var(--accent); }

        .table-card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn {
            display: inline-block;
            padding: 9px 18px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.15s;
        }

        .btn-primary   { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #a33208; }

        .btn-green     { background: #2d7a4f; color: #fff; }
        .btn-green:hover { background: #225f3c; }

        .btn-danger {
            background: transparent;
            color: var(--accent);
            border: 1px solid #f5c3b5;
            font-size: 0.8rem;
            padding: 6px 12px;
        }
        .btn-danger:hover { background: #fdf0ee; }

        .btn-secondary {
            background: transparent;
            color: var(--muted);
            border: 1.5px solid var(--border);
        }
        .btn-secondary:hover { border-color: var(--ink); color: var(--ink); }

        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }

        thead th {
            padding: 12px 16px;
            text-align: left;
            background: #faf8f5;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #faf8f5; }

        .emp-name { font-weight: 600; }
        .emp-email { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
        .emp-id-badge {
            display: inline-block;
            background: #f0ece4;
            color: #555;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            margin-top: 3px;
            font-family: monospace;
            letter-spacing: 0.04em;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .badge-resigned { background: #fdf0ee; color: var(--accent); }

        /* ── FORM ─────────────────────────────────────────────────── */
        .form-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 720px;
        }

        .form-grid { display: flex; flex-wrap: wrap; gap: 18px; }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: calc(50% - 9px);
        }
        .form-group.full { width: 100%; }
        .form-group.third { width: calc(33.333% - 12px); }

        label { font-size: 0.8rem; font-weight: 600; }

        input, select, textarea {
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem;
            background: #faf8f5;
            color: var(--ink);
            outline: none;
            width: 100%;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            background: #fff;
        }

        textarea { resize: vertical; min-height: 80px; }

        .form-actions { margin-top: 24px; display: flex; gap: 12px; }

        /* ── IMPORT PAGE ──────────────────────────────────────────── */
        .import-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 720px;
        }

        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 40px 24px;
            text-align: center;
            background: #faf8f5;
            cursor: pointer;
            transition: border-color 0.2s;
            position: relative;
        }
        .upload-zone:hover { border-color: var(--accent); }
        .upload-zone input[type=file] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .upload-icon { font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5; }
        .upload-zone p { font-size: 0.9rem; color: var(--muted); }
        .upload-zone strong { color: var(--ink); }
        .file-name-display {
            margin-top: 10px;
            font-size: 0.82rem;
            color: var(--accent);
            font-weight: 600;
            min-height: 18px;
        }

        .template-note {
            background: #e8f0fb;
            border: 1px solid #b8d0ef;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 0.85rem;
            color: var(--info);
            margin-top: 20px;
            line-height: 1.6;
        }
        .template-note strong { display: block; margin-bottom: 4px; }

        .col-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        .col-pill {
            background: #d0e2f7;
            color: var(--info);
            font-size: 0.73rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 4px;
            font-family: monospace;
        }
        .col-pill.optional { background: #eee; color: #666; }

        .empty { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.4; }
        .empty p { font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="layout">

    <nav class="sidebar">
        <div class="sidebar-brand">
            <h1>Resigned Emp<br>Registry</h1>
            <span>HR Management</span>
        </div>
        <div class="sidebar-nav">
            <a href="?view=dashboard" class="nav-item <?php echo ($view === 'dashboard') ? 'active' : ''; ?>">
                &#9632; Dashboard
            </a>
            <a href="?view=list" class="nav-item <?php echo ($view === 'list') ? 'active' : ''; ?>">
                &#9776; All Records
            </a>
            <a href="?view=add" class="nav-item <?php echo ($view === 'add') ? 'active' : ''; ?>">
                &#43; Add Record
            </a>
            <a href="?view=import" class="nav-item <?php echo ($view === 'import') ? 'active' : ''; ?>">
                &#8679; Import Excel
            </a>
        </div>
        <div class="sidebar-footer">
            <?php echo $totalResigned; ?> record<?php echo ($totalResigned !== 1) ? 's' : ''; ?> in `res`
        </div>
    </nav>

    <main class="main">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
        <!-- ═══════════════════════════════ DASHBOARD ═══════════════════════ -->
        <div class="page-header">
            <h2>Dashboard</h2>
            <p>Overview of resigned employee records &mdash; stored in table <strong>res</strong></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card orange">
                <div class="stat-label">Total Resigned</div>
                <div class="stat-value"><?php echo $totalResigned; ?></div>
                <div class="stat-sub">All time records</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-label">Recent (6 mo.)</div>
                <div class="stat-value"><?php echo $recent; ?></div>
                <div class="stat-sub">Last 6 months</div>
            </div>
            <div class="stat-card green">
                <div class="stat-label">Avg. Tenure</div>
                <div class="stat-value"><?php echo $avgTenureYears; ?>y</div>
                <div class="stat-sub">Years of service</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-label">Departments</div>
                <div class="stat-value"><?php echo count($deptCounts); ?></div>
                <div class="stat-sub">Affected departments</div>
            </div>
        </div>

        <?php if (!empty($deptCounts)): ?>
        <div class="dept-section">
            <div class="section-title">Resignations by Department</div>
            <?php foreach ($deptCounts as $dept => $count): ?>
            <div class="dept-bar-row">
                <div class="dept-bar-label">
                    <span><?php echo htmlspecialchars($dept); ?></span>
                    <span><?php echo $count; ?> employee<?php echo ($count > 1) ? 's' : ''; ?></span>
                </div>
                <div class="dept-bar-track">
                    <div class="dept-bar-fill" style="width: <?php echo round($count / $totalResigned * 100); ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <span class="section-title" style="margin:0">Recent Records</span>
                <a href="?view=list" class="btn btn-secondary">View All</a>
            </div>
            <?php $recent_emps = array_slice($employees, 0, 5); ?>
            <?php if (empty($recent_emps)): ?>
            <div class="empty">
                <div class="empty-icon">&#128203;</div>
                <p>No records yet. <a href="?view=add">Add your first entry.</a></p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Resign Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_emps as $emp): ?>
                    <tr>
                        <td><span class="emp-id-badge"><?php echo htmlspecialchars($emp['employee_id'] ?? '—'); ?></span></td>
                        <td>
                            <div class="emp-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                            <div class="emp-email"><?php echo htmlspecialchars($emp['email']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($emp['department']); ?></td>
                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                        <td><?php echo htmlspecialchars($emp['resign_date']); ?></td>
                        <td><span class="badge badge-resigned">Resigned</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php elseif ($view === 'list'): ?>
        <!-- ═══════════════════════════════ ALL RECORDS ════════════════════ -->
        <div class="page-header">
            <h2>All Records</h2>
            <p><?php echo $totalResigned; ?> resigned employee<?php echo ($totalResigned !== 1) ? 's' : ''; ?> in database</p>
        </div>

        <div class="table-card">
            <div class="table-header">
                <span class="section-title" style="margin:0">Employee Records</span>
                <div class="table-header-actions">
                    <a href="?view=import" class="btn btn-green">&#8679; Import Excel</a>
                    <a href="?view=add" class="btn btn-primary">+ Add Employee</a>
                </div>
            </div>
            <?php if (empty($employees)): ?>
            <div class="empty">
                <div class="empty-icon">&#128203;</div>
                <p>No resigned employees on file. <a href="?view=add">Add one now.</a></p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Hired</th>
                        <th>Resigned</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><span class="emp-id-badge"><?php echo htmlspecialchars($emp['employee_id'] ?? '—'); ?></span></td>
                        <td>
                            <div class="emp-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                            <div class="emp-email"><?php echo htmlspecialchars($emp['email']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($emp['department']); ?></td>
                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                        <td><?php echo htmlspecialchars($emp['hire_date']); ?></td>
                        <td><?php echo htmlspecialchars($emp['resign_date']); ?></td>
                        <td style="max-width:160px;font-size:0.82rem;color:#888"><?php echo htmlspecialchars($emp['reason']); ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this record?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?php echo $emp['id']; ?>">
                                <button type="submit" class="btn btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php elseif ($view === 'add'): ?>
        <!-- ═══════════════════════════════ ADD RECORD ════════════════════ -->
        <div class="page-header">
            <h2>Add Resigned Employee</h2>
            <p>Record will be permanently saved to table <strong>res</strong></p>
        </div>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_id">Employee ID *</label>
                        <input type="text" id="employee_id" name="employee_id" required placeholder="e.g. EMP-00123">
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required placeholder="maria@company.com">
                    </div>
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select department...</option>
                            <option>Engineering</option>
                            <option>Marketing</option>
                            <option>HR</option>
                            <option>Finance</option>
                            <option>Operations</option>
                            <option>Sales</option>
                            <option>IT</option>
                            <option>Legal</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="position">Position / Job Title *</label>
                        <input type="text" id="position" name="position" required placeholder="e.g. Senior Developer">
                    </div>
                    <div class="form-group">
                        <!-- spacer -->
                    </div>
                    <div class="form-group">
                        <label for="hire_date">Hire Date *</label>
                        <input type="date" id="hire_date" name="hire_date" required>
                    </div>
                    <div class="form-group">
                        <label for="resign_date">Resignation Date *</label>
                        <input type="date" id="resign_date" name="resign_date" required>
                    </div>
                    <div class="form-group full">
                        <label for="reason">Reason for Resignation</label>
                        <textarea id="reason" name="reason" placeholder="e.g. Career advancement, personal reasons, relocation..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save to Database</button>
                    <a href="?view=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif ($view === 'import'): ?>
        <!-- ═══════════════════════════════ IMPORT EXCEL ══════════════════ -->
        <div class="page-header">
            <h2>Import Excel / CSV</h2>
            <p>Upload an <strong>.xlsx</strong>, <strong>.xls</strong>, or <strong>.csv</strong> file to mass-import resigned employee records</p>
        </div>

        <div class="import-card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_excel">

                <div class="upload-zone" id="uploadZone">
                    <input type="file" name="excel_file" id="excelFile" accept=".xlsx,.xls,.csv"
                           onchange="document.getElementById('fileNameDisplay').textContent = this.files[0]?.name || ''">
                    <div class="upload-icon">&#128196;</div>
                    <p><strong>Click to browse</strong> or drag &amp; drop your file here</p>
                    <p>Supports .xlsx, .xls, .csv</p>
                    <div class="file-name-display" id="fileNameDisplay"></div>
                </div>

                <div class="template-note">
                    <strong>&#128274; Required column headers (row 1 of your spreadsheet):</strong>
                    <div class="col-list">
                        <span class="col-pill">employee_id</span>
                        <span class="col-pill">name</span>
                        <span class="col-pill">department</span>
                        <span class="col-pill">position</span>
                        <span class="col-pill">hire_date</span>
                        <span class="col-pill">resign_date</span>
                        <span class="col-pill optional">email</span>
                        <span class="col-pill optional">reason</span>
                    </div>
                    <p style="margin-top:10px;font-size:0.8rem;">
                        Grey pills are optional. Dates can be in <code>YYYY-MM-DD</code> or <code>MM/DD/YYYY</code> format.
                        Duplicate <code>employee_id</code> values will be skipped automatically.
                    </p>
                </div>

                <div class="form-actions" style="margin-top:24px;">
                    <button type="submit" class="btn btn-green">&#8679; Import Records</button>
                    <a href="?view=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php endif; ?>

    </main>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
