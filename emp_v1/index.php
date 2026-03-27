<?php //-- this is a test system only
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
        <p>Make sure you have run setup_database.sql in phpMyAdmin first.</p>
    </div>');
}

mysqli_set_charset($conn, 'utf8');

// ─── HELPER: normalise date ────────────────────────────────────────
function normalise_date($val) {
    $val = trim($val);
    if (is_numeric($val)) {
        $unix = ($val - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    $ts = strtotime($val);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }
    return $val;
}

// ─── HELPER: get field with alias fallbacks ────────────────────────
function get_field($row, $keys) {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim($row[$k]) !== '') {
            return trim($row[$k]);
        }
    }
    return '';
}

// ─── HANDLE ACTIONS ───────────────────────────────────────────────
$message     = '';
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

            $check = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$employee_id'");
            if (mysqli_num_rows($check) > 0) {
                $message     = "Employee ID '$employee_id' already exists in the database.";
                $messageType = 'error';
            } else {
                $sql = "INSERT INTO res (employee_id, name, department, position, email, hire_date, resign_date, reason)
                        VALUES ('$employee_id','$name','$department','$position','$email','$hire_date','$resign_date','$reason')";
                if (mysqli_query($conn, $sql)) {
                    $message     = "Employee '$name' (ID: $employee_id) has been added successfully.";
                    $messageType = 'success';
                } else {
                    $message     = "Error adding employee: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            }

        // ── DELETE ──────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'delete') {
            $id  = (int)$_POST['delete_id'];
            $res = mysqli_query($conn, "SELECT name, employee_id FROM res WHERE id = $id");
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                if (mysqli_query($conn, "DELETE FROM res WHERE id = $id")) {
                    $message     = "Employee '{$row['name']}' (ID: {$row['employee_id']}) has been removed.";
                    $messageType = 'info';
                } else {
                    $message     = "Error deleting: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            } else {
                $message     = "Employee not found.";
                $messageType = 'error';
            }

        // ── CSV IMPORT ──────────────────────────────────────────────
        } elseif ($_POST['action'] === 'import_csv') {

            $import_ok = true;

            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $message     = "No file uploaded or an upload error occurred.";
                $messageType = 'error';
                $import_ok   = false;
            }

            if ($import_ok) {
                $origName = $_FILES['csv_file']['name'];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $message     = "Please upload a .csv file. In Excel: File > Save As > CSV (Comma delimited).";
                    $messageType = 'error';
                    $import_ok   = false;
                }
            }

            if ($import_ok) {
                $tmpFile = $_FILES['csv_file']['tmp_name'];
                $handle  = fopen($tmpFile, 'r');

                if ($handle === false) {
                    $message     = "Could not open the uploaded file.";
                    $messageType = 'error';
                    $import_ok   = false;
                } else {
                    $rows   = array();
                    $header = null;

                    while (($line = fgetcsv($handle)) !== false) {
                        if ($header === null) {
                            $line[0] = str_replace("\xEF\xBB\xBF", '', $line[0]);
                            $header  = array_map('strtolower', array_map('trim', $line));
                        } else {
                            if (count($line) >= count($header)) {
                                $rows[] = array_combine($header, array_slice($line, 0, count($header)));
                            }
                        }
                    }
                    fclose($handle);

                    $imported = 0;
                    $skipped  = 0;
                    $errors   = array();
                    $rowNum   = 1;

                    foreach ($rows as $row) {
                        $rowNum++;

                        $emp_id     = get_field($row, array('employee_id', 'emp_id'));
                        $r_name     = get_field($row, array('name', 'full_name'));
                        $r_dept     = get_field($row, array('department', 'dept'));
                        $r_position = get_field($row, array('position', 'job_title', 'title'));
                        $r_email    = get_field($row, array('email', 'email_address'));
                        $r_hire     = get_field($row, array('hire_date', 'hiredate', 'date_hired'));
                        $r_resign   = get_field($row, array('resign_date', 'resigndate', 'date_resigned'));
                        $r_reason   = get_field($row, array('reason', 'resignation_reason'));

                        if ($emp_id === '' && $r_name === '') {
                            continue;
                        }

                        if ($emp_id === '' || $r_name === '' || $r_dept === '' || $r_position === '' || $r_hire === '' || $r_resign === '') {
                            $skipped++;
                            $errors[] = "Row $rowNum: Missing required field(s) - employee_id, name, department, position, hire_date, resign_date are all required.";
                            continue;
                        }

                        $r_hire   = normalise_date($r_hire);
                        $r_resign = normalise_date($r_resign);

                        $emp_id     = mysqli_real_escape_string($conn, $emp_id);
                        $r_name     = mysqli_real_escape_string($conn, $r_name);
                        $r_dept     = mysqli_real_escape_string($conn, $r_dept);
                        $r_position = mysqli_real_escape_string($conn, $r_position);
                        $r_email    = mysqli_real_escape_string($conn, $r_email);
                        $r_reason   = mysqli_real_escape_string($conn, $r_reason);

                        $dup = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$emp_id'");
                        if (mysqli_num_rows($dup) > 0) {
                            $skipped++;
                            $errors[] = "Row $rowNum: Employee ID '$emp_id' already exists - skipped.";
                            continue;
                        }

                        $sql = "INSERT INTO res (employee_id, name, department, position, email, hire_date, resign_date, reason)
                                VALUES ('$emp_id','$r_name','$r_dept','$r_position','$r_email','$r_hire','$r_resign','$r_reason')";
                        if (mysqli_query($conn, $sql)) {
                            $imported++;
                        } else {
                            $skipped++;
                            $errors[] = "Row $rowNum: DB error - " . mysqli_error($conn);
                        }
                    }

                    $message = "Import complete: <strong>$imported</strong> record(s) added, <strong>$skipped</strong> skipped.";
                    if (!empty($errors)) {
                        $message .= "<ul style='margin-top:8px;padding-left:18px;font-size:0.82rem'>";
                        foreach ($errors as $err) {
                            $message .= "<li>" . htmlspecialchars($err) . "</li>";
                        }
                        $message .= "</ul>";
                    }
                    $messageType = ($skipped > 0) ? 'info' : 'success';
                }
            }
        }
    }
}

