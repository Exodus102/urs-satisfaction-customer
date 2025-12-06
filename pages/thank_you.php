<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Redirect to the first page after 10 seconds -->
    <meta http-equiv="refresh" content="10;url=../index.php">
    <link rel="stylesheet" href="../Tailwind/src/output.css">
    <title>Customer Satisfaction Survey</title>
</head>

<body>
    <div class="min-h-screen flex flex-col items-center justify-between relative bg-cover bg-center"
        style="background-image: url('../resources/svg/landing-page.svg');">

        <!-- Logo -->
        <div class="flex items-center justify-center gap-2 mb-4 mt-10">
            <img src="../resources/img/new-logo.png" alt="URSatisfaction Logo" class="h-16">
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
                <div class="lg:text-left text-center">
                    <h1 class="text-2xl font-bold text-[#1E1E1E] mb-2 leading-snug">Thank you!</h1>
                    <p class="text-sm text-[#1E1E1E] lg:leading-relaxed lg:max-w-[90%]">
                        Thank you for completing the survey. Your feedback is valuable to us and will help improve our services.
                    </p>
                </div>

                <!-- Button -->
                <div class="flex justify-center pt-4">
                    <a href="../index.php"
                        class="bg-[#064089] hover:bg-blue-900 text-white text-sm font-medium px-6 py-3 rounded-md shadow-md transition w-full lg:w-auto text-center">
                        Submit another response
                    </a>
                </div>

            </div>
        </div>

        <!-- Footer (left under the white div) -->
        <div class="w-full max-w-[90%] mx-6 mt-4 mb-10 text-left">
            <p class="text-[#F1F7F9] text-s">
                © University of Rizal System - Customer Satisfaction Survey System
            </p>
        </div>

    </div>

    <script>
        (function() {
            // Push a new state to the history. This creates a new entry in the browser's history.
            history.pushState(null, null, location.href);
            // When the user clicks the back button, the 'popstate' event is triggered.
            window.onpopstate = function() {
                // Redirect to the homepage.
                window.location.href = '../index.php';
            };
        })();
    </script>
</body>

</html>