<?php
session_start();

// Authentication check - must be BEFORE any output
require_once './includes/check-auth.php';

// Set default user info if not logged in (for development)
// For testing purposes, force the user role to 'admin' to bypass restrictions.
$_SESSION['user_name'] = 'Admin (Testing)';
$_SESSION['role'] = 'admin';

// add BASE_URL after session_start()
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($baseUrl === '/') {
	$baseUrl = '';
}
define('BASE_URL', $baseUrl);

// Include component classes
require_once './components/layout/sidebar.php';
require_once './components/layout/header.php';
require_once './components/layout/mobile-menu.php';
require_once './includes/PathHelper.php'; // This path must be correct
require_once './includes/helpers.php'; // Load helper functions

// Load dashboard-specific components globally since they are used on the main page
require_once './components/dashboard/stat-card.php';
require_once './components/dashboard/sales-table.php';
require_once './components/dashboard/low-stock-alert.php';
require_once './components/dashboard/quick-actions.php';
require_once './components/shared/modal.php'; // Add Modal component
require_once './components/shared/card.php';
require_once './components/shared/button.php';
require_once './components/shared/table.php';
require_once './components/shared/form-input.php';
require_once './components/shared/loading.php';
require_once './components/widgets/notification-bell.php'; // Add NotificationBell component

// Get current page from URL hash, default to dashboard
$currentPage = $_GET['page'] ?? 'dashboard';

// Instantiate layout components
$sidebar = new Sidebar($currentPage);
$header = new Header();
$mobileMenu = new MobileMenu();

// --- Sample Notifications ---
$sampleNotifications = [
	['type' => 'alert', 'title' => 'Low Stock', 'message' => 'Paracetamol is running low. 15 units left.', 'created_at' => '2025-11-08 14:00:00', 'read' => false],
	['type' => 'success', 'title' => 'Sale Completed', 'message' => 'Sale INV-001 for MWK 125,500 completed.', 'created_at' => '2025-11-08 13:50:00', 'read' => false],
	['type' => 'info', 'title' => 'New Supplier Added', 'message' => 'MediSupply Ltd. has been added.', 'created_at' => '2025-11-07 10:00:00', 'read' => true],
];
$notificationBell = new NotificationBell($sampleNotifications);
// --- End Sample Notifications ---

// To test the modal, uncomment one of these lines:
// setFlash('success', 'The operation was completed successfully!');
// setFlash('error', 'An unexpected error occurred. Please try again.');

?>
<!doctype html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Next-Level - Pharmacy Management System</title>

	<!-- Font Awesome -->
	<link rel="stylesheet" href="<?= PathHelper::asset('assets/fontawesome/css/all.min.css') ?>">


	<!-- Custom CSS -->
	<?= PathHelper::loadCSS('assets/css/styles.css') ?>
	<?= PathHelper::loadCSS('assets/css/animations.css') ?>

	<!-- Inline Critical CSS -->
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

		* {
			font-family: 'Inter', sans-serif;
		}
        
        /* Tabular numbers for all data-heavy elements */
        .font-numeric, td, .stat-value, .currency {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
            letter-spacing: -0.01em;
        }

		.glassmorphism {
			background: rgba(255, 255, 255, 0.92);
			backdrop-filter: blur(20px);
			border: 1px solid rgba(255, 255, 255, 0.6);
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
		}

		.gradient-bg {
            /* Premium Rich Blue Gradient */
			background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
		}

		.active-nav {
            background-color: rgba(255, 255, 255, 0.12);
            border-right: 3px solid #60a5fa;
            backdrop-filter: blur(4px);
		}

		.table-row-hover:hover {
			background-color: #f8fafc;
            transform: translateY(0);
		}

		.custom-scrollbar::-webkit-scrollbar {
			width: 6px;
            height: 6px;
		}

		.custom-scrollbar::-webkit-scrollbar-thumb {
			background: #cbd5e1;
			border-radius: 10px;
		}
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

		.custom-scrollbar::-webkit-scrollbar-track {
			background: transparent;
		}
	</style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-slate-50">

	<!-- Heart Loader -->
	<div id="heart-loader"
		class="fixed inset-0 bg-white z-[100] flex items-center justify-center transition-opacity duration-500">
		<div class="heart-container">
			<div class="heart"></div>
		</div>
	</div>

	<?php
	// This will render a hidden div if a flash message is set
	displayFlashModal();
	?>
	<?= $mobileMenu->render() ?>

	<div class="flex h-screen overflow-hidden">

		<?= $sidebar->render() ?>

		<!-- Main Content -->
		<div class="flex-1 flex flex-col overflow-hidden">

			<?= $header->render($notificationBell) ?>

			<!-- Page Content -->
			<main id="main-content" class="flex-1 overflow-auto p-4 md:p-8 custom-scrollbar">
				<?php
				// Simple router to load page content
				$view = $_GET['view'] ?? null;
				if ($view) {
					// Corrected Logic: The 'view' parameter specifies the file to load within the 'page' directory.
					// e.g., for ?page=settings&view=user-profile, the path is pages/settings/user-profile.php
					$pagePath = "./pages/{$currentPage}/{$view}.php";
				} else {
					$pagePath = "./pages/{$currentPage}/index.php";
				}
				if (file_exists($pagePath)) {
					include $pagePath;
				} else {
					echo "<div class='text-center p-10'><h1 class='text-2xl font-bold'>Page not found</h1><p>The requested page '{$currentPage}' could not be found.</p></div>";
				}
				?>
			</main>
		</div>
	</div>

	<!-- JavaScript -->
	<?= PathHelper::loadJS('assets/js/chart.js') ?>
	<!-- Chart.js should be loaded first if other scripts depend on it -->
	<?= PathHelper::loadJS('assets/js/main.js', true) ?>

	<script>
		document.addEventListener('DOMContentLoaded', () => {
			// Hide loader once content is loaded
			const loader = document.getElementById('heart-loader');
			if (loader) {
				setTimeout(() => {
					loader.classList.add('opacity-0');
					setTimeout(() => loader.style.display = 'none', 500);
				}, 300); // Artificial delay to ensure loader is visible
			}

			// --- Generic Dropdown Toggle Logic (for notification bell) ---
			document.addEventListener('click', function (e) {
				const toggle = e.target.closest('[data-dropdown-toggle]');
				if (toggle) {
					const dropdownId = toggle.getAttribute('data-dropdown-toggle');
					const dropdown = document.getElementById(dropdownId);
					if (dropdown) {
						dropdown.classList.toggle('hidden');
					}
				} else if (!e.target.closest('[data-notification-bell]')) {
					document.getElementById('notificationDropdown')?.classList.add('hidden');
				}
			});
		});
	</script>
</body>

</html>