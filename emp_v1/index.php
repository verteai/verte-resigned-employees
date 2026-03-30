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
        <p>Make sure you have run setup_database.sql in phpMyAdmin first.</p>
    </div>');
}

mysqli_set_charset($conn, 'utf8');

// ─── HELPERS ──────────────────────────────────────────────────────
function normalise_date($val) {
    $val = trim($val);
    if ($val === '') return '';
    if (is_numeric($val)) {
        $unix = ($val - 25569) * 86400;
        return date('Y-m-d', $unix);
    }
    $ts = strtotime($val);
    if ($ts !== false) return date('Y-m-d', $ts);
    return $val;
}

function get_field($row, $keys) {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim($row[$k]) !== '') return trim($row[$k]);
    }
    return '';
}

function chk($val) {
    $v = strtolower(trim($val));
    return ($v === '1' || $v === 'yes' || $v === 'true' || $v === 'on') ? 1 : 0;
}

function tick($val) {
    return $val
        ? '<span class="tick tick-yes">&#10003;</span>'
        : '<span class="tick tick-no">&#8212;</span>';
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ─── DOWNLOAD CSV ─────────────────────────────────────────────────
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resigned_employees_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM for Excel
    fputcsv($out, array(
        'Week', 'Effective Date', 'Employee ID', 'Name', 'Hire Date',
        'Group', 'HR Summary', 'Department', 'Position',
        'Clearance Date', 'Wifi', 'GoPlus', 'Imapps', 'XO/Scanpack',
        'Email Address', 'AD/Email'
    ));
    $res = mysqli_query($conn, "SELECT * FROM res ORDER BY effective_date DESC");
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, array(
            isset($row['week'])           ? $row['week']           : '',
            isset($row['effective_date']) ? $row['effective_date'] : '',
            isset($row['employee_id'])    ? $row['employee_id']    : '',
            $row['name'],
            isset($row['date_hired'])     ? $row['date_hired']     : '',
            isset($row['grp'])            ? $row['grp']            : '',
            isset($row['hr_summary'])     ? $row['hr_summary']     : '',
            $row['department'],
            $row['position'],
            isset($row['clearance_date']) ? $row['clearance_date'] : '',
            isset($row['wifi'])           ? ($row['wifi']        ? 'Yes' : 'No') : 'No',
            isset($row['goplus'])         ? ($row['goplus']      ? 'Yes' : 'No') : 'No',
            isset($row['imapps'])         ? ($row['imapps']      ? 'Yes' : 'No') : 'No',
            isset($row['xo_scanpack'])    ? ($row['xo_scanpack'] ? 'Yes' : 'No') : 'No',
            isset($row['email_address'])  ? $row['email_address']  : '',
            isset($row['ad_email'])       ? $row['ad_email']       : ''
        ));
    }
    fclose($out);
    mysqli_close($conn);
    exit;
}

