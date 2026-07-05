<?php
/*************************************************
 * CONFIG GOOGLE SHEET
 *************************************************/
require_once 'functions.php';
require_once 'db.php';
require_login();
include 'header.php';

$GOOGLE_API_KEY = 'AIzaSyALEOTYoO5Y62sgmhXKz139vD_9iStZlY8';
$SPREADSHEET_ID = '1rc5ZiHXFxvESyduS2-fKZl4O2u-DKJ__Cz-3FxgE3L0';

// Daftar sheet yang tersedia
$available_sheets = [
    'finance' => 'Finance 2025',
    '2026' => 'Finance 2026'
];

// Ambil sheet yang dipilih dari GET atau default ke finance
$selected_sheet = $_GET['sheet'] ?? 'finance';
if (!array_key_exists($selected_sheet, $available_sheets)) {
    $selected_sheet = 'finance';
}

$RANGE = $selected_sheet . '!A1:P73';

// ================= FETCH DATA =================
$url = "https://sheets.googleapis.com/v4/spreadsheets/{$SPREADSHEET_ID}/values/{$RANGE}?key={$GOOGLE_API_KEY}";
$response = file_get_contents($url);

$gs_headers = [];
$gs_data = [];

if ($response !== false) {
    $json = json_decode($response, true);

    if (!empty($json['values'])) {
        $gs_headers = array_shift($json['values']);

        foreach ($json['values'] as $row) {
            $row = array_pad($row, count($gs_headers), '');
            $data = array_combine($gs_headers, $row);

            // Normalisasi Harga Jual
            if (isset($data['Harga Jual'])) {
                $angka = preg_replace('/[^0-9]/', '', $data['Harga Jual']);
                $data['Harga Jual'] = $angka ? floatval($angka) : 0;
            }

            $gs_data[] = $data;
        }
    }
}

// Hitung total semua harga dan PPN
$total_all_harga = 0;
$total_all_ppn = 0;
foreach ($gs_data as $row) {
    $harga = floatval($row['Harga Jual'] ?? 0);
    $total_all_harga += $harga;
    $total_all_ppn += $harga * 0.11; // PPN 11%
}

/*************************************************
 * FILTER & PAGINATION
 *************************************************/
$search = $_GET['search'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Filter
$filtered_data = [];
$filtered_total_harga = 0;
$filtered_total_ppn = 0;
if ($search !== '') {
    foreach ($gs_data as $row) {
        foreach ($row as $cell) {
            if (stripos($cell, $search) !== false) {
                $filtered_data[] = $row;
                $harga = floatval($row['Harga Jual'] ?? 0);
                $filtered_total_harga += $harga;
                $filtered_total_ppn += $harga * 0.11;
                break;
            }
        }
    }
} else {
    $filtered_data = $gs_data;
    $filtered_total_harga = $total_all_harga;
    $filtered_total_ppn = $total_all_ppn;
}

// Pagination
$total_data  = count($filtered_data);
$total_pages = max(1, ceil($total_data / $limit));
$page_data   = array_slice($filtered_data, $offset, $limit);

// Total harga dan PPN per halaman
$total_harga_page = 0;
$total_ppn_page = 0;
foreach ($page_data as $row) {
    $harga = floatval($row['Harga Jual'] ?? 0);
    $total_harga_page += $harga;
    $total_ppn_page += $harga * 0.11;
}

// Hitung statistik
$average_price = $total_data > 0 ? $filtered_total_harga / $total_data : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard - CV Afshin Raya Teknik</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --primary: #6366F1;
    --primary-dark: #4F46E5;
    --secondary: #8B5CF6;
    --success: #10B981;
    --warning: #F59E0B;
    --info: #3B82F6;
    --danger: #EF4444;
    --dark: #1F2937;
    --light: #F9FAFB;
    --gray: #6B7280;
    --gray-light: #F3F4F6;
    --border: #E5E7EB;
    
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    
    --radius-sm: 8px;
    --radius: 12px;
    --radius-lg: 16px;
    --radius-xl: 24px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: var(--dark);
    line-height: 1.6;
}

/* Main Container */
.dashboard-container {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    min-height: 100vh;
    border-radius: 0;
    position: relative;
    overflow: hidden;
}

