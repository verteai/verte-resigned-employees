<?php
session_start();

// Simple in-memory storage using session (no DB required)
if (!isset($_SESSION['employees'])) {
    $_SESSION['employees'] = array(
        array(
            'id' => 1,
            'name' => 'Maria Santos',
            'department' => 'Engineering',
            'position' => 'Senior Developer',
            'email' => 'maria@company.com',
            'hire_date' => '2019-03-15',
            'resign_date' => '2024-01-10',
            'reason' => 'Career advancement',
            'status' => 'resigned',
        ),
        array(
            'id' => 2,
            'name' => 'Juan dela Cruz',
            'department' => 'Marketing',
            'position' => 'Marketing Manager',
            'email' => 'juan@company.com',
            'hire_date' => '2018-07-01',
            'resign_date' => '2023-11-30',
            'reason' => 'Personal reasons',
            'status' => 'resigned',
        ),
        array(
            'id' => 3,
            'name' => 'Ana Reyes',
            'department' => 'HR',
            'position' => 'HR Specialist',
            'email' => 'ana@company.com',
            'hire_date' => '2021-02-20',
            'resign_date' => '2024-06-15',
            'reason' => 'Relocation',
            'status' => 'resigned',
        ),
    );
    $_SESSION['next_id'] = 4;
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $newEmployee = array(
                'id' => $_SESSION['next_id']++,
                'name' => htmlspecialchars(trim($_POST['name'])),
                'department' => htmlspecialchars(trim($_POST['department'])),
                'position' => htmlspecialchars(trim($_POST['position'])),
                'email' => htmlspecialchars(trim($_POST['email'])),
                'hire_date' => $_POST['hire_date'],
                'resign_date' => $_POST['resign_date'],
                'reason' => htmlspecialchars(trim($_POST['reason'])),
                'status' => 'resigned',
            );
            $_SESSION['employees'][] = $newEmployee;
            $message = "Employee '{$newEmployee['name']}' has been added successfully.";
            $messageType = 'success';
        } elseif ($_POST['action'] === 'delete') {
            $deleteId = (int)$_POST['delete_id'];
            $found = false;
            foreach ($_SESSION['employees'] as $k => $emp) {
                if ($emp['id'] === $deleteId) {
                    $name = $emp['name'];
                    unset($_SESSION['employees'][$k]);
                    $_SESSION['employees'] = array_values($_SESSION['employees']);
                    $message = "Employee '{$name}' has been removed.";
                    $messageType = 'info';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $message = "Employee not found.";
                $messageType = 'error';
            }
        }
    }
}

$employees = $_SESSION['employees'];
$totalResigned = count($employees);

// Department breakdown
$deptCounts = array();
foreach ($employees as $emp) {
    $dept = $emp['department'];
    $deptCounts[$dept] = isset($deptCounts[$dept]) ? $deptCounts[$dept] + 1 : 1;
}

// Recent resignations (last 6 months)
$recent = 0;
$sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
foreach ($employees as $emp) {
    if ($emp['resign_date'] >= $sixMonthsAgo) $recent++;
}

