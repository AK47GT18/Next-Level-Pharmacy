<?php
require_once '../../includes/auth.php';
require_once '../../classes/Database.php';
require_once '../../classes/Sale.php';
require_once '../../classes/Medicine.php';

checkAuth(['admin', 'pharmacist', 'cashier']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    https_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$database = new Database();
$db = $database->connect();
$sale = new Sale($db);
$medicine = new Medicine($db);

try {
    // Get date range
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    // Collect all statistics
    $stats = [
        'sales' => $sale->getDashboardStats($start_date, $end_date),
        'inventory' => $medicine->getDashboardStats(),
        'alerts' => [
            'low_stock' => $medicine->getLowStockItems(),
            'expiring_soon' => $medicine->getExpiringSoonItems(),
            'out_of_stock' => $medicine->getOutOfStockItems()
        ],
        'chart_data' => [
            'daily_sales' => $sale->getDailySalesData($start_date, $end_date),
            'top_selling' => $sale->getTopSellingItems($start_date, $end_date, 5)
        ]
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'Dashboard stats retrieved successfully',
        'data' => $stats
    ]);

} catch (Exception $e) {
    logError('dashboard_stats_failed', $e->getMessage());
    https_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve dashboard stats'
    ]);
}

// filepath: c:\xampp5\htdocs\Next-Level\rxpms\components\dashboard\stat-card.php

class StatCard {
    private $title;
    private $value;
    private $icon;
    private $growth;
    private $color;
    private $subtitle;

    public function __construct($title, $value, $icon, $growth = null, $color = 'blue', $subtitle = '') {
        $this->title = $title;
        $this->value = $value;
        $this->icon = $icon;
        $this->growth = $growth;
        $this->color = $color;
        $this->subtitle = $subtitle;
    }

    public function render() {
        $colorClasses = $this->getColorClasses($this->color);
        
        // ✅ Only show growth if it's not null
        $growthHtml = '';
        if ($this->growth !== null) {
            $growthClass = $this->growth >= 0 ? 'text-emerald-600' : 'text-rose-600';
            $growthIcon = $this->growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
            $growthHtml = "
                <div class='flex items-center gap-1 mt-2'>
                    <i class='fas {$growthIcon} text-xs {$growthClass}'></i>
                    <span class='{$growthClass} text-sm font-semibold'>" . abs($this->growth) . "%</span>
                </div>
            ";
        }

        return "
            <div class='glassmorphism rounded-2xl shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:scale-105'>
                <div class='flex items-start justify-between mb-4'>
                    <div>
                        <p class='text-gray-600 text-sm font-medium'>{$this->title}</p>
                    </div>
                    <div class='{$colorClasses['bg']} rounded-xl p-3'>
                        <i class='fas {$this->icon} {$colorClasses['text']} text-lg'></i>
                    </div>
                </div>
                
                <div class='space-y-2'>
                    <p class='text-2xl md:text-3xl font-bold text-gray-900'>{$this->value}</p>
                    <p class='text-gray-500 text-xs'>{$this->subtitle}</p>
                    {$growthHtml}
                </div>
            </div>
        ";
    }

    private function getColorClasses($color) {
        $colors = [
            'blue' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-600'],
            'indigo' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-600'],
            'amber' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600'],
            'purple' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-600'],
            'emerald' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600'],
            'rose' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-600'],
        ];

        return $colors[$color] ?? $colors['blue'];
    }
}