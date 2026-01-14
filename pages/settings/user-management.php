<?php
// This file is included by the root index.php, so the session is already started.
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../includes/PathHelper.php';
require_once __DIR__ . '/../../components/shared/confirmation-modal.php';
require_once __DIR__ . '/../../components/shared/edit-user-modal.php';
require_once __DIR__ . '/../../components/shared/add-user-modal.php';

$db_instance = Database::getInstance();
$conn = $db_instance->getConnection();
$user = new User($conn);

$users = $user->getAll();
if (!defined('BASE_URL')) {
    define('BASE_URL', PathHelper::getBaseUrl());
}
?>

<div class="space-y-6 animate-slide-in">
    <div>
        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-1">User Management</h1>
        <p class="text-gray-600">Manage user accounts and roles within the system.</p>
    </div>

    <div class="glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">System Users</h2>
            <button data-open-add-user class="px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg transition-all font-semibold flex items-center gap-2">
                <i class="fas fa-user-plus"></i>Add New User
            </button>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Name</th>
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Email</th>
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Phone</th>
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Role</th>
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-blue-50/50 transition group" data-user-id="<?= $u['id'] ?>">
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['name']) ?>&background=3b82f6&color=fff&size=32"
                                         class="w-8 h-8 rounded-lg" alt="<?= htmlspecialchars($u['name']) ?>">
                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($u['name']) ?></span>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="py-4 px-4 text-sm text-gray-600"><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></td>
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-bold bg-blue-100 text-blue-700">
                                    <?= htmlspecialchars(ucfirst($u['role'])) ?>
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700">
                                    <i class="fas fa-circle text-[6px]"></i>Active
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex gap-2">
                                    <button class="edit-user-btn p-2 w-8 h-8 flex items-center justify-center rounded-lg text-blue-600 hover:bg-blue-100 hover:text-blue-800 transition" data-user-id="<?= $u['id'] ?>" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-user-btn p-2 w-8 h-8 flex items-center justify-center rounded-lg text-rose-600 hover:bg-rose-100 hover:text-rose-800 transition" data-user-id="<?= $u['id'] ?>" data-user-name="<?= htmlspecialchars($u['name']) ?>" title="Delete User">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
echo (new AddUserModal())->render();
echo (new EditUserModal())->render();
echo (new ConfirmationModal('deleteUserModal', 'Confirm Deletion', 'Are you sure you want to delete this user?', 'Delete', 'Cancel', 'red', 'delete'))->render();
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = '<?= BASE_URL ?>';

    // ✅ Edit user functionality
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            
            try {
                const response = await fetch(`${BASE_URL}/api/users.php?id=${userId}`);
                const data = await response.json();

                if (data.success && data.user) {
                    window.openEditUserModal(data.user);
                } else {
                    alert('Failed to load user data');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load user data');
            }
        });
    });

    // ✅ Delete user functionality
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            
            const deleteModal = document.getElementById('deleteUserModal');
            const modalText = deleteModal.querySelector('p');
            
            modalText.textContent = `Are you sure you want to delete "${userName}"? This action cannot be undone.`;
            deleteModal.dataset.userId = userId;
            
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');

            // ✅ Set up the confirm button
            const confirmBtn = deleteModal.querySelector('[data-modal-confirm="true"]');
            if (confirmBtn) {
                confirmBtn.onclick = async function() {
                    try {
                        const response = await fetch(`${BASE_URL}/api/users.php?id=${userId}`, {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include'
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            alert('User deleted successfully!');
                            // Remove the row from table
                            document.querySelector(`tr[data-user-id="${userId}"]`)?.remove();
                            deleteModal.classList.add('hidden');
                            deleteModal.classList.remove('flex');
                        } else {
                            alert('Error: ' + (result.message || 'Failed to delete user'));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Failed to delete user: ' + error.message);
                    }
                };
            }
        });
    });
});
</script>