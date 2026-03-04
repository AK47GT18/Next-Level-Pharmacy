<?php
require_once __DIR__ . '/../../components/shared/button.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance()->getConnection();
// Quick check for settings
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM pharmacy_settings");
    $db_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $db_settings = [];
}

$settings = array_merge([
    'pharmacy_name' => 'Next-Level Pharmacy Malawi',
    'pharmacy_address' => 'Rumphi, Livingstonia',
    'pharmacy_phone' => '+265 999 123 456'
], $db_settings);
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
                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="?page=settings"
                            class="flex items-center gap-3 px-4 py-2 <?= !isset($_GET['view']) ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg font-semibold">
                            <i class="fas fa-store-alt w-5"></i>
                            <span>Pharmacy Details</span>
                        </a>
                    <?php endif; ?>

                    <a href="?page=settings&view=user-profile"
                        class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'user-profile' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                        <i class="fas fa-user-circle w-5"></i>
                        <span>User Profile</span>
                    </a>

                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="?page=settings&view=user-management"
                            class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'user-management' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                            <i class="fas fa-users-cog w-5"></i>
                            <span>User Management</span>
                        </a>
                        <a href="?page=settings&view=notification-settings"
                            class="flex items-center gap-3 px-4 py-2 <?= ($_GET['view'] ?? '') === 'notification-settings' ? 'text-blue-600 bg-blue-50' : 'text-gray-600 hover:bg-gray-100' ?> rounded-lg">
                            <i class="fas fa-bell w-5"></i>
                            <span>Notification Settings</span>
                        </a>
                    <?php endif; ?>
                </nav>
                <?php
                // Role check for specific views
                $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
                $currentView = $_GET['view'] ?? 'details';
                if (!$isAdmin && $currentView !== 'user-profile') {
                    // Redirect to user profile if trying to access restricted setting
                    header('Location: index.php?page=settings&view=user-profile');
                    exit;
                }
                ?>
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
                $btn_html = (new Button('Save Changes', 'submit', 'blue'))->render();
                $pharmacyDetailsForm = <<<HTML
                <form id="pharmacyDetailsForm" class="space-y-4">
                    <div>
                        <label class="font-semibold text-gray-700">Pharmacy Name</label>
                        <input type="text" name="pharmacy_name" value="{$settings['pharmacy_name']}" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="font-semibold text-gray-700">Address</label>
                        <input type="text" name="pharmacy_address" value="{$settings['pharmacy_address']}" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="font-semibold text-gray-700">Contact Phone</label>
                        <input type="text" name="pharmacy_phone" value="{$settings['pharmacy_phone']}" class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                    </div>
                    <div class="flex justify-end pt-4">
                         {$btn_html}
                    </div>
                </form>
                <script>
                    document.getElementById('pharmacyDetailsForm').addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        const data = Object.fromEntries(formData.entries());
                        const btn = this.querySelector('button[type="submit"]');
                        const originalText = btn.innerHTML;
                        
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                        
                        try {
                            const response = await fetch('api/pharmacy-settings.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(data)
                            });
                            const result = await response.json();
                            if (result.success) {
                                alert('Settings saved successfully!');
                                window.location.reload();
                            } else {
                                alert('Error: ' + (result.message || 'Failed to save settings'));
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('Failed to save settings');
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    });
                </script>
                HTML;

                echo (new Card('Pharmacy Details', $pharmacyDetailsForm))->render();
            }
            ?>
        </div>
    </div>
</div>