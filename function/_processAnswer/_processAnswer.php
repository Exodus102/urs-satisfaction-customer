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

    // --- Sentiment Analysis ---
    $sentiment = null; // Default to null.
    $api_full_response = null; // To store the full JSON response from the API.

    // Check if sentiment analysis is active from the database
    $analysis_active = false;
    $stmt_status = $conn->prepare("SELECT status FROM tbl_active WHERE id = 1"); // Assuming the setting is at id=1
    if ($stmt_status) {
        $stmt_status->execute();
        $status_result = $stmt_status->get_result()->fetch_assoc();
        if ($status_result && $status_result['status'] == 1) {
            $analysis_active = true;
        }
        $stmt_status->close();
    }

    if ($analysis_active && !empty($comment)) {
        try {
            $api_url = 'http://72.62.64.144:8000/predict';
            $data = ['text' => $comment];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout for connection
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // Timeout for the entire request

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode == 200 && $response) {
                $result = json_decode($response, true);
                if (isset($result['overall_sentiment'])) {
                    $sentiment = $result['overall_sentiment'];
                    $api_full_response = $response; // Store the raw JSON response
                }
            }
        } catch (Exception $e) {
            // Silently fail or log the error, but don't stop the script
            // The $sentiment will remain null
        }
    }

    // Start a transaction for data integrity
    $conn->begin_transaction();

    try {
        // Step 1: Determine the next response_id. This will be MAX(response_id) + 1.
        $result = $conn->query("SELECT MAX(response_id) as max_response_id FROM tbl_responses");
        $row = $result->fetch_assoc();
        $response_id = ($row['max_response_id'] ?? 0) + 1;

        // The fixed value for the 'uploaded' column as requested.
        $uploaded_value = 0;

        // Step 2: Insert all answers for this submission with the new response_id.
        $stmt_header = $conn->prepare("SELECT header, question_rendering FROM tbl_questionaire WHERE question_id = ?");
        $stmt_answers = $conn->prepare(
            "INSERT INTO tbl_responses (response_id, question_id, response, header, transaction_type, question_rendering, comment, analysis, uploaded) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt_header === false || $stmt_answers === false) {
            throw new Exception('Database prepare failed: ' . $conn->error);
        }

        // First, handle the "context" data (Campus, Division, etc.)
        $context_data = [
            -1 => $campusName,
            -2 => $divisionName,
            -3 => $unitName,
            -4 => $customerTypeName
        ];

        foreach ($context_data as $q_id => $resp) {
            // For these, header is '0' and rendering is null as they are just context.
            $header_value = '0';
            $rendering_value = null;
            $stmt_answers->bind_param("iissssssi", $response_id, $q_id, $resp, $header_value, $transactionType, $rendering_value, $comment, $sentiment, $uploaded_value);
            $stmt_answers->execute();
        }

        // Next, handle the "purpose" as if it's an answer to question_id 1
        $purpose_question_id = 1;
        $purpose_response = $purpose;

        $stmt_header->bind_param("i", $purpose_question_id);
        $stmt_header->execute();
        $header_result = $stmt_header->get_result()->fetch_assoc();
        $header_value = $header_result['header'] ?? '0';
        $question_rendering = $header_result['question_rendering'] ?? null;

        $final_header_value = $header_value;
        $final_question_rendering_value = null;
        if ($question_rendering === 'QoS' || $question_rendering === 'Su') {
            $final_header_value = '1';
            $final_question_rendering_value = $question_rendering;
        }
        $stmt_answers->bind_param("iissssssi", $response_id, $purpose_question_id, $purpose_response, $final_header_value, $transactionType, $final_question_rendering_value, $comment, $sentiment, $uploaded_value);
        $stmt_answers->execute();

        // Finally, process the actual answers from the form
        foreach ($answers as $q_id => $resp) {
            $question_id = $q_id;
            $response = is_array($resp) ? implode(', ', $resp) : $resp;

            // Get the header and question_rendering for the current question from the tbl_questionaire table.
            $stmt_header->bind_param("i", $question_id);
            $stmt_header->execute();
            $header_result_rest = $stmt_header->get_result()->fetch_assoc();
            $header_value_rest = $header_result_rest['header'] ?? '0';
            $question_rendering_rest = $header_result_rest['question_rendering'] ?? null;

            $final_header_value_rest = $header_value_rest;
            $final_question_rendering_value_rest = null;
            if ($question_rendering_rest === 'QoS' || $question_rendering_rest === 'Su') {
                $final_header_value_rest = '1';
                $final_question_rendering_value_rest = $question_rendering_rest;
            }

            // Bind all parameters for the current record.
            $stmt_answers->bind_param("iissssssi", $response_id, $question_id, $response, $final_header_value_rest, $transactionType, $final_question_rendering_value_rest, $comment, $sentiment, $uploaded_value);
            $stmt_answers->execute();
        }

        $stmt_header->close();
        $stmt_answers->close();
        $conn->commit();

        // After the main transaction is successful, insert the detailed API response.
        // This is done separately to keep the main data insertion logic clean.
        if ($api_full_response !== null) {
            $stmt_detail = $conn->prepare("INSERT INTO tbl_detail (response_id, sentiment_details) VALUES (?, ?)");
            $stmt_detail->bind_param("is", $response_id, $api_full_response);
            $stmt_detail->execute();
            $stmt_detail->close();
        }

        header("Location: ../../pages/thank_you.php");
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