@media (min-width: 1200px) {
    .dashboard-container {
        border-radius: var(--radius-xl);
        margin: 20px;
        box-shadow: var(--shadow-xl);
    }
}

/* Modern Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
    padding: 2.5rem 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
}

.header-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

.header-icon {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: var(--shadow);
}

.header-icon i {
    font-size: 2rem;
    color: white;
}

/* Sheet Selector */
.sheet-selector-container {
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.sheet-selector-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin: 0 auto;
    max-width: 800px;
}

.sheet-selector-label {
    display: block;
    text-align: center;
    margin-bottom: 1rem;
    font-weight: 600;
    font-size: 1.1rem;
    opacity: 0.95;
}

.sheet-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.sheet-option {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius);
    padding: 1.25rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.sheet-option::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, rgba(255,255,255,0.4), transparent);
    transition: all 0.3s ease;
}

.sheet-option:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-3px);
    box-shadow: var(--shadow);
}

.sheet-option.active {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.4);
    box-shadow: var(--shadow-md);
}

.sheet-option.active::before {
    background: linear-gradient(90deg, rgba(255,255,255,0.8), rgba(255,255,255,0.4));
}

.sheet-icon {
    font-size: 1.5rem;
    margin-bottom: 0.75rem;
    opacity: 0.9;
}

.sheet-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.sheet-year {
    font-size: 0.85rem;
    opacity: 0.8;
}

.sheet-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Header Stats */
.header-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 2.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, rgba(255,255,255,0.4), transparent);
}

.stat-card:hover {
    transform: translateY(-4px);
    background: rgba(255, 255, 255, 0.18);
    box-shadow: var(--shadow-lg);
}

.stat-card .number {
    font-size: 1.75rem;
    font-weight: 700;
    display: block;
    margin-bottom: 0.25rem;
    letter-spacing: -0.5px;
}

.stat-card .label {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 500;
}

/* Modern Search */
.search-section {
    padding: 2rem 2rem 1rem;
}

