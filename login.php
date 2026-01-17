<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\login.php
require_once './includes/PathHelper.php';
require_once './includes/helpers.php';

// ✅ Only start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user is already logged in, redirect them to the dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . PathHelper::getBaseUrl() . '/index.php?page=dashboard');
    exit;
}

define('BASE_URL', PathHelper::getBaseUrl());
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Next-Level</title>
    <link rel="stylesheet" href="<?= PathHelper::asset('assets/fontawesome/css/all.min.css') ?>">

    <!-- Custom CSS -->
    <?= PathHelper::loadCSS('assets/css/styles.css') ?>
    <?= PathHelper::loadCSS('assets/css/animations.css') ?>
    <style>
        body {
            background-color: #f0f2f5;
        }

        .glassmorphism {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md p-8 space-y-6 glassmorphism rounded-2xl shadow-lg border border-gray-100">
        <div class="text-center">
            <div class="inline-block p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                <i class="fas fa-prescription-bottle-medical text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mt-4">Welcome Back</h1>
            <p class="text-gray-500">Sign in to your Next-Level Pharmacy account</p>
        </div>

        <form id="loginForm" class="space-y-5">
            <!-- Error Message Display -->
            <div id="error-message" class="hidden p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg text-sm">
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="text" name="email" id="email" required placeholder="Enter email or username"
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="password" id="password" required placeholder="••••••••"
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition" />
                </div>
            </div>

            <div>
                <button type="submit" id="loginButton"
                    class="w-full px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2">
                    <span id="button-text">Sign In</span>
                    <i id="button-spinner" class="fas fa-spinner fa-spin hidden"></i>
                </button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const errorDiv = document.getElementById('error-message');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('button-text');
            const buttonSpinner = document.getElementById('button-spinner');

            // Reset UI
            errorDiv.classList.add('hidden');
            errorDiv.textContent = '';
            loginButton.disabled = true;
            buttonText.classList.add('hidden');
            buttonSpinner.classList.remove('hidden');

            try {
                const response = await fetch('<?= BASE_URL ?>/api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    // Check if there was a redirect URL stored
                    const urlParams = new URLSearchParams(window.location.search);
                    const redirectParam = urlParams.get('redirect');

                    // Determine the redirect URL
                    let redirectUrl;

                    if (redirectParam) {
                        // Use the redirect parameter from URL
                        redirectUrl = redirectParam;
                    } else if (result.redirect) {
                        // Use the redirect from server response
                        redirectUrl = result.redirect;
                    } else {
                        // Default redirect to dashboard via index.php
                        redirectUrl = '<?= BASE_URL ?>/index.php?page=dashboard';
                    }

                    // Redirect to the final URL
                    window.location.href = redirectUrl;
                } else {
                    errorDiv.textContent = result.message || 'An unknown error occurred.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Login request failed:', error);
                errorDiv.textContent = 'Could not connect to the server. Please try again later.';
                errorDiv.classList.remove('hidden');
            } finally {
                loginButton.disabled = false;
                buttonText.classList.remove('hidden');
                buttonSpinner.classList.add('hidden');
            }
        });
    </script>

</body>

</html>