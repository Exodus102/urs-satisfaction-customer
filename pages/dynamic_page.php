<?php
// It's a good practice to check if the form was submitted via POST
require_once '../auth/_dbConfig/_dbConfig.php';

// --- Fetch Active Logo ---
$logoPath = '../resources/svg/logo.svg'; // Default logo
$logo_stmt = $conn->prepare("SELECT logo_path FROM tbl_logo WHERE status = 1 LIMIT 1");
if ($logo_stmt) {
  $logo_stmt->execute();
  $logo_result = $logo_stmt->get_result();
  if ($logo_row = $logo_result->fetch_assoc()) {
    $logoPath = ADMIN_BASE_PATH . $logo_row['logo_path'];
  }
  $logo_stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Use isset() to avoid errors if a value wasn't submitted
  $campusId = isset($_POST['campus_id']) ? (int)$_POST['campus_id'] : null;
  $divisionId = isset($_POST['division_id']) ? (int)$_POST['division_id'] : null;
  $unitId = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
  $customerTypeId = isset($_POST['customer_type_id']) ? (int)$_POST['customer_type_id'] : null;
  $transactionTypeString = isset($_POST['transaction_type']) ? $_POST['transaction_type'] : 'Not provided';
  $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : 'Not provided';

  // Map transaction type to numeric value for the next page
  $transactionType = 'Not provided';
  if ($transactionTypeString === 'Face-to-Face') {
    $transactionType = 0;
  } elseif ($transactionTypeString === 'Online') {
    $transactionType = 1;
  }

  // Fetch names based on IDs
  $campusName = 'N/A';
  if ($campusId) {
    $stmt = $conn->prepare("SELECT campus_name FROM tbl_campus WHERE id = ?");
    $stmt->bind_param("i", $campusId);
    $stmt->execute();
    $campusName = $stmt->get_result()->fetch_assoc()['campus_name'] ?? 'N/A';
    $stmt->close();
  }

  $divisionName = 'N/A';
  if ($divisionId) {
    $stmt = $conn->prepare("SELECT division_name FROM tbl_division WHERE id = ?");
    $stmt->bind_param("i", $divisionId);
    $stmt->execute();
    $divisionName = $stmt->get_result()->fetch_assoc()['division_name'] ?? 'N/A';
    $stmt->close();
  }

  $unitName = 'N/A';
  if ($unitId) {
    $stmt = $conn->prepare("SELECT unit_name FROM tbl_unit WHERE id = ?");
    $stmt->bind_param("i", $unitId);
    $stmt->execute();
    $unitName = $stmt->get_result()->fetch_assoc()['unit_name'] ?? 'N/A';
    $stmt->close();
  }

  $customerTypeName = 'N/A';
  if ($customerTypeId) {
    $stmt = $conn->prepare("SELECT customer_type FROM tbl_customer_type WHERE id = ?");
    $stmt->bind_param("i", $customerTypeId);
    $stmt->execute();
    $customerTypeName = $stmt->get_result()->fetch_assoc()['customer_type'] ?? 'N/A';
    $stmt->close();
  }

  // Use htmlspecialchars() to prevent XSS when displaying user input
  $safeCampusName = htmlspecialchars($campusName, ENT_QUOTES, 'UTF-8');
  $safeDivisionName = htmlspecialchars($divisionName, ENT_QUOTES, 'UTF-8');
  $safeUnitName = htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8');
  $safeCustomerTypeName = htmlspecialchars($customerTypeName, ENT_QUOTES, 'UTF-8');
  $safeTransactionType = htmlspecialchars($transactionType, ENT_QUOTES, 'UTF-8');
  $safePurpose = htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8');
} else {
  // If the page is accessed directly, redirect to the first page
  header("Location: first_page.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../Tailwind/src/output.css">
  <title>Customer Satisfaction Survey</title>
</head>

<body>
  <div class="min-h-screen flex flex-col items-center justify-start relative bg-cover bg-center"
    style="background-image: url('../resources/svg/landing-page.svg');">

    <!-- Logo -->
    <div class="flex items-center justify-center gap-2 mb-4 mt-10">
      <img src="<?= htmlspecialchars($logoPath) ?>" alt="URSatisfaction Logo" class="h-16">
      <div class="text-left">
        <h2 class="text-xl font-bold leading-tight">
          <span class="text-[#95B3D3]">URS</span><span class="text-[#F1F7F9]">atisfaction</span>
        </h2>
        <p class="text-sm text-[#F1F7F9] leading-snug">We comply so URSatisfied</p>
      </div>
    </div>

    <!-- White Card -->
    <div class="bg-white shadow-2xl rounded-lg w-full max-w-[90%] p-5 lg:p-10 mx-6 min-h-[550px] flex items-center">
      <!-- Inner wrapper -->
      <div class="w-full max-w-xl mx-auto space-y-10 lg:px-10">

        <!-- Title and Subtitle -->
        <div class="text-center lg:text-left">
          <h1 class="text-4xl lg:text-2xl font-bold text-[#1E1E1E] mb-2 leading-snug">Your thoughts matter!</h1>
          <p class="text-xl lg:text-sm text-[#1E1E1E] lg:leading-relaxed lg:max-w-[90%]">
            We’d love to hear your comments and suggestions to serve you better.
          </p>
        </div>

        <!-- Form -->
        <form action="../function/_processAnswer/_processAnswer.php" method="POST" class="space-y-6">
          <!-- Hidden fields -->
          <input type="hidden" name="campus_name" value="<?= $safeCampusName ?>">
          <input type="hidden" name="division_name" value="<?= $safeDivisionName ?>">
          <input type="hidden" name="unit_name" value="<?= $safeUnitName ?>">
          <input type="hidden" name="customer_type_name" value="<?= $safeCustomerTypeName ?>">
          <input type="hidden" name="transaction_type" value="<?= $safeTransactionType ?>">
          <input type="hidden" name="purpose" value="<?= $safePurpose ?>">

          <!-- Questions from Database -->
          <div class="space-y-6 pt-2">
            <?php
            $stmt = $conn->prepare("SELECT question_id, question, question_type, required 
                                    FROM tbl_questionaire 
                                    WHERE (transaction_type = ? OR transaction_type = 2) 
                                    AND status = 1 ORDER BY question_id");
            $stmt->bind_param("i", $transactionType);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
              while ($question = $result->fetch_assoc()) {
                $q_id = htmlspecialchars($question['question_id']);
                $q_text = htmlspecialchars($question['question']);
                $q_type = $question['question_type'];
                $q_required = $question['required'] ? 'required' : '';

                echo "<div class='space-y-2'>";
                echo "<label class='block text-[#1E1E1E] text-lg lg:text-sm mb-2 leading-snug font-medium'>{$q_text}</label>";

                switch ($q_type) {
                  case 'Dropdown':
                    $choice_stmt = $conn->prepare("SELECT choice_text FROM tbl_choices WHERE question_id = ? ORDER BY choices_id");
                    $choice_stmt->bind_param("i", $question['question_id']);
                    $choice_stmt->execute();
                    $choices_result = $choice_stmt->get_result();

                    echo "<select name='answers[{$q_id}]' 
                            class='w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]' 
                            {$q_required}>";
                    echo "<option value='' disabled selected>--Please choose an option--</option>";

                    while ($choice = $choices_result->fetch_assoc()) {
                      $c_text = htmlspecialchars($choice['choice_text']);
                      echo "<option value='{$c_text}'>{$c_text}</option>";
                    }

                    echo "</select>";
                    $choice_stmt->close();
                    break;
                  case 'Multiple Choice':
                    $choice_stmt = $conn->prepare("SELECT choice_text FROM tbl_choices WHERE question_id = ? ORDER BY choices_id");
                    $choice_stmt->bind_param("i", $question['question_id']);
                    $choice_stmt->execute();
                    $choices_result = $choice_stmt->get_result();

                    echo "<div class='mt-2 flex items-center space-x-6'>";
                    $choice_index = 0;
                    while ($choice = $choices_result->fetch_assoc()) {
                      $c_text = htmlspecialchars($choice['choice_text']);
                      $radio_id = "q_{$q_id}_choice_{$choice_index}";
                      echo "<div class='flex items-center'>";
                      echo "<input id='{$radio_id}' name='answers[{$q_id}]' type='radio' value='{$c_text}' class='focus:ring-[#064089] h-4 w-4 text-[#064089] border-gray-300' {$q_required}>";
                      echo "<label for='{$radio_id}' class='ml-3 block text-base text-gray-700'>{$c_text}</label>";
                      echo "</div>";
                      $choice_index++;
                    }
                    echo "</div>";
                    $choice_stmt->close();
                    break;
                  case 'Description':
                    // This is just a label, no input required.
                    break;
                  case 'Text':
                    echo "<input type='text' name='answers[{$q_id}]' 
                            class='w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]' 
                            {$q_required}>";
                    break;
                }
                echo "</div>";
              }
            } else {
              echo "<p class='text-red-600 text-center'>No questions found for this transaction type.</p>";
            }
            $stmt->close();
            ?>
          </div>

          <!-- Comment Box -->
          <div class="space-y-2 pt-4">
            <label for="comment" class="block text-[#1E1E1E] text-lg lg:text-sm mb-2 leading-snug font-medium">Comments/Suggestions</label>
            <textarea
              id="comment"
              name="comment"
              rows="4"
              class="w-full border border-[#1E1E1E] rounded-md px-3 py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]"
              placeholder="Enter your comments or suggestions here..." required></textarea>
          </div>

          <!-- Buttons -->
          <div class="flex justify-between items-center pt-4">
            <!-- Back Arrow -->
            <a href="javascript:history.back()"
              class="bg-[#064089] hover:bg-blue-900 text-white text-sm font-medium px-6 py-2 rounded-md shadow-md transition flex items-center justify-center">
              <img src="../resources/svg/back-arrow.svg" alt="Back" class="h-5 w-5">
            </a>

            <!-- Submit -->
            <button type="submit"
              class="bg-[#064089] hover:bg-blue-900 text-white text-sm font-medium px-6 py-2 rounded-md shadow-md transition">
              Submit
            </button>
          </div>

        </form>

      </div>
    </div>

    <!-- Footer -->
    <div class="w-full max-w-[90%] mx-6 mt-4 mb-10 text-left">
      <p class="text-[#F1F7F9] text-s">
        © University of Rizal System - Customer Satisfaction Survey System
      </p>
    </div>

  </div>
</body>

</html>