// ─── HANDLE POST ACTIONS ──────────────────────────────────────────
$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // ── ADD ──────────────────────────────────────────────────
        if ($_POST['action'] === 'add') {
            $week           = mysqli_real_escape_string($conn, trim($_POST['week']));
            $effective_date = mysqli_real_escape_string($conn, trim($_POST['effective_date']));
            $employee_id    = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
            $name           = mysqli_real_escape_string($conn, trim($_POST['name']));
            $date_hired     = mysqli_real_escape_string($conn, trim($_POST['date_hired']));
            $grp            = mysqli_real_escape_string($conn, trim($_POST['grp']));
            $hr_summary     = mysqli_real_escape_string($conn, trim($_POST['hr_summary']));
            $department     = mysqli_real_escape_string($conn, trim($_POST['department']));
            $position       = mysqli_real_escape_string($conn, trim($_POST['position']));
            $clearance_date = mysqli_real_escape_string($conn, trim($_POST['clearance_date']));
            $wifi           = isset($_POST['wifi'])        ? 1 : 0;
            $goplus         = isset($_POST['goplus'])      ? 1 : 0;
            $imapps         = isset($_POST['imapps'])      ? 1 : 0;
            $xo_scanpack    = isset($_POST['xo_scanpack']) ? 1 : 0;
            $ad_email       = mysqli_real_escape_string($conn, trim($_POST['ad_email']));
            $email_address  = mysqli_real_escape_string($conn, trim($_POST['email_address']));

            $check = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$employee_id' AND effective_date = '$effective_date'");
            if (mysqli_num_rows($check) > 0) {
                $message     = "A record for Employee ID '$employee_id' with the same Effective Date already exists.";
                $messageType = 'error';
            } else {
                $cd_val = ($clearance_date !== '') ? "'$clearance_date'" : 'NULL';
                $sql = "INSERT INTO res
                        (week, effective_date, employee_id, name, date_hired, grp, hr_summary, department, position, clearance_date, wifi, goplus, imapps, xo_scanpack, email_address, ad_email)
                        VALUES
                        ('$week','$effective_date','$employee_id','$name','$date_hired','$grp','$hr_summary','$department','$position',$cd_val,$wifi,$goplus,$imapps,$xo_scanpack,'$email_address','$ad_email')";
                if (mysqli_query($conn, $sql)) {
                    $message     = "Record for '$name' (ID: $employee_id) added successfully.";
                    $messageType = 'success';
                } else {
                    $message     = "Error adding record: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            }

        // ── EDIT ─────────────────────────────────────────────────
        } elseif ($_POST['action'] === 'edit') {
            $id             = (int)$_POST['edit_id'];
            $week           = mysqli_real_escape_string($conn, trim($_POST['week']));
            $effective_date = mysqli_real_escape_string($conn, trim($_POST['effective_date']));
            $employee_id    = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
            $name           = mysqli_real_escape_string($conn, trim($_POST['name']));
            $date_hired     = mysqli_real_escape_string($conn, trim($_POST['date_hired']));
            $grp            = mysqli_real_escape_string($conn, trim($_POST['grp']));
            $hr_summary     = mysqli_real_escape_string($conn, trim($_POST['hr_summary']));
            $department     = mysqli_real_escape_string($conn, trim($_POST['department']));
            $position       = mysqli_real_escape_string($conn, trim($_POST['position']));
            $clearance_date = mysqli_real_escape_string($conn, trim($_POST['clearance_date']));
            $wifi           = isset($_POST['wifi'])        ? 1 : 0;
            $goplus         = isset($_POST['goplus'])      ? 1 : 0;
            $imapps         = isset($_POST['imapps'])      ? 1 : 0;
            $xo_scanpack    = isset($_POST['xo_scanpack']) ? 1 : 0;
            $ad_email       = mysqli_real_escape_string($conn, trim($_POST['ad_email']));
            $email_address  = mysqli_real_escape_string($conn, trim($_POST['email_address']));

            $dup = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$employee_id' AND effective_date = '$effective_date' AND id != $id");
            if (mysqli_num_rows($dup) > 0) {
                $message     = "Another record for Employee ID '$employee_id' with the same Effective Date already exists.";
                $messageType = 'error';
            } else {
                $cd_val = ($clearance_date !== '') ? "'$clearance_date'" : 'NULL';
                $sql = "UPDATE res SET
                            week           = '$week',
                            effective_date = '$effective_date',
                            employee_id    = '$employee_id',
                            name           = '$name',
                            date_hired     = '$date_hired',
                            grp            = '$grp',
                            hr_summary     = '$hr_summary',
                            department     = '$department',
                            position       = '$position',
                            clearance_date = $cd_val,
                            wifi           = $wifi,
                            goplus         = $goplus,
                            imapps         = $imapps,
                            xo_scanpack    = $xo_scanpack,
                            email_address  = '$email_address',
                            ad_email       = '$ad_email'
                        WHERE id = $id";
                if (mysqli_query($conn, $sql)) {
                    $message     = "Record for '$name' (ID: $employee_id) updated successfully.";
                    $messageType = 'success';
                } else {
                    $message     = "Error updating record: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            }

        // ── DELETE ───────────────────────────────────────────────
        } elseif ($_POST['action'] === 'delete') {
            $id  = (int)$_POST['delete_id'];
            $res = mysqli_query($conn, "SELECT name, employee_id FROM res WHERE id = $id");
            $row = mysqli_fetch_assoc($res);
            if ($row) {
                if (mysqli_query($conn, "DELETE FROM res WHERE id = $id")) {
                    $message     = "Record for '{$row['name']}' (ID: {$row['employee_id']}) removed.";
                    $messageType = 'info';
                } else {
                    $message     = "Error deleting: " . mysqli_error($conn);
                    $messageType = 'error';
                }
            } else {
                $message     = "Record not found.";
                $messageType = 'error';
            }

        // ── CSV IMPORT ───────────────────────────────────────────
        } elseif ($_POST['action'] === 'import_csv') {
            $import_ok = true;

            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $message     = "No file uploaded or upload error occurred.";
                $messageType = 'error';
                $import_ok   = false;
            }

            if ($import_ok) {
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    $message     = "Please upload a .csv file. In Excel: File > Save As > CSV (Comma delimited).";
                    $messageType = 'error';
                    $import_ok   = false;
                }
            }

            if ($import_ok) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
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

                        $r_week    = get_field($row, array('week'));
                        $r_effdate = get_field($row, array('effective_date','effective date','effdate'));
                        $r_empid   = get_field($row, array('employee_id','employee id','emp_id'));
                        $r_name    = get_field($row, array('name','full_name'));
                        $r_hired   = get_field($row, array('date_hired','date hired','hire_date'));
                        $r_grp     = get_field($row, array('group','grp'));
                        $r_hrsumm  = get_field($row, array('hr_summary','hr summary','hrsummary'));
                        $r_dept    = get_field($row, array('department','dept'));
                        $r_pos     = get_field($row, array('position','job_title','title'));
                        $r_clear   = get_field($row, array('clearance_date','clearance date'));
                        $r_wifi    = chk(get_field($row, array('wifi')));
                        $r_goplus  = chk(get_field($row, array('goplus','go+')));
                        $r_imapps  = chk(get_field($row, array('imapps')));
                        $r_xo      = chk(get_field($row, array('xo_scanpack','xo/scanpack','xo')));
                        $r_email   = get_field($row, array('email_address','email address','emailaddress','email'));
                        $r_admail  = get_field($row, array('ad_email','ad/email'));

                        if ($r_empid === '' && $r_name === '') continue;

                        if ($r_empid === '' || $r_name === '' || $r_dept === '' || $r_pos === '' || $r_effdate === '') {
                            $skipped++;
                            $errors[] = "Row $rowNum: Missing required fields (employee_id, name, department, position, effective_date).";
                            continue;
                        }

                        $r_effdate = normalise_date($r_effdate);
                        $r_hired   = normalise_date($r_hired);
                        $r_clear   = normalise_date($r_clear);

                        $r_week   = mysqli_real_escape_string($conn, $r_week);
                        $r_empid  = mysqli_real_escape_string($conn, $r_empid);
                        $r_name   = mysqli_real_escape_string($conn, $r_name);
                        $r_grp    = mysqli_real_escape_string($conn, $r_grp);
                        $r_hrsumm = mysqli_real_escape_string($conn, $r_hrsumm);
                        $r_dept   = mysqli_real_escape_string($conn, $r_dept);
                        $r_pos    = mysqli_real_escape_string($conn, $r_pos);

                        $dup = mysqli_query($conn, "SELECT id FROM res WHERE employee_id = '$r_empid' AND effective_date = '$r_effdate'");
                        if (mysqli_num_rows($dup) > 0) {
                            $skipped++;
                            $errors[] = "Row $rowNum: Employee ID '$r_empid' with effective date '$r_effdate' already exists - skipped.";
                            continue;
                        }

                        $cd_val = ($r_clear !== '') ? "'$r_clear'" : 'NULL';
                        $sql = "INSERT INTO res
                                (week, effective_date, employee_id, name, date_hired, grp, hr_summary, department, position, clearance_date, wifi, goplus, imapps, xo_scanpack, email_address, ad_email)
                                VALUES
                                ('$r_week','$r_effdate','$r_empid','$r_name','$r_hired','$r_grp','$r_hrsumm','$r_dept','$r_pos',$cd_val,$r_wifi,$r_goplus,$r_imapps,$r_xo,'$r_email','$r_admail')";
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
                        foreach ($errors as $err) $message .= "<li>" . e($err) . "</li>";
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
/*
$result    = mysqli_query($conn, "SELECT * FROM res ORDER BY effective_date DESC, week DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}
*/

$result = mysqli_query($conn, "SELECT * FROM res ORDER BY effective_date DESC, week DESC");

if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}

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
    if (isset($emp['effective_date']) && $emp['effective_date'] >= $sixMonthsAgo) $recent++;
}

// Group breakdown
$grpCounts = array();
foreach ($employees as $emp) {
    $g = isset($emp['grp']) && $emp['grp'] !== '' ? $emp['grp'] : 'Unassigned';
    $grpCounts[$g] = isset($grpCounts[$g]) ? $grpCounts[$g] + 1 : 1;
}

// ─── VIEW ROUTING ─────────────────────────────────────────────────
$view     = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$edit_emp = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if ($messageType === 'success') {
        $view = 'list';
    } else {
        $view    = 'edit';
        $edit_id = (int)$_POST['edit_id'];
        $er      = mysqli_query($conn, "SELECT * FROM res WHERE id = $edit_id");
        $edit_emp = mysqli_fetch_assoc($er);
        if ($edit_emp) {
            $edit_emp['week']           = $_POST['week'];
            $edit_emp['effective_date'] = $_POST['effective_date'];
            $edit_emp['employee_id']    = $_POST['employee_id'];
            $edit_emp['name']           = $_POST['name'];
            $edit_emp['date_hired']     = $_POST['date_hired'];
            $edit_emp['grp']            = $_POST['grp'];
            $edit_emp['hr_summary']     = $_POST['hr_summary'];
            $edit_emp['department']     = $_POST['department'];
            $edit_emp['position']       = $_POST['position'];
            $edit_emp['clearance_date'] = $_POST['clearance_date'];
            $edit_emp['wifi']           = isset($_POST['wifi'])        ? 1 : 0;
            $edit_emp['goplus']         = isset($_POST['goplus'])      ? 1 : 0;
            $edit_emp['imapps']         = isset($_POST['imapps'])      ? 1 : 0;
            $edit_emp['xo_scanpack']    = isset($_POST['xo_scanpack']) ? 1 : 0;
            $edit_emp['ad_email']        = isset($_POST['ad_email']) ? $_POST['ad_email'] : '';
            $edit_emp['email_address']   = isset($_POST['email_address']) ? $_POST['email_address'] : '';
        }
    }
}

