<?php
require_once '../../auth/_dbConfig/_dbConfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve submitted data
    $campusName = isset($_POST['campus_name']) ? trim($_POST['campus_name']) : null;
    $divisionName = isset($_POST['division_name']) ? trim($_POST['division_name']) : null;
    $unitName = isset($_POST['unit_name']) ? trim($_POST['unit_name']) : null;
    $customerTypeName = isset($_POST['customer_type_name']) ? trim($_POST['customer_type_name']) : null;
    $transactionType = isset($_POST['transaction_type']) ? $_POST['transaction_type'] : null;
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : null;
    $answers = isset($_POST['answers']) ? $_POST['answers'] : [];
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Validate that we have answers to process
    if ($transactionType === null || $purpose === null || $campusName === null || $divisionName === null || $unitName === null || $customerTypeName === null) {
        header("Location: first_page.php");
        exit();
    }

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // Step 1: Insert the purpose as the first record to generate a unique ID.
        // We'll use this ID as the `response_id` for all answers.
        $purpose_question_id = 1; // 💡 The purpose is treated as a response to a specific question (e.g., Question #1).
        $purpose_response = $purpose;

        // Get the header and question_rendering for the purpose question from the tbl_questionaire table.
        $stmt_header = $conn->prepare("SELECT header, question_rendering FROM tbl_questionaire WHERE question_id = ?");
        $stmt_header->bind_param("i", $purpose_question_id);
        $stmt_header->execute();
        $header_result = $stmt_header->get_result();
        $header_row = $header_result->fetch_assoc();
        $header_value = $header_row['header'] ?? '0';
        $question_rendering = $header_row['question_rendering'] ?? null;
        $stmt_header->close();

        $final_header_value = $header_value;
        $final_question_rendering_value = null;

        if ($question_rendering === 'QoS' || $question_rendering === 'Su') {
            $final_header_value = '1';
            $final_question_rendering_value = $question_rendering;
        }

        $stmt_purpose = $conn->prepare(
            "INSERT INTO tbl_responses (question_id, response, header, transaction_type, question_rendering, comment) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if ($stmt_purpose === false) {
            throw new Exception('Database prepare failed for purpose insert: ' . $conn->error);
        }

        $stmt_purpose->bind_param("isssss", $purpose_question_id, $purpose_response, $final_header_value, $transactionType, $final_question_rendering_value, $comment);
        $stmt_purpose->execute();

        // Get the auto-generated `id` from the purpose insert. This is our `response_id`.
        $response_id = $conn->insert_id;
        if ($response_id === 0) {
            throw new Exception("Failed to get auto-generated ID from the purpose insert.");
        }

        // Update the first record (the purpose) to set its `response_id` to the ID we just generated.
        $stmt_update = $conn->prepare("UPDATE tbl_responses SET response_id = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $response_id, $response_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Step 2: Loop through the remaining answers and insert them with the same `response_id`.
        $stmt_rest = $conn->prepare(
            "INSERT INTO tbl_responses (response_id, question_id, response, header, transaction_type, question_rendering, comment) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt_rest === false) {
            throw new Exception('Database prepare for subsequent inserts failed: ' . $conn->error);
        }

        // Manually add Campus, Division, and Unit as responses
        // Using negative question_ids to signify they are not from tbl_questionaire
        $context_data = [
            -1 => $campusName,
            -2 => $divisionName,
            -3 => $unitName,
            -4 => $customerTypeName
        ];

        foreach ($context_data as $q_id => $resp) {
            // For these, header is '0' and rendering is null as they are just context.
            $stmt_rest->bind_param("iisssss", $response_id, $q_id, $resp, $header_value, $transactionType, $final_question_rendering_value, $comment);
            $stmt_rest->execute();
        }

        // Now process the actual answers from the form
        foreach ($answers as $q_id => $resp) {
            $question_id = $q_id;
            $response = is_array($resp) ? implode(', ', $resp) : $resp;

            // Get the header and question_rendering for the current question from the tbl_questionaire table.
            $stmt_header_rest = $conn->prepare("SELECT header, question_rendering FROM tbl_questionaire WHERE question_id = ?");
            $stmt_header_rest->bind_param("i", $question_id);
            $stmt_header_rest->execute();
            $header_result_rest = $stmt_header_rest->get_result();
            $header_row_rest = $header_result_rest->fetch_assoc();
            $header_value_rest = $header_row_rest['header'] ?? '0';
            $question_rendering_rest = $header_row_rest['question_rendering'] ?? null;
            $stmt_header_rest->close();

            $final_header_value_rest = $header_value_rest;
            $final_question_rendering_value_rest = null;
            if ($question_rendering_rest === 'QoS' || $question_rendering_rest === 'Su') {
                $final_header_value_rest = '1';
                $final_question_rendering_value_rest = $question_rendering_rest;
            }

            // Bind all parameters for the current record.
            $stmt_rest->bind_param("iisssss", $response_id, $question_id, $response, $final_header_value_rest, $transactionType, $final_question_rendering_value_rest, $comment);
            $stmt_rest->execute();
        }

        $stmt_purpose->close();
        $stmt_rest->close();
        $conn->commit();

        header("Location: ../../pages/last_page.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die('An error occurred during transaction: ' . $e->getMessage());
    } finally {
        $conn->close();
    }
} else {
    header("Location: ../index.php");
    exit();
}
