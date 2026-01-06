<?php

require_once '../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();
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
    $validated_sentiment = null; // To store sentiment from validation

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
            $api_url = $_ENV['SENTIMENT_API_URL'];
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

            // --- Secondary Validation using OpenAI ---
            $openai_api_key = $_ENV['OPENAI_API_KEY'];
            if ($openai_api_key) {
                $openai_data = [
                    "model" => "gpt-4.1-mini",
                    "messages" => [
                        [
                            "role" => "system",
                            "content" => "You are a strict sentiment classifier. Reply with only one word: positive, neutral, or negative."
                        ],
                        [
                            "role" => "user",
                            "content" => "Classify the sentiment of this Taglish text:\n\nText: \"$comment\"\nReturn only one word: positive, neutral, or negative."
                        ]
                    ],
                    "temperature" => 0,
                    "max_tokens" => 1 // Increased slightly for safety
                ];

                $ch_openai = curl_init("https://api.openai.com/v1/chat/completions");
                curl_setopt($ch_openai, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_openai, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer $openai_api_key"
                ]);
                curl_setopt($ch_openai, CURLOPT_POST, true);
                curl_setopt($ch_openai, CURLOPT_POSTFIELDS, json_encode($openai_data));

                $openai_response = curl_exec($ch_openai);
                curl_close($ch_openai);

                $openai_result = json_decode($openai_response, true);
                $validated_sentiment = isset($openai_result['choices'][0]['message']['content']) ? strtolower(trim($openai_result['choices'][0]['message']['content'])) : null;
            }
        } catch (Exception $e) {
            // Silently fail or log the error, but don't stop the script
            // The $sentiment will remain null
        }
    }

    // If validation was performed and sentiments do not align, use the validated one.
    if ($validated_sentiment !== null && strtolower(trim($sentiment)) !== $validated_sentiment) {
        $sentiment = $validated_sentiment;
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
            $decoded_api = json_decode($api_full_response, true);

            // Get raw scores safely
            $overall_conf = $decoded_api['overall_confidence'] ?? [];
            $raw_neg = $overall_conf['Negative'] ?? 0;
            $raw_neu = $overall_conf['Neutral'] ?? 0;
            $raw_pos = $overall_conf['Positive'] ?? 0;

            $scores = ['Negative' => $raw_neg, 'Neutral' => $raw_neu, 'Positive' => $raw_pos];

            // Ensure the highest percentage is assigned to the validated sentiment
            $final_sentiment_key = ucfirst(strtolower($sentiment ?? ''));
            $max_val = max($scores);
            $max_key = array_search($max_val, $scores);

            if (isset($scores[$final_sentiment_key]) && $scores[$final_sentiment_key] < $max_val) {
                $scores[$max_key] = $scores[$final_sentiment_key];
                $scores[$final_sentiment_key] = $max_val;
            }

            $neg_score = number_format($scores['Negative'] * 100, 2);
            $neu_score = number_format($scores['Neutral'] * 100, 2);
            $pos_score = number_format($scores['Positive'] * 100, 2);

            // Format token details
            $token_details = [];
            if (isset($decoded_api['token_level_results']) && is_array($decoded_api['token_level_results'])) {
                foreach ($decoded_api['token_level_results'] as $key => $t) {
                    if (isset($t['token'], $t['predicted_sentiment'])) {
                        // Use a Unicode-aware regex to remove any character that is not a letter or number.
                        $cleaned_token = preg_replace('/[^\p{L}\p{N}]/u', '', $t['token']);

                        // Update the raw data as well so the saved JSON is clean
                        $decoded_api['token_level_results'][$key]['token'] = $cleaned_token;
                        $token_details[] = "\"" . $cleaned_token . "\" (" . $t['predicted_sentiment'] . ")";
                    }
                }
            }
            $token_string = !empty($token_details) ? "Key expressions identified include: " . implode(", ", $token_details) . "." : "No specific key expressions were isolated.";

            // The selected score is the highest score (which is now assigned to the final sentiment)
            $selected_score = number_format(max($scores) * 100, 2);

            $analysis_text = "The statement \"$comment\" reflects a generally " . ($sentiment ?? 'N/A') . " overall sentiment. ";
            $analysis_text .= "The system classified the sentiment as " . ucfirst($sentiment ?? 'N/A') . " with a confidence score of " . $selected_score . "% (Negative: " . $neg_score . "%, Neutral: " . $neu_score . "%, Positive: " . $pos_score . "%). ";
            $analysis_text .= "$token_string";

            // Generate Recommendations based on sentiment
            $recommendation_text = "Review the feedback for potential improvements.";
            $sent_lower = strtolower($sentiment ?? '');
            if ($sent_lower === 'positive') {
                $recommendation_text = "Continue with the current practices. Consider highlighting this feedback to the team.";
            } elseif ($sent_lower === 'negative') {
                $recommendation_text = "Investigate the specific issues raised. Immediate action may be required to resolve customer dissatisfaction.";
            } elseif ($sent_lower === 'neutral') {
                $recommendation_text = "Monitor this trend. While not negative, there may be opportunities to delight the customer in the future.";
            }

            $sentiment_details_array = [
                'Analysis' => $analysis_text,
                'Interpretation' => "The statement \"$comment\" reflects a generally " . ($sentiment ?? 'N/A') . " overall sentiment.",
                'Recommendations' => $recommendation_text
            ];

            if ($validated_sentiment) {
                $match_status = (strtolower(trim($sentiment)) === strtolower(trim($validated_sentiment))) ? "aligns with" : "differs from";
                $val_text = "External validation classified the sentiment as " . ucfirst($validated_sentiment) . ", which $match_status the system's analysis.";
                $sentiment_details_array['External Validation'] = $val_text;
            }

            $sentiment_details_array['Overall Sentiment'] = ucfirst($validated_sentiment ?? 'N/A');
            $sentiment_details_array['Polarity'] = ucfirst($sentiment ?? 'N/A');

            // Bring back the JSON from API, but remove redundant fields.
            $api_data_for_db = $decoded_api;
            unset($api_data_for_db['overall_confidence']);
            unset($api_data_for_db['overall_sentiment']);
            unset($api_data_for_db['polarity']); // As requested
            $sentiment_details_array['JSON from API'] = $api_data_for_db;

            $json_to_save = json_encode($sentiment_details_array);

            $stmt_detail = $conn->prepare("INSERT INTO tbl_detail (response_id, sentiment_details) VALUES (?, ?)");
            $stmt_detail->bind_param("is", $response_id, $json_to_save);
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
