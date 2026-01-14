<?php
require_once __DIR__ . '/../../components/shared/button.php';
?>

<div class="space-y-8">
    <!-- Page Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="text-gray-500">Manage your pharmacy's configuration and preferences.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Navigation -->
        <div class="lg:col-span-1">
            <div class="glassmorphism rounded-2xl p-4">
                <nav class="space-y-1">
                    <a href="?page=settings" class="flex items-center gap-3 px-4 py-2 <?= !isset($_GET['view']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg font-semibold">
                        <i class="fas fa-store-alt w-5"></i>
                        <span>Pharmacy Details</span>
                    </a>
                    <a href="?page=settings&view=user-profile" class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'user-profile' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                        <i class="fas fa-user-circle w-5"></i>
                        <span>User Profile</span>
                    </a>
                    <a href="?page=settings&view=user-management" class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'user-management' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                        <i class="fas fa-users-cog w-5"></i>
                        <span>User Management</span>
                    </a>
                    <a href="?page=settings&view=notification-settings" class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'notification-settings' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                        <i class="fas fa-bell w-5"></i>
                        <span>Notification Settings</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Right Column: Content -->
        <div class="lg:col-span-2">
            <?php
            $view = $_GET['view'] ?? 'details';
            $view_path = __DIR__ . '/' . $view . '.php';

            if (file_exists($view_path)) {
                include $view_path;
            } else {
                // Default view: Pharmacy Details
                require_once __DIR__ . '/../../components/shared/card.php';
                $pharmacyDetailsForm = <<<HTML
                <form class="space-y-4">
                    <div>
                        <label class="font-semibold text-gray-700">Pharmacy Name</label>
                        <input type="text" value="Next-Level Pharmacy Malawi" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="font-semibold text-gray-700">Address</label>
                        <input type="text" value="Rumphi, Livingstonia" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="font-semibold text-gray-700">Contact Phone</label>
                        <input type="text" value="+265 999 123 456" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                </form>
                HTML;

                echo (new Card('Pharmacy Details', $pharmacyDetailsForm, (new Button('Save Changes', 'submit', 'blue'))->render()))->render();
            }
            ?>
        </div>
    </div>
</div>