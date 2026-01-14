<?php

class Table {
    private array $headers;
    private array $data;
    private array $options;

    public function __construct(
        array $headers,
        array $data,
        array $options = []
    ) {
        $this->headers = $headers;
        $this->data = $data;
        $this->options = array_merge([
            'striped' => true,
            'hover' => true,
            'responsive' => true,
            'bordered' => false
        ], $options);
    }

    public function render(): string {
        $tableClasses = $this->getTableClasses();
        $wrapper = $this->options['responsive'] ? 'overflow-x-auto custom-scrollbar' : '';

        $table = <<<HTML
        <div class="glassmorphism rounded-2xl p-6 {$wrapper}">
            <table class="{$tableClasses}">
                <thead>
                    {$this->renderHeaders()}
                </thead>
                <tbody class="divide-y divide-gray-50">
                    {$this->renderRows()}
                </tbody>
            </table>
        </div>
        HTML;

        return $table;
    }

    private function getTableClasses(): string {
        $classes = ['w-full'];
        
        if ($this->options['striped']) {
            $classes[] = 'table-striped';
        }
        if ($this->options['hover']) {
            $classes[] = 'table-hover';
        }
        if ($this->options['bordered']) {
            $classes[] = 'table-bordered';
        }

        return implode(' ', $classes);
    }

    private function renderHeaders(): string {
        $headers = '';
        foreach ($this->headers as $header) {
            $headers .= "
            <th class='text-left py-4 px-4 text-xs font-bold text-gray-600 uppercase tracking-wider'>
                {$header}
            </th>";
        }

        return "<tr class='border-b-2 border-gray-100'>{$headers}</tr>";
    }

    private function renderRows(): string {
        $rows = '';
        foreach ($this->data as $row) {
            $cells = '';
            foreach ($row as $cell) {
                $cells .= "<td class='py-4 px-4 text-sm text-gray-700'>{$cell}</td>";
            }
            $rows .= "<tr class='border-b border-gray-100 last:border-none hover:bg-blue-50/50 transition'>{$cells}</tr>";
        }
        return $rows;
    }
}