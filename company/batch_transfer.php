<?php
require "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$employee_ids = $_POST["employee_ids"] ?? [];
$new_department = filter_input(INPUT_POST, "new_department", FILTER_VALIDATE_INT);

$employee_ids = array_values(array_filter(
    array_map("intval", (array)$employee_ids),
    fn($id) => $id > 0
));

if (!$new_department || !$employee_ids) {
    die("Please select at least one employee and a valid target department.");
}

try {
    $pdo->beginTransaction();

    $deptStmt = $pdo->prepare(
        "SELECT id, dept_name FROM departments WHERE id = :id"
    );
    $deptStmt->execute([":id" => $new_department]);
    $newDept = $deptStmt->fetch();

    if (!$newDept) {
        throw new Exception("Invalid target department ID.");
    }

    $employeeStmt = $pdo->prepare(
        "SELECT e.id, e.full_name, e.department_id, d.dept_name AS old_department
         FROM employees e
         INNER JOIN departments d ON e.department_id = d.id
         WHERE e.id = :id"
    );

    $updateStmt = $pdo->prepare(
        "UPDATE employees
         SET department_id = :new_dept
         WHERE id = :emp_id"
    );

    $logStmt = $pdo->prepare(
        "INSERT INTO transfer_logs
         (employee_name, old_department, new_department)
         VALUES (:employee_name, :old_department, :new_department)"
    );

    foreach ($employee_ids as $emp_id) {
        $employeeStmt->execute([":id" => $emp_id]);
        $employee = $employeeStmt->fetch();

        if (!$employee) {
            throw new Exception("Employee ID $emp_id was not found.");
        }

        if ((int)$employee["department_id"] === $new_department) {
            continue;
        }

        $updateStmt->execute([
            ":new_dept" => $new_department,
            ":emp_id" => $emp_id
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new Exception("Employee ID $emp_id could not be transferred.");
        }

        $logStmt->execute([
            ":employee_name" => $employee["full_name"],
            ":old_department" => $employee["old_department"],
            ":new_department" => $newDept["dept_name"]
        ]);
    }

    $pdo->commit();

    header("Location: index.php");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo "<h2>Batch transfer failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo '<p><a href="index.php">Return to dashboard</a></p>';
}
?>