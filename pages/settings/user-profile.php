<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../components/shared/confirmation-modal.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo "Please log in first.";
    exit;
}

$db_instance = Database::getInstance();
$conn = $db_instance->getConnection();
$userClass = new User($conn);
$user = $userClass->getById($userId);

if (!$user) {
    echo "User not found.";
    exit;
}
?>

<div class="space-y-6 animate-slide-in">
    <!-- Header with Back and Logout buttons -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-1">User Profile</h1>
            <p class="text-gray-600">Manage your personal information and password.</p>
        </div>
        <div class="flex items-center gap-4">
            <!-- Back to Settings -->
            <a href="?page=settings" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Back to Settings
            </a>
            <!-- Logout Button - Now properly placed top-right -->
            <a href="<?= PathHelper::getBaseUrl() ?>/logout.php" 
               class="px-4 py-2 bg-rose-100 text-rose-700 rounded-lg hover:bg-rose-200 transition-all flex items-center gap-2 font-medium"
               onclick="return confirm('Are you sure you want to log out?')">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <!-- Profile Form Card -->
    <div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100">
        <form id="userProfileForm" class="space-y-6">
            <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']) ?>">
           
            <!-- Avatar and User Info Section -->
            <div class="flex items-center gap-6 pb-6 border-b border-gray-100">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=3b82f6&color=fff&size=96&bold=true"
                     class="w-24 h-24 rounded-2xl ring-4 ring-white/50 shadow-lg" 
                     alt="<?= htmlspecialchars($user['name']) ?>">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($user['name']) ?></h2>
                    <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($user['email']) ?></p>
                    <p class="text-xs text-gray-400 mt-1">Role: <?= htmlspecialchars(ucfirst($user['role'])) ?></p>
                </div>
            </div>

            <!-- Form Fields -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="name" id="name" value="<?= htmlspecialchars($user['name']) ?>" required 
                               class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" required 
                               class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <div class="relative">
                        <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="tel" name="phone" id="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Role</label>
                    <div class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl flex items-center gap-2">
                        <i class="fas fa-user-shield text-gray-400"></i>
                        <span class="font-medium text-gray-700"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New Password (optional)</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="password" name="password" id="password" placeholder="Leave blank to keep current password" 
                           class="w-full pl-10 pr-4 py-3 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <p class="text-xs text-gray-500 mt-2">Only fill this if you want to change your password.</p>
            </div>

            <div class="flex justify-end mt-8 pt-6 border-t border-gray-100">
                <button type="submit" id="saveProfileBtn" 
                        class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg hover:-translate-y-0.5 transform transition-all font-semibold flex items-center gap-2">
                    <i class="fas fa-save"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Removed old centered logout button from here -->
</div>

<!-- Success Modal -->
<?php echo (new ConfirmationModal('successModal', 'Success!', 'Profile updated successfully!', 'OK', '', 'green', 'success'))->render(); ?>

<script>
document.getElementById('userProfileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const saveBtn = document.getElementById('saveProfileBtn');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('<?= PathHelper::getBaseUrl() ?>/api/auth/users.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (response.ok) {
            // Optional: Show success modal instead of alert
            document.getElementById('successModal').classList.remove('hidden');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alert('Error: ' + (result.message || 'Failed to update profile'));
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update profile. Please try again.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
});
</script>