// Avg tenure
$totalDays = 0;
foreach ($employees as $emp) {
    $hire = new DateTime($emp['hire_date']);
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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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

        .layout { display: -webkit-box; display: flex; min-height: 100vh; }

        .sidebar {
            width: 240px;
            background: var(--ink);
            color: #fff;
            padding: 0;
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

        .sidebar-nav { padding: 16px 0; -webkit-box-flex: 1; flex: 1; }

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
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover { color: #fff; background: #1a1a1a; }
        .nav-item.active { color: #fff; border-left-color: var(--accent); background: #1a1a1a; }

        .nav-icon { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #2a2a2a;
            font-size: 0.75rem;
            color: #555;
        }

        .main { -webkit-box-flex: 1; flex: 1; padding: 36px 40px; overflow-y: auto; }

        .page-header { margin-bottom: 32px; }

        .page-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--ink);
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
            display: -webkit-box;
            display: flex;
            -webkit-box-orient: horizontal;
            flex-direction: row;
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
            color: var(--ink);
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
            color: var(--ink);
        }

        .dept-bar-row { margin-bottom: 14px; }

        .dept-bar-label {
            display: -webkit-box;
            display: flex;
            -webkit-box-pack: justify;
            justify-content: space-between;
            font-size: 0.83rem;
            margin-bottom: 5px;
            color: var(--ink);
            font-weight: 500;
        }

        .dept-bar-track { background: #f0ece4; border-radius: 4px; height: 8px; overflow: hidden; }

        .dept-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: var(--accent);
        }

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
        }

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

        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #a33208; }

        .btn-danger {
            background: transparent;
            color: var(--accent);
            border: 1px solid #f5c3b5;
            font-size: 0.8rem;
            padding: 6px 12px;
        }
        .btn-danger:hover { background: #fdf0ee; }

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

        .emp-name { font-weight: 600; color: var(--ink); }
        .emp-email { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .badge-resigned { background: #fdf0ee; color: var(--accent); }

        .form-card {
            background: var(--card);
            border-radius: 12px;
            padding: 32px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            max-width: 680px;
        }

        .form-grid {
            display: -webkit-box;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        .form-group {
            display: -webkit-box;
            display: flex;
            -webkit-box-orient: vertical;
            flex-direction: column;
            gap: 6px;
            width: calc(50% - 9px);
        }

        .form-group.full { width: 100%; }

        label { font-size: 0.8rem; font-weight: 600; color: var(--ink); }

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

        .form-actions {
            margin-top: 24px;
            display: -webkit-box;
            display: flex;
            gap: 12px;
        }

        .btn-secondary {
            background: transparent;
            color: var(--muted);
            border: 1.5px solid var(--border);
        }
        .btn-secondary:hover { border-color: var(--ink); color: var(--ink); }

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
                <span class="nav-icon">[&#x25A0;]</span> Dashboard
            </a>
            <a href="?view=list" class="nav-item <?php echo ($view === 'list') ? 'active' : ''; ?>">
                <span class="nav-icon">[&#x2261;]</span> All Records
            </a>
            <a href="?view=add" class="nav-item <?php echo ($view === 'add') ? 'active' : ''; ?>">
                <span class="nav-icon">[+]</span> Add Record
            </a>
        </div>
        <div class="sidebar-footer">
            <?php echo $totalResigned; ?> total record<?php echo ($totalResigned !== 1) ? 's' : ''; ?>
        </div>
    </nav>

    <main class="main">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($view === 'dashboard'): ?>

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
            <?php $recent_emps = array_slice(array_reverse($employees), 0, 5); ?>
            <?php if (empty($recent_emps)): ?>
            <div class="empty">
                <div class="empty-icon">&#128203;</div>
                <p>No records yet. <a href="?view=add">Add your first entry.</a></p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
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
            <p><?php echo $totalResigned; ?> resigned employee<?php echo ($totalResigned !== 1) ? 's' : ''; ?> on file</p>
        </div>

        <div class="table-card">
            <div class="table-header">
                <span class="section-title" style="margin:0">Employee Records</span>
                <a href="?view=add" class="btn btn-primary">+ Add Employee</a>
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
                        <th>#</th>
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
                    <?php foreach ($employees as $i => $emp): ?>
                    <tr>
                        <td style="color:#888;font-size:0.8rem"><?php echo $i + 1; ?></td>
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

        <div class="page-header">
            <h2>Add Resigned Employee</h2>
            <p>Record a new resigned employee entry</p>
        </div>

        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
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
                    <button type="submit" class="btn btn-primary">Save Record</button>
                    <a href="?view=list" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <?php endif; ?>

    </main>
</div>
</body>
</html>