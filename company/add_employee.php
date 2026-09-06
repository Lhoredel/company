<?php
require "db.php";

$error = "";
$departments = $pdo->query(
    "SELECT id, dept_name FROM departments ORDER BY dept_name"
)->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $employee_code = trim($_POST["employee_code"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department_id = $_POST["department_id"] ?? "";
    $salary = $_POST["salary"] ?? "";
    $hire_date = $_POST["hire_date"] ?? "";

    if ($employee_code === "" || $full_name === "" || $email === "" ||
        $department_id === "" || $salary === "" || $hire_date === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!is_numeric($salary) || (float)$salary < 0) {
        $error = "Salary must be a valid non-negative number.";
    } else {
        $check = $pdo->prepare(
            "SELECT id FROM employees
             WHERE employee_code = :employee_code OR email = :email
             LIMIT 1"
        );
        $check->execute([
            ":employee_code" => $employee_code,
            ":email" => $email
        ]);

        if ($check->fetch()) {
            $error = "Employee code or email already exists.";
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO employees
                 (employee_code, full_name, email, department_id, salary, hire_date)
                 VALUES (:employee_code, :full_name, :email, :department_id, :salary, :hire_date)"
            );
            $insert->execute([
                ":employee_code" => $employee_code,
                ":full_name" => $full_name,
                ":email" => $email,
                ":department_id" => (int)$department_id,
                ":salary" => $salary,
                ":hire_date" => $hire_date
            ]);

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Add Employee</title>
<style>
body{font-family:Arial;background:#f4f6f8}.box{max-width:600px;margin:40px auto;background:white;padding:25px;border-radius:10px}
label{display:block;margin-top:12px}input,select{width:100%;padding:10px;margin-top:5px;box-sizing:border-box}
button,a{margin-top:18px;padding:10px 15px;border:0;border-radius:6px;text-decoration:none}
button{background:#198754;color:white}.back{background:#6c757d;color:white}.error{background:#f8d7da;padding:10px}
</style></head>
<body>
<div class="box">
<h1>Add Employee</h1>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<label>Employee Code
<input name="employee_code" maxlength="15" required value="<?= htmlspecialchars($_POST["employee_code"] ?? "") ?>">
</label>
<label>Full Name
<input name="full_name" maxlength="100" required value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>">
</label>
<label>Email
<input type="email" name="email" maxlength="100" required value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
</label>
<label>Department
<select name="department_id" required>
<option value="">Select department</option>
<?php foreach ($departments as $d): ?>
<option value="<?= $d["id"] ?>" <?= (($_POST["department_id"] ?? "") == $d["id"]) ? "selected" : "" ?>>
<?= htmlspecialchars($d["dept_name"]) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Salary
<input type="number" name="salary" step="0.01" min="0" required value="<?= htmlspecialchars($_POST["salary"] ?? "") ?>">
</label>
<label>Hire Date
<input type="date" name="hire_date" required value="<?= htmlspecialchars($_POST["hire_date"] ?? "") ?>">
</label>
<button type="submit">Save Employee</button>
<a class="back" href="index.php">Cancel</a>
</form>
</div>
</body></html>