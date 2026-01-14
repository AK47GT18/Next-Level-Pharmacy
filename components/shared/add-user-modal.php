<?php
// filepath: c:\xampp5\htdocs\Next-Level\rxpms\components\shared\add-user-modal.php

require_once __DIR__ . '/button.php';
require_once __DIR__ . '/form-input.php';

class AddUserModal {
    private string $id;

    public function __construct(string $id = 'addUserModal') {
        $this->id = $id;
    }

    public function render(): string {
        $userRoles = [
            'admin' => 'Administrator',
            'cashier' => 'Cashier',
            'manager' => 'Manager'
        ];

        $roleOptions = '';
        foreach ($userRoles as $value => $label) {
            $roleOptions .= "<option value='{$value}'>{$label}</option>";
        }

        $html = <<<HTML
        <div id="{$this->id}" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden transform transition-all animate-slide-in">
                <div class="flex items-center justify-between p-6 bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                    <h3 class="text-lg font-bold">Add New User</h3>
                    <button type="button" data-modal-close class="p-2 hover:bg-white/20 rounded-lg transition"><i class="fas fa-times"></i></button>
                </div>
                <form id="addUserForm" class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
HTML;
        
        $html .= '<div class="md:col-span-2">' . (new FormInput('text', 'name', 'Full Name', ['required' => true, 'placeholder' => 'e.g., John Doe', 'icon' => 'fas fa-user']))->render() . '</div>';
        $html .= '<div class="md:col-span-2">' . (new FormInput('email', 'email', 'Email Address', ['required' => true, 'placeholder' => 'e.g., john@example.com', 'icon' => 'fas fa-envelope']))->render() . '</div>';
        $html .= (new FormInput('password', 'password', 'Password', ['required' => true, 'placeholder' => 'Create a strong password', 'icon' => 'fas fa-lock']))->render();
        $html .= (new FormInput('tel', 'phone', 'Phone Number', ['placeholder' => 'e.g., +265999123456', 'icon' => 'fas fa-phone']))->render();
        
        $html .= <<<HTML
                        <div>
                            <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                            <select id="role" name="role" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="">Select a role</option>
                                {$roleOptions}
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button type="button" data-modal-cancel class="px-4 py-2.5 rounded-lg bg-gray-100 text-gray-700 font-medium hover:bg-gray-200 transition">Cancel</button>
HTML;
        
        $html .= (new Button('Create User', 'submit', 'blue', 'fa-user-plus'))->render();
        
        $html .= <<<HTML
                    </div>
                </form>
            </div>
        </div>

        <script>
        (function(){
            const modal = document.getElementById("{$this->id}");
            if (!modal) return;

            function show() { 
                modal.classList.remove('hidden'); 
                modal.classList.add('flex'); 
            }
            
            function hide() { 
                modal.classList.remove('flex'); 
                modal.classList.add('hidden'); 
                modal.querySelector('form')?.reset(); 
            }

            document.querySelectorAll('[data-open-add-user]').forEach(btn => btn.addEventListener('click', show));
            modal.querySelectorAll('[data-modal-close], [data-modal-cancel]').forEach(el => el.addEventListener('click', hide));
            modal.addEventListener('click', (e) => { if(e.target === modal) hide(); });

            // Handle form submission
            document.getElementById('addUserForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                try {
                    const response = await fetch('api/users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (response.ok) {
                        alert('User created successfully!');
                        hide();
                        window.location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Failed to create user'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Failed to create user');
                }
            });
        })();
        </script>
HTML;
        return $html;
    }
}
?>