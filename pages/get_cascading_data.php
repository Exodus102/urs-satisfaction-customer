<?php
header('Content-Type: application/json');
require_once '../auth/_dbConfig/_dbConfig.php';

$response = [];

try {
    if (isset($_GET['campus_id']) && isset($_GET['division_id'])) {
        $campus_id = (int)$_GET['campus_id'];
        $division_id = (int)$_GET['division_id'];

        // 1. Get campus_name from tbl_campus using campus_id
        $stmt_campus = $conn->prepare("SELECT campus_name FROM tbl_campus WHERE id = ?");
        $stmt_campus->bind_param("i", $campus_id);
        $stmt_campus->execute();
        $campus_result = $stmt_campus->get_result()->fetch_assoc();
        $campus_name = $campus_result['campus_name'] ?? null;
        $stmt_campus->close();

        // 2. Get division_name from tbl_division using division_id
        $stmt_division = $conn->prepare("SELECT division_name FROM tbl_division WHERE id = ?");
        $stmt_division->bind_param("i", $division_id);
        $stmt_division->execute();
        $division_result = $stmt_division->get_result()->fetch_assoc();
        $division_name = $division_result['division_name'] ?? null;
        $stmt_division->close();

        // 3. Filter tbl_unit using the retrieved names
        if ($campus_name && $division_name) {
            $stmt = $conn->prepare("SELECT id, unit_name FROM tbl_unit WHERE campus_name = ? AND division_name = ? ORDER BY unit_name");
            if ($stmt) {
                $stmt->bind_param("ss", $campus_name, $division_name);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $response[] = $row;
                }
                $stmt->close();
            }
        }
    }
} catch (Exception $e) {
    // In a real application, you would log this error.
    // For now, we'll send an empty response.
    http_response_code(500);
    $response = ['error' => 'An internal server error occurred.'];
}

$conn->close();
echo json_encode($response);
