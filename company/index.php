<?php
require "db.php";

$search = trim($_GET["search"] ?? "");
$department_id = $_GET["department_id"] ?? "";
$page = max(1, (int)($_GET["page"] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

$departments = $pdo->query(
    "SELECT id, dept_name FROM departments ORDER BY dept_name"
)->fetchAll();

$where = [];
$params = [];

if ($search !== "") {
    $where[] = "(e.full_name LIKE :search OR e.employee_code LIKE :search)";
    $params[":search"] = "%$search%";
}

if ($department_id !== "" && ctype_digit((string)$department_id)) {
    $where[] = "e.department_id = :department_id";
    $params[":department_id"] = (int)$department_id;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM employees e
     INNER JOIN departments d ON e.department_id = d.id
     $whereSql"
);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));

$stmt = $pdo->prepare(
    "SELECT e.id, e.employee_code, e.full_name, e.email,
            d.dept_name, e.salary, e.hire_date
     FROM employees e
     INNER JOIN departments d ON e.department_id = d.id
     $whereSql
     ORDER BY e.id DESC
     LIMIT :limit OFFSET :offset"
);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll();

$deptStmt = $pdo->query(
    "SELECT d.id, d.dept_name, COUNT(e.id) AS employee_count
     FROM departments d
     LEFT JOIN employees e ON d.id = e.department_id
     GROUP BY d.id, d.dept_name
     ORDER BY d.dept_name"
);
$departmentCounts = $deptStmt->fetchAll();

$logs = $pdo->query(
    "SELECT employee_name, old_department, new_department, transferred_at
     FROM transfer_logs
     ORDER BY transferred_at DESC
     LIMIT 10"
)->fetchAll();

function pageUrl($pageNumber) {
    $query = $_GET;
    $query["page"] = $pageNumber;
    return "?" . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Management System</title>
<style>
body{font-family:Arial,sans-serif;margin:0;background:#f4f6f8;color:#222}
header{background:#1f4e79;color:#fff;padding:22px}
.container{max-width:1200px;margin:25px auto;padding:0 15px}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:20px}
.card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px #0001;margin-bottom:20px}
h1,h2{margin-top:0}
.btn{display:inline-block;padding:9px 13px;border-radius:6px;text-decoration:none;border:0;cursor:pointer;background:#1f4e79;color:white}
.btn.green{background:#198754}.btn.red{background:#dc3545}.btn.gray{background:#6c757d}
input,select{padding:10px;border:1px solid #ccc;border-radius:6px}
.search{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left;font-size:14px}
th{background:#eef2f5}
.actions{white-space:nowrap}
.pagination{margin-top:15px;display:flex;gap:6px;flex-wrap:wrap}
.pagination a{padding:7px 10px;border:1px solid #ccc;text-decoration:none;color:#1f4e79;border-radius:5px}
.pagination .active{background:#1f4e79;color:#fff}
ul{padding-left:20px}
.small{font-size:13px;color:#666}
@media(max-width:850px){.grid{grid-template-columns:1fr}table{display:block;overflow-x:auto}}
</style>
</head>
<body>
<header>
<div class="container">
<h1>Employee Management System</h1>
<p>PHP PDO • MySQL • CRUD • Search • Pagination • Transactions</p>
</div>
</header>

<div class="container">
<div class="card">
<form class="search" method="get">
<input type="text" name="search" placeholder="Search name or employee code"
       value="<?= htmlspecialchars($search) ?>">
<select name="department_id">
<option value="">All Departments</option>
<?php foreach ($departments as $dept): ?>
<option value="<?= $dept["id"] ?>" <?= (string)$department_id === (string)$dept["id"] ? "selected" : "" ?>>
<?= htmlspecialchars($dept["dept_name"]) ?>
</option>
<?php endforeach; ?>
</select>
<button class="btn" type="submit">Search / Filter</button>
<a class="btn gray" href="index.php">Reset</a>
<a class="btn green" href="add_employee.php">+ Add Employee</a>
</form>
</div>

<div class="grid">
<main>
<div class="card">
<h2>Employee Directory</h2>
<form method="post" action="batch_transfer.php">
<div style="margin-bottom:12px">
<select name="new_department" required>
<option value="">Transfer selected to...</option>
<?php foreach ($departments as $dept): ?>
<option value="<?= $dept["id"] ?>"><?= htmlspecialchars($dept["dept_name"]) ?></option>
<?php endforeach; ?>
</select>
<button class="btn" type="submit">Batch Transfer</button>
</div>

<table>
<thead>
<tr>
<th>Select</th><th>Code</th><th>Name</th><th>Email</th>
<th>Department</th><th>Salary</th><th>Hire Date</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!$employees): ?>
<tr><td colspan="8">No employees found.</td></tr>
<?php else: ?>
<?php foreach ($employees as $employee): ?>
<tr>
<td><input type="checkbox" name="employee_ids[]" value="<?= $employee["id"] ?>"></td>
<td><?= htmlspecialchars($employee["employee_code"]) ?></td>
<td><?= htmlspecialchars($employee["full_name"]) ?></td>
<td><?= htmlspecialchars($employee["email"]) ?></td>
<td><?= htmlspecialchars($employee["dept_name"]) ?></td>
<td>₱<?= number_format((float)$employee["salary"],2) ?></td>
<td><?= htmlspecialchars($employee["hire_date"]) ?></td>
<td class="actions">
<a class="btn" href="edit_employee.php?id=<?= $employee["id"] ?>">Edit</a>
<a class="btn red" href="delete_employee.php?id=<?= $employee["id"] ?>"
   onclick="return confirm('Delete this employee?')">Delete</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</form>

<div class="pagination">
<?php if ($page > 1): ?>
<a href="<?= pageUrl($page-1) ?>">Previous</a>
<?php endif; ?>
<?php for ($i=1; $i <= $totalPages; $i++): ?>
<a class="<?= $i === $page ? "active" : "" ?>" href="<?= pageUrl($i) ?>"><?= $i ?></a>
<?php endfor; ?>
<?php if ($page < $totalPages): ?>
<a href="<?= pageUrl($page+1) ?>">Next</a>
<?php endif; ?>
</div>
<p class="small">Showing <?= count($employees) ?> of <?= $totalRecords ?> matching employee(s).</p>
</div>
</main>

<aside>
<div class="card">
<h2>Department Summary</h2>
<table>
<tr><th>Department</th><th>Employees</th></tr>
<?php foreach ($departmentCounts as $row): ?>
<tr>
<td><?= htmlspecialchars($row["dept_name"]) ?></td>
<td><?= (int)$row["employee_count"] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>Recent Activity</h2>
<?php if (!$logs): ?>
<p>No transfer logs yet.</p>
<?php else: ?>
<ul>
<?php foreach ($logs as $log): ?>
<li>
<strong><?= htmlspecialchars($log["employee_name"]) ?></strong><br>
<?= htmlspecialchars($log["old_department"]) ?> →
<?= htmlspecialchars($log["new_department"]) ?><br>
<span class="small"><?= htmlspecialchars($log["transferred_at"]) ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
</aside>
</div>
</div>
</body>
</html>