.search-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.search-box {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-input-container {
    flex: 1;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 1rem 1.5rem 1rem 3rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: all 0.3s ease;
    background: var(--gray-light);
    font-weight: 500;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
}

.search-btn {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
}

.ppn-info-banner {
    margin-top: 1rem;
    background: linear-gradient(135deg, #F5F3FF, #EDE9FE);
    border: 1px solid #DDD6FE;
    border-radius: var(--radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ppn-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    box-shadow: var(--shadow-sm);
}

/* Modern Summary Cards */
.summary-section {
    padding: 0 2rem 2rem;
}

.summary-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
}

.summary-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.summary-card.ppn-card::before {
    background: linear-gradient(90deg, #8B5CF6, #7C3AED);
}

.summary-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    font-size: 1.5rem;
    box-shadow: var(--shadow);
}

.summary-card.ppn-card .summary-icon {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
}

.summary-number {
    font-size: 2.25rem;
    font-weight: 700;
    margin: 0.5rem 0;
    background: linear-gradient(135deg, var(--dark), var(--gray));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1;
}

.summary-card.ppn-card .summary-number {
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    -webkit-background-clip: text;
    background-clip: text;
}

.summary-label {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

/* Modern Table */
.table-section {
    padding: 0 2rem 2rem;
}

.table-card {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.table-header {
    padding: 1.5rem;
    background: linear-gradient(to right, var(--gray-light), #F8FAFC);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.table-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 1.1rem;
}

.table-title i {
    color: var(--primary);
}

.table-container-inner {
    overflow: auto;
    max-height: 500px;
}

/* Modern Table Design */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1000px;
}

.data-table thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table th {
    padding: 1.25rem 1rem;
    font-weight: 600;
    color: var(--dark);
    text-align: left;
    background: linear-gradient(to bottom, #F8FAFC, #F1F5F9);
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
    position: relative;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table th::after {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 1px;
    height: 20px;
    background: var(--border);
}

.data-table th:last-child::after {
    display: none;
}

.data-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid var(--gray-light);
    position: relative;
}

.data-table tbody tr::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: transparent;
    transition: all 0.3s ease;
}

.data-table tbody tr:hover::before {
    background: linear-gradient(to bottom, var(--primary), var(--secondary));
}

.data-table tbody tr:hover {
    background: linear-gradient(to right, #FEFCE8, #FEF3C7);
    transform: translateX(4px);
}

.data-table td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-light);
    font-size: 0.95rem;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.price-cell {
    font-weight: 700;
    color: var(--success);
    text-align: right;
    font-family: 'Inter', monospace;
}

.ppn-cell {
    font-weight: 700;
    color: #7C3AED;
    text-align: right;
    font-family: 'Inter', monospace;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
    color: #065F46;
}

/* Modern Pagination */
.pagination-section {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    background: var(--gray-light);
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
}

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info {
    font-size: 0.9rem;
    color: var(--gray);
    font-weight: 500;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.page-link {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius);
    background: white;
    border: 1px solid var(--border);
    color: var(--gray);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.page-link:hover {
    background: var(--gray-light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-color: var(--primary);
    color: white;
    box-shadow: var(--shadow-sm);
}

/* Modern Footer */
.footer-section {
    padding: 0 2rem 2rem;
}

.footer-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.footer-card {
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.footer-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
}

.footer-card.total-card {
    background: linear-gradient(135deg, var(--success), #059669);
}

.footer-card.ppn-card {
    background: linear-gradient(135deg, #8B5CF6, #7C3AED);
}

.footer-label {
    font-size: 1.1rem;
    font-weight: 600;
    opacity: 0.95;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.footer-amount {
    font-size: 2rem;
    font-weight: 700;
    position: relative;
    z-index: 1;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Empty State */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: var(--gray-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--gray);
    font-size: 2rem;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.empty-subtitle {
    color: var(--gray);
    margin-bottom: 2rem;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--gray-light);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 2rem 1rem;
        border-radius: 0;
    }
    
    .search-section,
    .summary-section,
    .table-section,
    .footer-section {
        padding: 1rem;
    }
    
    .header-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .stat-card .number {
        font-size: 1.5rem;
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .search-btn {
        width: 100%;
        justify-content: center;
    }
    
    .summary-container {
        grid-template-columns: 1fr;
    }
    
    .footer-container {
        grid-template-columns: 1fr;
    }
    
    .footer-amount {
        font-size: 1.75rem;
    }
    
    .table-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .pagination-container {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    
    .sheet-selector-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .header-stats {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .summary-card {
        padding: 1.5rem;
    }
    
    .footer-card {
        padding: 1.5rem;
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
}
</style>
</head>

<body>
<div class="dashboard-container fade-in">
    
    <!-- MODERN HEADER -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Finance Dashboard</h1>
            <p style="font-size: 1rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">
                Data Faktur Pajak terintegrasi langsung dari Google Sheets
            </p>
            
            <!-- Sheet Selector -->
            <div class="sheet-selector-container">
                <div class="sheet-selector-card">
                    <div class="sheet-selector-label">
                        <i class="fas fa-file-excel"></i> Pilih Sheet Data
                    </div>
                    <div class="sheet-selector-grid">
                        <?php foreach ($available_sheets as $sheet_key => $sheet_name): ?>
                            <a href="?sheet=<?php echo $sheet_key; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                               class="sheet-option <?php echo $selected_sheet == $sheet_key ? 'active' : ''; ?>">
                                <div class="sheet-icon">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div class="sheet-name"><?php echo $sheet_name; ?></div>
                                <div class="sheet-year">
                                    <?php echo $sheet_key == '2026' ? 'Tahun 2026' : 'Tahun 2025'; ?>
                                </div>
                                <?php if ($selected_sheet == $sheet_key): ?>
                                    <span class="sheet-badge">
                                        <i class="fas fa-check"></i> Aktif
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Header Stats -->
            <div class="header-stats">
                <div class="stat-card">
                    <span class="number"><?php echo $total_data; ?></span>
                    <span class="label">Total Data</span>
                </div>
                <div class="stat-card">
                    <span class="number">Rp <?php echo number_format($filtered_total_harga, 0, ',', '.'); ?></span>
                    <span class="label">Total Nilai <?php echo $search !== '' ? '(Filter)' : ''; ?></span>
                </div>
                <div class="stat-card">
                    <span class="number">Rp <?php echo number_format($filtered_total_ppn, 0, ',', '.'); ?></span>
                    <span class="label">Total PPN (11%) <?php echo $search !== '' ? '(Filter)' : ''; ?></span>
                </div>
                <div class="stat-card">
                    <span class="number"><?php echo $total_pages; ?></span>
                    <span class="label">Total Halaman</span>
                </div>
            </div>
        </div>
    </div>

    <!-- MODERN SEARCH -->
    <div class="search-section">
        <div class="search-card">
            <form method="get" class="search-box">
                <input type="hidden" name="sheet" value="<?php echo $selected_sheet; ?>">
                <div class="search-input-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Cari data di sheet <?php echo $available_sheets[$selected_sheet]; ?>..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           autocomplete="off">
                </div>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Cari
                </button>
                <?php if ($search !== ''): ?>
                    <a href="?sheet=<?php echo $selected_sheet; ?>" class="search-btn" style="background: var(--gray); text-decoration: none;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
            
            <?php if ($search !== ''): ?>
            <div class="ppn-info-banner">
                <div class="ppn-badge">
                    <i class="fas fa-percentage"></i> PPN Filter
                </div>
                <div>
                    <strong>Rp <?php echo number_format($filtered_total_ppn, 0, ',', '.'); ?></strong>
                    <span style="color: var(--gray); font-size: 0.9rem;">
                        (11% dari Total Nilai: Rp <?php echo number_format($filtered_total_harga, 0, ',', '.'); ?>)
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODERN SUMMARY CARDS -->
    <div class="summary-section">
        <div class="summary-container">
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="summary-number"><?php echo count($filtered_data); ?></div>
                <div class="summary-label">Data Difilter</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="summary-number">Rp <?php echo number_format($total_harga_page, 0, ',', '.'); ?></div>
                <div class="summary-label">Total Halaman Ini</div>
            </div>
            
            <div class="summary-card">
                <div class="summary-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="summary-number"><?php echo $page; ?> / <?php echo $total_pages; ?></div>
                <div class="summary-label">Halaman Saat Ini</div>
            </div>
            
            <div class="summary-card ppn-card">
                <div class="summary-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="summary-number">Rp <?php echo number_format($total_ppn_page, 0, ',', '.'); ?></div>
                <div class="summary-label">PPN Halaman Ini (11%)</div>
            </div>
        </div>
    </div>

    <!-- MODERN TABLE -->
    <div class="table-section">
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-table"></i>
                    Data <?php echo $available_sheets[$selected_sheet]; ?>
                    <span class="ppn-badge">
                        <i class="fas fa-percentage"></i> PPN Total: Rp <?php echo number_format($filtered_total_ppn, 0, ',', '.'); ?>
                    </span>
                </div>
                <div style="font-size: 0.9rem; color: var(--gray);">
                    Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?>
                </div>
            </div>
            
            <?php if (empty($page_data)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="empty-title">Data Tidak Ditemukan</div>
                    <div class="empty-subtitle">
                        <?php if ($search !== ''): ?>
                            Tidak ada data yang sesuai dengan pencarian "<?php echo htmlspecialchars($search); ?>" di sheet <?php echo $available_sheets[$selected_sheet]; ?>
                        <?php else: ?>
                            Tidak ada data di sheet <?php echo $available_sheets[$selected_sheet]; ?>
                        <?php endif; ?>
                    </div>
                    <a href="?sheet=<?php echo $selected_sheet; ?>" class="search-btn" style="display: inline-flex; text-decoration: none;">
                        <i class="fas fa-redo"></i> Tampilkan Semua Data
                    </a>
                </div>
            <?php else: ?>
                <div class="table-container-inner">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <?php foreach ($gs_headers as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                                <th>PPN (11%)</th>
                            </tr>
                        </thead>
                        
                        <tbody>
                            <?php foreach ($page_data as $index => $row): ?>
                                <?php 
                                $harga = floatval($row['Harga Jual'] ?? 0);
                                $ppn = $harga * 0.11;
                                ?>
                                <tr>
                                    <?php foreach ($gs_headers as $header): ?>
                                        <td class="<?php echo $header === 'Harga Jual' ? 'price-cell' : ''; ?>">
                                            <?php if ($header === 'Harga Jual'): ?>
                                                Rp <?php echo number_format($harga, 0, ',', '.'); ?>
                                            <?php elseif (in_array($header, ['Status', 'Kelompok'])): ?>
                                                <span class="status-badge">
                                                    <?php echo htmlspecialchars($row[$header] ?? ''); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($row[$header] ?? ''); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="ppn-cell">
                                        Rp <?php echo number_format($ppn, 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- MODERN PAGINATION -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-section">
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Menampilkan <strong><?php echo count($page_data); ?></strong> dari <strong><?php echo $total_data; ?></strong> data
                                di sheet <strong><?php echo $available_sheets[$selected_sheet]; ?></strong>
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?sheet=<?php echo $selected_sheet; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link"
                                               href="?sheet=<?php echo $selected_sheet; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?sheet=<?php echo $selected_sheet; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODERN FOOTER -->
    <div class="footer-section">
        <div class="footer-container">
            <div class="footer-card total-card">
                <div class="footer-label">
                    <i class="fas fa-calculator"></i>
                    Total Harga Jual <?php echo $available_sheets[$selected_sheet]; ?>
                </div>
                <div class="footer-amount">
                    Rp <?php echo number_format($filtered_total_harga, 0, ',', '.'); ?>
                </div>
            </div>
            
            <div class="footer-card ppn-card">
                <div class="footer-label">
                    <i class="fas fa-percentage"></i>
                    Total PPN <?php echo $available_sheets[$selected_sheet]; ?>
                </div>
                <div class="footer-amount">
                    Rp <?php echo number_format($filtered_total_ppn, 0, ',', '.'); ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto focus search input
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.focus();
        searchInput.select();
    }
    
    // Add hover animations to cards
    const cards = document.querySelectorAll('.summary-card, .stat-card, .sheet-option');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
    
    // Enhanced table row hover effect
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(8px)';
            this.style.boxShadow = '0 8px 16px rgba(0, 0, 0, 0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
            this.style.boxShadow = 'none';
        });
    });
    
    // Sheet selector with smooth transition
    const sheetOptions = document.querySelectorAll('.sheet-option');
    sheetOptions.forEach(option => {
        option.addEventListener('click', function(e) {
            // Add loading animation
            const icon = this.querySelector('.sheet-icon i');
            const originalClass = icon.className;
            icon.className = 'fas fa-spinner fa-spin';
            
            // Remove active class from all options
            sheetOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Show loading state for the whole page
            document.body.style.opacity = '0.7';
            document.body.style.pointerEvents = 'none';
            
            setTimeout(() => {
                icon.className = originalClass;
                document.body.style.opacity = '1';
                document.body.style.pointerEvents = 'auto';
            }, 500);
        });
    });
    
    // Smooth scroll for pagination
    document.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').includes('page=')) {
                e.preventDefault();
                window.scrollTo({
                    top: document.querySelector('.table-section').offsetTop - 20,
                    behavior: 'smooth'
                });
                setTimeout(() => {
                    window.location.href = this.getAttribute('href');
                }, 400);
            }
        });
    });
    
    // Auto submit form on Enter in search
    document.querySelector('.search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });
    
    // Add loading state to search button
    const searchForm = document.querySelector('form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const searchBtn = this.querySelector('.search-btn[type="submit"]');
            if (searchBtn) {
                searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mencari...';
                searchBtn.disabled = true;
            }
        });
    }
    
    // Add ripple effect to buttons
    const buttons = document.querySelectorAll('.search-btn, .page-link, .sheet-option');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Don't create ripple for anchor tags (sheet options)
            if (this.tagName === 'A' && this.classList.contains('sheet-option')) {
                return;
            }
            
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.7);
                transform: scale(0);
                animation: ripple 0.6s linear;
                width: ${size}px;
                height: ${size}px;
                top: ${y}px;
                left: ${x}px;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Show current sheet info in console (for debugging)
    console.log('Sheet aktif:', '<?php echo $available_sheets[$selected_sheet]; ?>');
});
</script>

</body>
</html>