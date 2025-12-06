<?php
require_once '../auth/_dbConfig/_dbConfig.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Retrieve submitted data
  $transactionType = isset($_POST['transaction_type']) ? $_POST['transaction_type'] : 'Not provided';
  $purpose = isset($_POST['purpose']) ? $_POST['purpose'] : 'Not provided';
  $answers = isset($_POST['answers']) ? $_POST['answers'] : [];

  // Map transaction type back to string for display
  $transactionTypeString = 'Not provided';
  if ($transactionType === '0') {
    $transactionTypeString = 'Face-to-Face';
  } elseif ($transactionType === '1') {
    $transactionTypeString = 'Online';
  }

  // Use htmlspecialchars() to prevent XSS
  $safeTransactionType = htmlspecialchars($transactionTypeString, ENT_QUOTES, 'UTF-8');
  $safePurpose = htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8');
} else {
  // If the page is accessed directly, redirect to the first page
  header("Location: ../index.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Redirect to thank_you.php after 10 seconds -->
  <meta http-equiv="refresh" content="10;url=thank_you.php">
  <link rel="stylesheet" href="../Tailwind/src/output.css">
  <title>Survey Summary</title>
</head>

<body>
  <div class="min-h-screen flex flex-col items-center justify-start relative bg-cover bg-center"
    style="background-image: url('../resources/svg/landing-page.svg');">

    <!-- Logo -->
    <div class="flex items-center justify-center gap- mb-5 mt-6">
      <img src="../resources/svg/logo.svg" alt="URSatisfaction Logo" class="h-20">
      <div class="text-left">
        <h2 class="text-2xl font-bold leading-tight">
          <span class="text-[#95B3D3]">URS</span><span class="text-[#F1F7F9]">atisfaction</span>
        </h2>
        <p class="text-sm text-[#F1F7F9] leading-snug">We comply so URSatisfied</p>
      </div>
    </div>

    <!-- White Card -->
    <div class="bg-white shadow-2xl rounded-lg w-full max-w-[90%] p-14 mx-6 min-h-[620px] mt-14">
      <div class="w-full max-w-2xl mx-auto space-y-10 px-10">
        <h1 class="text-3xl font-bold text-center text-[#064089]">Thank you for your feedback!</h1>
        <p class="text-lg text-center text-gray-600">Here is a summary of your submission.</p>

        <div class="space-y-8 border-t pt-8">
          <div class="space-y-2">
            <h2 class="text-xl font-semibold text-gray-800">Submission Details</h2>
            <p><strong class="font-medium">Transaction Type:</strong> <?= $safeTransactionType ?></p>
            <p><strong class="font-medium">Purpose of Transaction:</strong> <?= $safePurpose ?></p>
          </div>

          <div class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-800">Your Answers</h2>
            <?php
            if (!empty($answers)) {
              // Prepare a statement to get question text from question_id
              $questionIds = array_keys($answers);
              if (!empty($questionIds)) {
                $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
                $types = str_repeat('i', count($questionIds));

                $stmt = $conn->prepare("SELECT question_id, question FROM tbl_questionaire WHERE question_id IN ($placeholders)");
                $stmt->bind_param($types, ...$questionIds);
                $stmt->execute();
                $result = $stmt->get_result();

                $questions = [];
                while ($row = $result->fetch_assoc()) {
                  $questions[$row['question_id']] = $row['question'];
                }
                $stmt->close();

                echo "<ul class='list-disc list-inside space-y-2'>";
                foreach ($answers as $question_id => $answer) {
                  $question_text = isset($questions[$question_id]) ? htmlspecialchars($questions[$question_id], ENT_QUOTES, 'UTF-8') : "Question ID: " . htmlspecialchars($question_id);
                  $answer_safe = htmlspecialchars($answer, ENT_QUOTES, 'UTF-8');
                  echo "<li><strong class='font-medium'>{$question_text}:</strong> {$answer_safe}</li>";
                }
                echo "</ul>";
              }
            } else {
              echo "<p class='text-gray-500'>No answers were submitted.</p>";
            }
            $conn->close();
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>