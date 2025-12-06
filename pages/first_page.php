<?php
require_once 'auth/_dbConfig/_dbConfig.php';

// --- Pre-fill from URL parameters ---

$selectedCampusId = null;
$selectedDivisionId = null;
$selectedUnitName = null; // We'll use the name for the unit, as it's populated by JS

if (isset($_GET['campus'])) {
  $campusName = trim($_GET['campus']);
  $stmt = $conn->prepare("SELECT id FROM tbl_campus WHERE campus_name = ?");
  $stmt->bind_param("s", $campusName);
  $stmt->execute();
  $selectedCampusId = $stmt->get_result()->fetch_assoc()['id'] ?? null;
  $stmt->close();
}

if (isset($_GET['division'])) {
  $divisionName = trim($_GET['division']);
  $stmt = $conn->prepare("SELECT id FROM tbl_division WHERE division_name = ?");
  $stmt->bind_param("s", $divisionName);
  $stmt->execute();
  $selectedDivisionId = $stmt->get_result()->fetch_assoc()['id'] ?? null;
  $stmt->close();
}

if (isset($_GET['unit'])) {
  $selectedUnitName = trim($_GET['unit']);
}
?>
<div class="min-h-screen flex flex-col items-center justify-between relative bg-cover bg-center"
  style="background-image: url('resources/svg/landing-page.svg');">

  <!-- Logo -->
  <div class="flex items-center justify-center gap-2 mb-4 mt-10">
    <img src="resources/img/new-logo.png" alt="URSatisfaction Logo" class="h-16">
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

      <!-- Title -->
      <div class="text-center lg:text-left">
        <h1 class="text-4xl lg:text-2xl font-bold text-[#1E1E1E] mb-2 leading-snug">Getting started!</h1>
        <p class="text-lg lg:text-sm text-[#1E1E1E] lg:leading-relaxed lg:max-w-[90%]">
          Help us understand what are we working on today by providing the following information:
        </p>
      </div>

      <!-- Form -->
      <form action="pages/dynamic_page.php" method="POST" class="space-y-6">

        <!-- Campus -->
        <div>
          <select id="campus" name="campus_id" class="w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]" required>
            <option value="" hidden>Campus</option>
            <?php
            $result = $conn->query("SELECT id, campus_name FROM tbl_campus ORDER BY campus_name");
            if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $selected = ($selectedCampusId == $row['id']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($row['id']) . "' {$selected}>" . htmlspecialchars($row['campus_name']) . "</option>";
              }
            }
            ?>
          </select>
        </div>

        <!-- Division -->
        <div>
          <select id="division" name="division_id" class="w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]" required>
            <option value="" hidden>Division</option>
            <?php
            $result_division = $conn->query("SELECT id, division_name FROM tbl_division ORDER BY division_name");
            if ($result_division && $result_division->num_rows > 0) {
              while ($row_division = $result_division->fetch_assoc()) {
                $selected = ($selectedDivisionId == $row_division['id']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($row_division['id']) . "' {$selected}>" . htmlspecialchars($row_division['division_name']) . "</option>";
              }
            }
            ?>
          </select>
        </div>

        <!-- Unit -->
        <div>
          <select id="unit" name="unit_id" class="w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]" required disabled>
            <option value="" hidden>Unit</option>
          </select>
        </div>

        <!-- Customer Type -->
        <div>
          <select id="customer_type" name="customer_type_id" class="w-full border border-[#1E1E1E] rounded-md p-5 lg:px-3 lg:py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]" required>
            <option value="" hidden>Customer Type</option>
            <?php
            $result_customer_type = $conn->query("SELECT id, customer_type FROM tbl_customer_type ORDER BY customer_type");
            if ($result_customer_type && $result_customer_type->num_rows > 0) {
              while ($row_customer_type = $result_customer_type->fetch_assoc()) {
                // No pre-selection for customer type from URL in this implementation
                echo "<option value='" . htmlspecialchars($row_customer_type['id']) . "'>" . htmlspecialchars($row_customer_type['customer_type']) . "</option>";
              }
            }
            ?>
          </select>
        </div>

        <!-- Transaction Type -->
        <div>
          <label class="block text-[#1E1E1E] text-xl lg:text-sm mb-2 leading-snug">Transaction Type <span class="text-red-500">*</span></label>
          <div class="space-y-2">
            <label class="flex items-center space-x-2">
              <input type="radio" name="transaction_type" value="Face-to-Face"
                class="text-[#064089] focus:ring-[#064089] w-4 h-4" required>
              <span class="text-lg text-[#1E1E1E] leading-relaxed lg:text-sm">Face-to-Face</span>
            </label>
            <label class="flex items-center space-x-2">
              <input type="radio" name="transaction_type" value="Online"
                class="text-[#064089] focus:ring-[#064089] w-4 h-4" required>
              <span class="text-lg text-[#1E1E1E] leading-relaxed lg:text-sm">Online</span>
            </label>
          </div>
        </div>

        <!-- Purpose of Visit -->
        <div>
          <label class="block text-[#1E1E1E] text-xl lg:text-sm mb-2 leading-snug">Purpose of Visit or Transaction <span class="text-red-500">*</span></label>
          <textarea
            name="purpose"
            rows="3"
            class="w-full border border-[#1E1E1E] rounded-md px-3 py-2 text-lg lg:text-sm text-[#1E1E1E] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#064089]"
            placeholder="Enter purpose here..."
            required></textarea>
        </div>

        <!-- Button -->
        <div class="flex lg:justify-end w-full lg:w-auto">
          <button type="submit" class="bg-[#064089] hover:bg-blue-900 text-white text-sm font-medium px-6 py-2 rounded-md shadow-md transition w-full lg:w-auto">
            Next
          </button>
        </div>

      </form>

    </div>
  </div>

  <!-- Footer (left under the white div) -->
  <div class="w-full max-w-[90%] mx-6 mt-4 mb-10 text-left">
    <p class="text-[#F1F7F9] text-s">
      © University of Rizal System - Customer Satisfaction Survey System
    </p>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const campusSelect = document.getElementById('campus');
      const divisionSelect = document.getElementById('division');
      const unitSelect = document.getElementById('unit');
      const unitToSelect = <?= json_encode($selectedUnitName) ?>;

      function fetchUnits() {
        const campusId = campusSelect.value;
        const divisionId = divisionSelect.value;

        // Reset and disable unit dropdown, keeping the placeholder
        unitSelect.length = 1;
        unitSelect.disabled = true;

        if (campusId && divisionId) {
          fetch(`pages/get_cascading_data.php?campus_id=${campusId}&division_id=${divisionId}`)
            .then(response => response.ok ? response.json() : Promise.reject('Network response was not ok.'))
            .then(data => {
              // Only enable if there are units to show
              if (data.length > 0) {
                data.forEach(unit => {
                  const option = document.createElement('option');
                  option.value = unit.id;
                  option.textContent = unit.unit_name;
                  unitSelect.appendChild(option);
                });
                unitSelect.disabled = false;

                // After populating, try to select the unit from the URL parameter
                if (unitToSelect) {
                  for (let i = 0; i < unitSelect.options.length; i++) {
                    if (unitSelect.options[i].text === unitToSelect) {
                      unitSelect.selectedIndex = i;
                      break;
                    }
                  }
                }
              }
            })
            .catch(error => console.error('Error fetching units:', error));
        }
      }

      fetchUnits(); // Call on page load to handle pre-filled campus/division
      campusSelect.addEventListener('change', fetchUnits);
      divisionSelect.addEventListener('change', fetchUnits);
    });
  </script>

</div>