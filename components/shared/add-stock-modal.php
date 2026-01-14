<?php
class AddStockModal {
    private string $id;
    private array $medicines;

    public function __construct(array $medicines, string $id = 'addStockModal') {
        $this->id = $id;
        $this->medicines = $medicines;
    }

    public function render(): string {
        $medicineOptions = '';
        foreach ($this->medicines as $medicine) {
            $medicineOptions .= "<option value='{$medicine['id']}'>{$medicine['name']} (Current Stock: {$medicine['stock']})</option>";
        }

        return <<<HTML
<div id="{$this->id}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="flex items-center justify-between p-4 border-b">
      <h3 class="text-lg font-bold">Add Stock to Inventory</h3>
      <button data-modal-close class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"><i class="fas fa-times"></i></button>
    </div>
    <form id="addStockForm" class="p-6 space-y-4">
      <div class="space-y-4">
        <div>
            <label class="font-semibold text-gray-700">Select Medicine</label>
            <select name="medicine_id" required class="mt-1 w-full p-3 bg-white border border-gray-200 rounded-xl">
                <option value="">-- Choose a medicine --</option>
                {$medicineOptions}
            </select>
            <p class="text-xs text-gray-500 mt-1">Tip: You can type to search when the list is open.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <?php
            echo (new FormInput('number', 'quantity', 'Quantity to Add', ['required' => true, 'min' => 1, 'placeholder' => 'e.g., 50']))->render();
            echo (new FormInput('date', 'expiry_date', 'Expiry Date of New Batch', ['required' => true, 'min' => date('Y-m-d')]))->render();
            ?>
        </div>
      </div>
      <div class="flex items-center justify-end gap-3">
        <button type="button" data-modal-cancel class="px-4 py-2 rounded-xl bg-gray-100">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white">Add Stock</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById("{$this->id}");
  if (!modal) return;

  function show() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function hide() { modal.classList.remove('flex'); modal.classList.add('hidden'); modal.querySelector('form')?.reset(); }

  document.querySelectorAll('[data-open-add-stock]').forEach(btn => btn.addEventListener('click', show));
  modal.querySelectorAll('[data-modal-close], [data-modal-cancel]').forEach(el => el.addEventListener('click', hide));

  const form = document.getElementById('addStockForm');
  form?.addEventListener('submit', async function(e){
    e.preventDefault();
    const formData = new FormData(form);
    console.log('Adding stock:', Object.fromEntries(formData.entries()));
    // In a real app, you would send this to an API endpoint like 'api/inventory/add-stock.php'
    hide();
    // Show a success toast/message
  });
})();
</script>
HTML;
    }
}
?>