if ($view === 'edit' && $edit_emp === null && isset($_GET['id'])) {
    $edit_id  = (int)$_GET['id'];
    $er       = mysqli_query($conn, "SELECT * FROM res WHERE id = $edit_id");
    $edit_emp = mysqli_fetch_assoc($er);
    if (!$edit_emp) {
        $message     = "Record not found.";
        $messageType = 'error';
        $view        = 'list';
    }
}
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

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            background: var(--ink);
            color: #fff;
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

        .sidebar-nav { padding:16px 0; flex:1; }

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

        /* ── MAIN ── */
        .main { flex:1; padding:36px 40px; overflow-y:auto; }

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

        /* ── STATS ── */
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

        /* ── DEPT / GROUP BARS ── */
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

        /* ── TABLE ── */
        .table-card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .table-wrapper { overflow-x:auto; }

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

        .table-header-actions { display:-webkit-box; display:flex; gap:10px; flex-wrap:wrap; }

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
        .btn-blue      { background:var(--info); color:#fff; }

        .btn-danger {
            background: transparent;
            color: var(--accent);
            border: 1px solid #f5c3b5;
            font-size: 0.8rem;
            padding: 6px 12px;
        }

        .btn-edit {
            background: transparent;
            color: var(--info);
            border: 1px solid #b8d0ef;
            font-size: 0.8rem;
            padding: 6px 12px;
        }

        .btn-secondary {
            background: transparent;
            color: var(--muted);
            border: 1.5px solid var(--border);
        }

        table { width:100%; border-collapse:collapse; font-size:0.82rem; }

        thead th {
            padding: 11px 12px;
            text-align: left;
            background: #faf8f5;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover td { background:#faf8f5; }

        .emp-name  { font-weight:600; font-size:0.875rem; }
        .emp-sub   { font-size:0.75rem; color:var(--muted); margin-top:2px; }

        .emp-id-badge {
            display: inline-block;
            background: #f0ece4;
            color: #555;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            font-family: monospace;
            letter-spacing: 0.04em;
        }

        .week-badge {
            display: inline-block;
            background: #e8f0fb;
            color: var(--info);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.04em;
        }

        .grp-badge {
            display: inline-block;
            background: #f3eeff;
            color: #5c35b5;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:0.72rem; font-weight:600; }
        .badge-resigned { background:#fdf0ee; color:var(--accent); }

        .tick { display:inline-block; font-size:0.9rem; font-weight:700; width:22px; text-align:center; }
        .tick-yes { color:var(--success); }
        .tick-no  { color:#ccc; }

        .ad-badge { display:inline-block; font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; }
        .ad-active   { background:#d4edda; color:#155724; }
        .ad-disabled { background:#fdf0ee; color:#c1440e; }

        /* ── FORM ── */
        .form-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 780px;
        }

        .form-section-label {
            font-family: 'Syne', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin: 24px 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            width: 100%;
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
        .form-group.full  { width:100%; }
        .form-group.third { width:calc(33.333% - 12px); }

        label { font-size:0.8rem; font-weight:600; }

        input[type=text], input[type=email], input[type=date],
        input[type=number], select, textarea {
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

        input[type=text]:focus, input[type=date]:focus,
        input[type=number]:focus, select:focus, textarea:focus {
            border-color: var(--accent);
            background: #fff;
        }

        textarea { resize:vertical; min-height:80px; }

        /* ── CHECKBOXES ── */
        .checkbox-group {
            display: -webkit-box;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 4px 0;
        }

        .checkbox-item {
            display: -webkit-box;
            display: flex;
            -webkit-box-align: center;
            align-items: center;
            gap: 8px;
            background: #faf8f5;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .checkbox-item input[type=checkbox] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0;
            padding: 0;
            border: none;
            background: none;
        }

        .checkbox-item:hover { border-color:var(--accent); background:#fff; }

        .form-actions { margin-top:24px; display:-webkit-box; display:flex; gap:12px; }

        /* ── IMPORT PAGE ── */
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

        .action-cell { display:-webkit-box; display:flex; gap:6px; align-items:center; }
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
        <!-- ═══════ DASHBOARD ══════════════════════════════════════ -->
        <div class="page-header">
            <h2>Dashboard</h2>
            <p>Overview of resigned employee records</p>
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
                <div class="stat-sub">By effective date</div>
            </div>
            <div class="stat-card green">
                <div class="stat-label">Departments</div>
                <div class="stat-value"><?php echo count($deptCounts); ?></div>
                <div class="stat-sub">Affected departments</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-label">Groups</div>
                <div class="stat-value"><?php echo count($grpCounts); ?></div>
                <div class="stat-sub">Affected groups</div>
            </div>
        </div>

        <?php if (!empty($deptCounts)): ?>
        <div class="dept-section">
            <div class="section-title">Resignations by Department</div>
            <?php foreach ($deptCounts as $dept => $count): ?>
            <div class="dept-bar-row">
                <div class="dept-bar-label">
                    <span><?php echo e($dept); ?></span>
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
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Effective Date</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Group</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_emps as $emp): ?>
                    <tr>
                        <td><span class="week-badge"><?php echo e(isset($emp['week']) ? $emp['week'] : '-'); ?></span></td>
                        <td><?php echo e(isset($emp['effective_date']) ? $emp['effective_date'] : '-'); ?></td>
                        <td><span class="emp-id-badge"><?php echo e(isset($emp['employee_id']) ? $emp['employee_id'] : '-'); ?></span></td>
                        <td>
                            <div class="emp-name"><?php echo e($emp['name']); ?></div>
                            <div class="emp-sub"><?php echo e(isset($emp['hr_summary']) ? $emp['hr_summary'] : ''); ?></div>
                        </td>
                        <td><?php echo isset($emp['grp']) && $emp['grp'] !== '' ? '<span class="grp-badge">' . e($emp['grp']) . '</span>' : '-'; ?></td>
                        <td><?php echo e($emp['department']); ?></td>
                        <td><?php echo e($emp['position']); ?></td>
                        <td><span class="badge badge-resigned">Resigned</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($view === 'list'): ?>
        <!-- ═══════ ALL RECORDS ════════════════════════════════════ -->
        <div class="page-header">
            <h2>All Records</h2>
            <p><?php echo $totalResigned; ?> resigned employee<?php echo ($totalResigned !== 1) ? 's' : ''; ?> in database</p>
        </div>

        <div class="table-card">
            <div class="table-header">
                <span class="section-title" style="margin:0">Employee Records</span>
                <div class="table-header-actions">
                    <a href="?download=csv" class="btn btn-blue">&#8595; Download CSV</a>
                    <a href="?view=import"  class="btn btn-green">&#8679; Import CSV</a>
                    <a href="?view=add"     class="btn btn-primary">&#43; Add Record</a>
                </div>
            </div>
            <?php if (empty($employees)): ?>
            <div class="empty">
                <div class="empty-icon">&#128203;</div>
                <p>No records yet. <a href="?view=add">Add one now.</a></p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Effective Date</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Date Hired</th>
                        <th>Group</th>
                        <th>HR Summary</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Clearance</th>
                        <th>WiFi</th>
                        <th>GO+</th>
                        <th>IMAPPS</th>
                        <th>XO/SCAN</th>
                        <th>Email Address</th>
                        <th>AD/Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <?php
                        $wifi_val = isset($emp['wifi'])        ? (int)$emp['wifi']        : 0;
                        $gop_val  = isset($emp['goplus'])      ? (int)$emp['goplus']      : 0;
                        $ima_val  = isset($emp['imapps'])      ? (int)$emp['imapps']      : 0;
                        $xo_val   = isset($emp['xo_scanpack']) ? (int)$emp['xo_scanpack'] : 0;
                    ?>
                    <tr>
                        <td><span class="week-badge"><?php echo e(isset($emp['week']) ? $emp['week'] : '-'); ?></span></td>
                        <td style="white-space:nowrap"><?php echo e(isset($emp['effective_date']) ? $emp['effective_date'] : '-'); ?></td>
                        <td><span class="emp-id-badge"><?php echo e(isset($emp['employee_id']) ? $emp['employee_id'] : '-'); ?></span></td>
                        <td>
                            <div class="emp-name"><?php echo e($emp['name']); ?></div>
                        </td>
                        <td style="white-space:nowrap;font-size:0.8rem"><?php echo e(isset($emp['date_hired']) ? $emp['date_hired'] : '-'); ?></td>
                        <td><?php echo isset($emp['grp']) && $emp['grp'] !== '' ? '<span class="grp-badge">' . e($emp['grp']) . '</span>' : '-'; ?></td>
                        <td style="max-width:130px;font-size:0.78rem;color:#666"><?php echo e(isset($emp['hr_summary']) ? $emp['hr_summary'] : ''); ?></td>
                        <td><?php echo e($emp['department']); ?></td>
                        <td><?php echo e($emp['position']); ?></td>
                        <td style="white-space:nowrap;font-size:0.8rem"><?php echo e(isset($emp['clearance_date']) && $emp['clearance_date'] ? $emp['clearance_date'] : '-'); ?></td>
                        <td style="text-align:center"><?php echo tick($wifi_val); ?></td>
                        <td style="text-align:center"><?php echo tick($gop_val); ?></td>
                        <td style="text-align:center"><?php echo tick($ima_val); ?></td>
                        <td style="text-align:center"><?php echo tick($xo_val); ?></td>
                        <td style="font-size:0.78rem"><?php echo e(isset($emp['email_address']) ? $emp['email_address'] : ''); ?></td>
                        <td><?php
                            $adv = isset($emp['ad_email']) ? $emp['ad_email'] : '';
                            if ($adv === 'Active')   echo '<span class="ad-badge ad-active">Active</span>';
                            elseif ($adv === 'Disabled') echo '<span class="ad-badge ad-disabled">Disabled</span>';
                            else echo '<span style="color:#ccc">&#8212;</span>';
                        ?></td>
                        <td>
                            <div class="action-cell">
                                <a href="?view=edit&id=<?php echo $emp['id']; ?>" class="btn btn-edit">&#9998; Edit</a>
                                <form method="POST" onsubmit="return confirm('Remove this record?')">
                                    <input type="hidden" name="action"    value="delete">
                                    <input type="hidden" name="delete_id" value="<?php echo $emp['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($view === 'add' || ($view === 'edit' && $edit_emp)): ?>
        <!-- ═══════ ADD / EDIT RECORD ══════════════════════════════ -->
        <?php
            $is_edit  = ($view === 'edit');
            $f_week   = $is_edit ? e($edit_emp['week'])                                          : '';
            $f_effdt  = $is_edit ? e(isset($edit_emp['effective_date']) ? $edit_emp['effective_date'] : '') : '';
            $f_empid  = $is_edit ? e($edit_emp['employee_id'])                                   : '';
            $f_name   = $is_edit ? e($edit_emp['name'])                                          : '';
            $f_hired  = $is_edit ? e(isset($edit_emp['date_hired'])     ? $edit_emp['date_hired']     : '') : '';
            $f_grp    = $is_edit ? e(isset($edit_emp['grp'])            ? $edit_emp['grp']            : '') : '';
            $f_hrsumm = $is_edit ? e(isset($edit_emp['hr_summary'])     ? $edit_emp['hr_summary']     : '') : '';
            $f_dept   = $is_edit ? $edit_emp['department']                                       : '';
            $f_pos    = $is_edit ? e($edit_emp['position'])                                      : '';
            $f_clear  = $is_edit ? e(isset($edit_emp['clearance_date']) ? $edit_emp['clearance_date'] : '') : '';
            $f_wifi   = $is_edit ? (int)$edit_emp['wifi']        : 0;
            $f_gop    = $is_edit ? (int)$edit_emp['goplus']      : 0;
            $f_ima    = $is_edit ? (int)$edit_emp['imapps']      : 0;
            $f_xo     = $is_edit ? (int)$edit_emp['xo_scanpack'] : 0;
            $f_admail = $is_edit ? e(isset($edit_emp['ad_email']) ? $edit_emp['ad_email'] : '') : '';
            $f_email  = $is_edit ? e(isset($edit_emp['email_address']) ? $edit_emp['email_address'] : '') : '';
        ?>
        <div class="page-header">
            <h2><?php echo $is_edit ? 'Edit Record' : 'Add Resigned Employee'; ?></h2>
            <p><?php echo $is_edit ? 'Updating record for <strong>' . e($edit_emp['name']) . '</strong>' : 'Record will be saved to table <strong>res</strong>'; ?></p>
        </div>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" value="<?php echo $edit_emp['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">

                    <div class="form-section-label">Resignation Info</div>

                    <div class="form-group third">
                        <label for="week">Week</label>
                        <input type="text" id="week" name="week" placeholder="e.g. Week 1" value="<?php echo $f_week; ?>">
                    </div>
                    <div class="form-group third">
                        <label for="effective_date">Effective Date *</label>
                        <input type="date" id="effective_date" name="effective_date" required value="<?php echo $f_effdt; ?>">
                    </div>
                    <div class="form-group third">
                        <label for="hr_summary">HR Summary</label>
                        <input type="text" id="hr_summary" name="hr_summary" placeholder="e.g. Resigned" value="<?php echo $f_hrsumm; ?>">
                    </div>

                    <div class="form-section-label">Employee Information</div>

                    <div class="form-group">
                        <label for="employee_id">Employee ID *</label>
                        <input type="text" id="employee_id" name="employee_id" required placeholder="e.g. EMP-00123" value="<?php echo $f_empid; ?>">
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required placeholder="e.g. Maria Santos" value="<?php echo $f_name; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_hired">Date Hired</label>
                        <input type="date" id="date_hired" name="date_hired" value="<?php echo $f_hired; ?>">
                    </div>
                    <div class="form-group">
                        <label for="grp">Group</label>
                        <input type="text" id="grp" name="grp" placeholder="e.g. Production" value="<?php echo $f_grp; ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select department...</option>
                            <?php
                            $depts = array('Engineering','Marketing','HR','Finance','Operations','Sales','IT','Legal','Production','Warehouse','Quality','Admin','Other');
                            foreach ($depts as $d) {
                                $sel = ($f_dept === $d) ? ' selected' : '';
                                echo '<option value="' . e($d) . '"' . $sel . '>' . e($d) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="position">Position / Job Title *</label>
                        <input type="text" id="position" name="position" required placeholder="e.g. Senior Developer" value="<?php echo $f_pos; ?>">
                    </div>

                    <div class="form-section-label">Clearance</div>

                    <div class="form-group">
                        <label for="clearance_date">Clearance Date</label>
                        <input type="date" id="clearance_date" name="clearance_date" value="<?php echo $f_clear; ?>">
                    </div>
                    <div class="form-group">
                        <!-- spacer -->
                    </div>

                    <div class="form-group full">
                        <label>Accounts Cleared</label>
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="wifi" value="1" <?php echo $f_wifi ? 'checked' : ''; ?>>
                                <span>WiFi</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="goplus" value="1" <?php echo $f_gop ? 'checked' : ''; ?>>
                                <span>GO+</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="imapps" value="1" <?php echo $f_ima ? 'checked' : ''; ?>>
                                <span>IMAPPS</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="xo_scanpack" value="1" <?php echo $f_xo ? 'checked' : ''; ?>>
                                <span>XO/SCANPACK</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email_address">Email Address</label>
                        <input type="email" id="email_address" name="email_address" placeholder="e.g. maria@company.com" value="<?php echo $f_email; ?>">
                    </div>
                    <div class="form-group">
                        <label for="ad_email">AD/Email</label>
                        <select id="ad_email" name="ad_email">
                            <option value="">-- Null --</option>
                            <option value="Active" <?php echo ($f_admail === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Disabled" <?php echo ($f_admail === 'Disabled') ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                    </div>

                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? '&#10003; Save Changes' : 'Save to Database'; ?>
                    </button>
                    <a href="?view=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php elseif ($view === 'import'): ?>
        <!-- ═══════ IMPORT CSV ═════════════════════════════════════ -->
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
                    <span class="col-pill">Week</span>
                    <span class="col-pill">Effective Date</span>
                    <span class="col-pill">Employee ID</span>
                    <span class="col-pill">Name</span>
                    <span class="col-pill">Hire Date</span>
                    <span class="col-pill">Group</span>
                    <span class="col-pill">HR Summary</span>
                    <span class="col-pill">Department</span>
                    <span class="col-pill">Position</span>
                    <span class="col-pill optional">Clearance Date</span>
                    <span class="col-pill optional">Wifi</span>
                    <span class="col-pill optional">GoPlus</span>
                    <span class="col-pill optional">Imapps</span>
                    <span class="col-pill optional">XO/Scanpack</span>
                    <span class="col-pill optional">Email Address</span>
                    <span class="col-pill optional">AD/Email</span>
                    <p style="margin-top:10px;font-size:0.8rem;">
                        Grey pills are optional. Dates: <strong>YYYY-MM-DD</strong> or <strong>MM/DD/YYYY</strong>.
                        Checkbox columns: use <strong>1</strong> or <strong>Yes</strong> to mark as cleared.
                        Required columns: <strong>Week, Effective Date, Employee ID, Name, Hire Date, Group, HR Summary, Department, Position</strong>. All others are optional.<br>
                        Duplicate employee_id + effective_date combinations are skipped automatically.<br><br>
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