// ─── FETCH DATA ───────────────────────────────────────────────────
$employees = array();
$result    = mysqli_query($conn, "SELECT * FROM res ORDER BY resign_date DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}

$totalResigned = count($employees);

$deptCounts = array();
foreach ($employees as $emp) {
    $dept = $emp['department'];
    $deptCounts[$dept] = isset($deptCounts[$dept]) ? $deptCounts[$dept] + 1 : 1;
}

$recent       = 0;
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
foreach ($employees as $emp) {
    if ($emp['resign_date'] >= $sixMonthsAgo) {
        $recent++;
    }
}

$totalDays = 0;
foreach ($employees as $emp) {
    $hire      = new DateTime($emp['hire_date']);
    $resign    = new DateTime($emp['resign_date']);
    $totalDays += $hire->diff($resign)->days;
}
$avgTenureYears = ($totalResigned > 0) ? round($totalDays / $totalResigned / 365, 1) : 0;

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
            --ink:     #0d0d0d;
            --paper:   #f5f2eb;
            --card:    #ffffff;
            --accent:  #c1440e;
            --accent2: #e8855a;
            --muted:   #888;
            --border:  #e2ddd5;
            --success: #2d7a4f;
            --info:    #1e5fa3;
            --shadow:  0 2px 16px rgba(0,0,0,0.07);
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
        }

        .layout { display:-webkit-box; display:flex; min-height:100vh; }

        .sidebar {
            width: 240px;
            background: var(--ink);
            color: #fff;
            -webkit-box-flex: 0;
            flex-shrink: 0;
            display: -webkit-box;
            display: flex;
            -webkit-box-orient: vertical;
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

        .sidebar-nav { padding:16px 0; -webkit-box-flex:1; flex:1; }

        .nav-item {
            display: -webkit-box;
            display: flex;
            -webkit-box-align: center;
            align-items: center;
            padding: 11px 24px;
            color: #aaa;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            gap: 10px;
            border-left: 3px solid transparent;
        }

        .nav-item:hover  { color:#fff; background:#1a1a1a; }
        .nav-item.active { color:#fff; border-left-color:var(--accent); background:#1a1a1a; }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #2a2a2a;
            font-size: 0.75rem;
            color: #555;
        }

        .main { -webkit-box-flex:1; flex:1; padding:36px 40px; overflow-y:auto; }

        .page-header { margin-bottom:32px; }

        .page-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .page-header p { color:var(--muted); font-size:0.9rem; margin-top:4px; }

        .alert {
            padding: 13px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.88rem;
            font-weight: 500;
        }
        .alert-success { background:#e9f7ef; color:var(--success); border:1px solid #b7dfc9; }
        .alert-info    { background:#e8f0fb; color:var(--info);    border:1px solid #b8d0ef; }
        .alert-error   { background:#fdf0ee; color:var(--accent);  border:1px solid #f5c3b5; }

        .stats-grid {
            display: -webkit-box;
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
            -webkit-box-flex: 1;
            flex: 1;
            min-width: 160px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top:0; left:0; right:0;
            height: 3px;
        }
        .stat-card.orange::before { background:var(--accent); }
        .stat-card.blue::before   { background:var(--info); }
        .stat-card.green::before  { background:var(--success); }
        .stat-card.purple::before { background:#7c4dff; }

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

        .stat-sub { font-size:0.78rem; color:var(--muted); margin-top:6px; }

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

        .dept-bar-row { margin-bottom:14px; }

        .dept-bar-label {
            display: -webkit-box;
            display: flex;
            -webkit-box-pack: justify;
            justify-content: space-between;
            font-size: 0.83rem;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .dept-bar-track { background:#f0ece4; border-radius:4px; height:8px; overflow:hidden; }
        .dept-bar-fill  { height:100%; border-radius:4px; background:var(--accent); }

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
            display: -webkit-box;
            display: flex;
            -webkit-box-align: center;
            align-items: center;
            -webkit-box-pack: justify;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .table-header-actions { display:-webkit-box; display:flex; gap:10px; }

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
        }

        .btn-primary   { background:var(--accent); color:#fff; }
        .btn-green     { background:#2d7a4f; color:#fff; }

        .btn-danger {
            background: transparent;
            color: var(--accent);
            border: 1px solid #f5c3b5;
            font-size: 0.8rem;
            padding: 6px 12px;
        }

        .btn-secondary {
            background: transparent;
            color: var(--muted);
            border: 1.5px solid var(--border);
        }

        table { width:100%; border-collapse:collapse; font-size:0.875rem; }

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

        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover td { background:#faf8f5; }

        .emp-name  { font-weight:600; }
        .emp-email { font-size:0.78rem; color:var(--muted); margin-top:2px; }

        .emp-id-badge {
            display: inline-block;
            background: #f0ece4;
            color: #555;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            font-family: monospace;
            letter-spacing: 0.04em;
        }

        .badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:0.72rem; font-weight:600; }
        .badge-resigned { background:#fdf0ee; color:var(--accent); }

        .form-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 720px;
        }

        .form-grid { display:-webkit-box; display:flex; flex-wrap:wrap; gap:18px; }

        .form-group {
            display: -webkit-box;
            display: flex;
            -webkit-box-orient: vertical;
            flex-direction: column;
            gap: 6px;
            width: calc(50% - 9px);
        }
        .form-group.full { width:100%; }

        label { font-size:0.8rem; font-weight:600; }

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

        textarea { resize:vertical; min-height:80px; }

        .form-actions { margin-top:24px; display:-webkit-box; display:flex; gap:12px; }

        .import-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 680px;
        }

        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 40px 24px;
            text-align: center;
            background: #faf8f5;
            cursor: pointer;
            position: relative;
        }

        .upload-zone input[type=file] {
            position: absolute;
            top:0; left:0;
            width:100%; height:100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon { font-size:2.5rem; margin-bottom:10px; opacity:0.5; }
        .upload-zone p { font-size:0.9rem; color:var(--muted); }
        .upload-zone strong { color:var(--ink); }

        .file-name-display {
            margin-top: 10px;
            font-size: 0.82rem;
            color: var(--accent);
            font-weight: 600;
            min-height: 18px;
        }

        .info-box {
            background: #e8f0fb;
            border: 1px solid #b8d0ef;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 0.85rem;
            color: var(--info);
            margin-top: 20px;
            line-height: 1.6;
        }

        .info-box strong { display:block; margin-bottom:6px; }

        .col-pill {
            display: inline-block;
            background: #d0e2f7;
            color: var(--info);
            font-size: 0.73rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 4px;
            font-family: monospace;
            margin: 2px 3px 2px 0;
        }

        .col-pill.optional { background:#eee; color:#666; }

        .empty { text-align:center; padding:60px 20px; color:var(--muted); }
        .empty-icon { font-size:3rem; margin-bottom:12px; opacity:0.4; }
        .empty p { font-size:0.9rem; }
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
                &#8679; Import CSV
            </a>
        </div>
        <div class="sidebar-footer">
            <?php echo $totalResigned; ?> record<?php echo ($totalResigned !== 1) ? 's' : ''; ?> in `res`
        </div>
    </nav>

    <main class="main">

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
        <div class="page-header">
            <h2>Dashboard</h2>
            <p>Overview of resigned employee records &mdash; table <strong>res</strong></p>
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
                    <div class="dept-bar-fill" style="width:<?php echo round($count / $totalResigned * 100); ?>%"></div>
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
                        <td><span class="emp-id-badge"><?php echo htmlspecialchars(isset($emp['employee_id']) ? $emp['employee_id'] : '-'); ?></span></td>
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
        <div class="page-header">
            <h2>All Records</h2>
            <p><?php echo $totalResigned; ?> resigned employee<?php echo ($totalResigned !== 1) ? 's' : ''; ?> in database</p>
        </div>

        <div class="table-card">
            <div class="table-header">
                <span class="section-title" style="margin:0">Employee Records</span>
                <div class="table-header-actions">
                    <a href="?view=import" class="btn btn-green">&#8679; Import CSV</a>
                    <a href="?view=add"    class="btn btn-primary">&#43; Add Employee</a>
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
                        <td><span class="emp-id-badge"><?php echo htmlspecialchars(isset($emp['employee_id']) ? $emp['employee_id'] : '-'); ?></span></td>
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
                                <input type="hidden" name="action"    value="delete">
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
        <div class="page-header">
            <h2>Add Resigned Employee</h2>
            <p>Record will be saved to table <strong>res</strong></p>
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
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="maria@company.com">
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
        <div class="page-header">
            <h2>Import CSV</h2>
            <p>Upload a <strong>.csv</strong> file to mass-import resigned employee records</p>
        </div>

        <div class="import-card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">

                <div class="upload-zone">
                    <input type="file" name="csv_file" id="csvFile" accept=".csv"
                           onchange="document.getElementById('fileNameDisplay').textContent = (this.files && this.files[0]) ? this.files[0].name : ''">
                    <div class="upload-icon">&#128196;</div>
                    <p><strong>Click to browse</strong> your CSV file</p>
                    <p>Only .csv files are supported</p>
                    <div class="file-name-display" id="fileNameDisplay"></div>
                </div>

                <div class="info-box">
                    <strong>&#128274; Required column headers (first row of your CSV):</strong>
                    <span class="col-pill">employee_id</span>
                    <span class="col-pill">name</span>
                    <span class="col-pill">department</span>
                    <span class="col-pill">position</span>
                    <span class="col-pill">hire_date</span>
                    <span class="col-pill">resign_date</span>
                    <span class="col-pill optional">email</span>
                    <span class="col-pill optional">reason</span>
                    <p style="margin-top:10px;font-size:0.8rem;">
                        Grey pills are optional. Dates accepted as <strong>YYYY-MM-DD</strong> or <strong>MM/DD/YYYY</strong>.
                        Duplicate employee IDs are skipped automatically.<br><br>
                        <strong>To export from Excel:</strong> File &gt; Save As &gt; CSV (Comma delimited) (.csv)
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
