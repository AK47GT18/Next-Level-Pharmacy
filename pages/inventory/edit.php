<?php
require_once '../../components/shared/card.php';
require_once '../../components/shared/button.php';
require_once '../../components/shared/form-input.php';
require_once '../../config/constants.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$medicine = json_decode(file_get_contents("https://localhost/Next-Level/rxpms/api/inventory/get.php?id=$id"), true);

$backButton = new Button('Cancel', 'button', 'gray', 'fa-times', [
    'onclick' => "window.location.href='view.php?id=$id'"
]);

$saveButton = new Button('Save Changes', 'submit', 'blue', 'fa-save', [
    'form' => 'editMedicineForm'
]);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <?= $backButton->render() ?>
            <h1 class="text-2xl font-bold text-gray-900">Edit Medicine</h1>
        </div>
        <?= $saveButton->render() ?>
    </div>

    <!-- Edit Form -->
    <form id="editMedicineForm" class="space-y-6" onsubmit="handleSubmit(event)">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Basic Information -->
            <div class="lg:col-span-2 space-y-6">
                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Basic Information</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <?php
                        echo (new FormInput('text', 'name', 'Medicine Name', [
                            'required' => true,
                            'value' => $medicine['name']
                        ]))->render();

                        echo (new FormInput('text', 'generic_name', 'Generic Name', [
                            'required' => true,
                            'value' => $medicine['generic_name']
                        ]))->render();

                        echo (new FormInput('text', 'code', 'Medicine Code', [
                            'required' => true,
                            'value' => $medicine['code']
                        ]))->render();

                        echo (new FormInput('select', 'category', 'Category', [
                            'required' => true,
                            'value' => $medicine['category'],
                            'options' => MEDICINE_CATEGORIES
                        ]))->render();

                        echo (new FormInput('text', 'manufacturer', 'Manufacturer', [
                            'required' => true,
                            'value' => $medicine['manufacturer']
                        ]))->render();
                        ?>
                    </div>
                </div>

                <!-- Description and Usage -->
                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Description & Usage</h3>
                    <div class="space-y-6">
                        <?php
                        echo (new FormInput('textarea', 'description', 'Description', [
                            'required' => true,
                            'value' => $medicine['description'],
                            'rows' => 4
                        ]))->render();

                        echo (new FormInput('textarea', 'usage', 'Usage Instructions', [
                            'required' => true,
                            'value' => $medicine['usage'],
                            'rows' => 4
                        ]))->render();

                        echo (new FormInput('textarea', 'side_effects', 'Side Effects', [
                            'value' => $medicine['side_effects'],
                            'rows' => 4
                        ]))->render();
                        ?>
                    </div>
                </div>
            </div>

            <!-- Stock and Pricing -->
            <div class="space-y-6">
                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Stock Information</h3>
                    <div class="space-y-6">
                        <?php
                        echo (new FormInput('number', 'quantity_in_stock', 'Current Stock', [
                            'required' => true,
                            'value' => $medicine['quantity_in_stock'],
                            'min' => 0
                        ]))->render();

                        echo (new FormInput('number', 'reorder_level', 'Reorder Level', [
                            'required' => true,
                            'value' => $medicine['reorder_level'],
                            'min' => 0
                        ]))->render();

                        echo (new FormInput('number', 'max_stock', 'Maximum Stock', [
                            'required' => true,
                            'value' => $medicine['max_stock'],
                            'min' => 0
                        ]))->render();
                        ?>
                    </div>
                </div>

                <div class="glassmorphism rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Pricing</h3>
                    <div class="space-y-6">
                        <?php
                        echo (new FormInput('number', 'cost_price', 'Cost Price', [
                            'required' => true,
                            'value' => $medicine['cost_price'],
                            'min' => 0,
                            'step' => '0.01'
                        ]))->render();

                        echo (new FormInput('number', 'selling_price', 'Selling Price', [
                            'required' => true,
                            'value' => $medicine['selling_price'],
                            'min' => 0,
                            'step' => '0.01'
                        ]))->render();
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="module">
import { api } from '../../assets/js/api.js';
import { showToast } from '../../assets/js/utils.js';

window.handleSubmit = async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    try {
        const response = await api.request('inventory/update.php', {
            method: 'POST',
            body: formData
        });

        showToast('Medicine updated successfully', 'success');
        window.location.href = `view.php?id=<?= $id ?>`;
    } catch (error) {
        showToast(error.message, 'error');
    }
};
</script>