<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get stats for dashboard - USING THE SAME QUERIES AS YOUR ACCOUNT MANAGEMENT
$stats = [
    'total_active_staff' => 0,
    'total_inactive_staff' => 0,
    'total_approved_residents' => 0,
    'total_pending_residents' => 0,
    'total_declined_residents' => 0,
    'total_unlinked_residents' => 0,
    'total_patients' => 0,
    'total_unlinked_patients' => 0,
    'linked_accounts_count' => 0
];

try {
    // ACTIVE STAFF - Using the same query as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff WHERE is_active = 1");
    $stats['total_active_staff'] = $stmt->fetchColumn();
    
    // INACTIVE STAFF - Using the same query as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff WHERE is_active = 0");
    $stats['total_inactive_staff'] = $stmt->fetchColumn();
    
    // APPROVED RESIDENTS - Using the same query as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE role = 'patient' AND status = 'approved'");
    $stats['total_approved_residents'] = $stmt->fetchColumn();
    
    // PENDING RESIDENTS - Using the same query as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE role = 'patient' AND status = 'pending'");
    $stats['total_pending_residents'] = $stmt->fetchColumn();
    
    // DECLINED RESIDENTS - Using the same query as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE role = 'patient' AND status = 'declined'");
    $stats['total_declined_residents'] = $stmt->fetchColumn();
    
    // UNLINKED RESIDENTS - Using the SAME FUNCTION as in manage_accounts.php
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM sitio1_users u
        LEFT JOIN sitio1_patients p ON u.id = p.user_id
        WHERE u.role = 'patient' 
        AND u.status = 'approved'
        AND p.id IS NULL
    ");
    $stmt->execute();
    $stats['total_unlinked_residents'] = $stmt->fetchColumn();
    
    // TOTAL PATIENTS - All patient records
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_patients WHERE deleted_at IS NULL");
    $stats['total_patients'] = $stmt->fetchColumn();
    
    // UNLINKED PATIENTS - Using the SAME FUNCTION as in manage_accounts.php
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_patients WHERE user_id IS NULL AND deleted_at IS NULL");
    $stats['total_unlinked_patients'] = $stmt->fetchColumn();
    
    // LINKED ACCOUNTS COUNT - Residents with patient records
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) 
        FROM sitio1_users u
        INNER JOIN sitio1_patients p ON u.id = p.user_id
        WHERE u.role = 'patient' 
        AND u.status = 'approved'
        AND p.deleted_at IS NULL
    ");
    $stmt->execute();
    $stats['linked_accounts_count'] = $stmt->fetchColumn();
    
    // Calculate percentages
    $total_accounts = $stats['total_active_staff'] + $stats['total_inactive_staff'] + 
                      $stats['total_approved_residents'] + $stats['total_pending_residents'] + 
                      $stats['total_declined_residents'];
    
    if ($total_accounts > 0) {
        $active_staff_percentage = round(($stats['total_active_staff'] / $total_accounts) * 100, 1);
        $approved_residents_percentage = round(($stats['total_approved_residents'] / $total_accounts) * 100, 1);
        $pending_residents_percentage = round(($stats['total_pending_residents'] / $total_accounts) * 100, 1);
    } else {
        $active_staff_percentage = $approved_residents_percentage = $pending_residents_percentage = 0;
    }
    
    // Calculate percentages for linking status
    $total_residents = $stats['total_approved_residents'] + $stats['total_pending_residents'] + $stats['total_declined_residents'];
    if ($total_residents > 0) {
        $linked_percentage = round(($stats['linked_accounts_count'] / $total_residents) * 100, 1);
        $unlinked_percentage = round(($stats['total_unlinked_residents'] / $total_residents) * 100, 1);
    } else {
        $linked_percentage = $unlinked_percentage = 0;
    }
    
} catch (PDOException $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch dashboard statistics. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Community Health Tracker</title>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif !important;
        }

        /* Ensure Font Awesome icons are visible */
        .fas, .far, .fab {
            font-family: 'Font Awesome 6 Free' !important;
            font-weight: 900 !important;
            display: inline-block !important;
        }

        .far {
            font-weight: 400 !important;
        }

        /* Stats card styling */
        .stats-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        }

        /* Enhanced icon containers */
        .icon-container {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .icon-container i {
            font-size: 28px !important;
            display: block !important;
        }

        /* Progress bar */
        .progress-bar {
            height: 10px;
            border-radius: 5px;
            background-color: #e5e7eb;
            overflow: hidden;
            margin-top: 12px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        /* Chart containers */
        .chart-container {
            height: 340px;
            position: relative;
        }

        /* Improved badge styling */
        .percentage-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Quick stats items */
        .quick-stat-item {
            padding: 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .quick-stat-item:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .quick-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .quick-stat-icon i {
            font-size: 20px !important;
            display: block !important;
        }

        /* Quick action cards */
        .quick-action-card {
            padding: 20px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            background: white;
            text-decoration: none !important;
            color: inherit;
            display: block;
        }

        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }

        .quick-action-icon i {
            font-size: 24px !important;
            display: block !important;
        }

        /* Chart headers */
        .chart-header {
            display: flex;
            align-items: center;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 20px;
        }

        .chart-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .chart-header-icon i {
            color: white !important;
            font-size: 20px !important;
            display: block !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div class="flex items-center">
                <div class="icon-container bg-gradient-to-br from-blue-500 to-blue-700 mr-4">
                    <i class="fas fa-chart-network text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
                    <p class="text-gray-600 mt-1">Overview of system statistics and activities</p>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3 text-red-500 text-lg"></i>
                    <span><?= $_SESSION['error_message'] ?></span>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Main Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Active Staff Card -->
            <div class="stats-card">
                <div class="flex items-center mb-6">
                    <div class="icon-container bg-gradient-to-br from-blue-500 to-blue-700">
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-700 mb-1">Active Staff</h3>
                        <p class="text-gray-500 text-sm">Currently active healthcare staff</p>
                    </div>
                </div>
                <div class="flex items-end justify-between mb-4">
                    <p class="text-3xl font-bold text-blue-600"><?= $stats['total_active_staff'] ?></p>
                    <span class="percentage-badge bg-blue-100 text-blue-800">
                        <?= isset($active_staff_percentage) ? $active_staff_percentage : 0 ?>%
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill bg-gradient-to-r from-blue-500 to-blue-700" style="width: <?= isset($active_staff_percentage) ? $active_staff_percentage : 0 ?>%"></div>
                </div>
                <a href="manage_accounts.php?section=staff" class="inline-flex items-center mt-4 text-blue-600 text-sm font-medium hover:underline">
                    <i class="fas fa-external-link-alt mr-2 text-sm"></i> Manage staff
                </a>
            </div>
            
            <!-- Approved Residents Card -->
            <div class="stats-card">
                <div class="flex items-center mb-6">
                    <div class="icon-container bg-gradient-to-br from-green-500 to-green-700">
                        <i class="fas fa-user-circle text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-700 mb-1">Approved Residents</h3>
                        <p class="text-gray-500 text-sm">Verified resident accounts</p>
                    </div>
                </div>
                <div class="flex items-end justify-between mb-4">
                    <p class="text-3xl font-bold text-green-600"><?= $stats['total_approved_residents'] ?></p>
                    <span class="percentage-badge bg-green-100 text-green-800">
                        <?= isset($approved_residents_percentage) ? $approved_residents_percentage : 0 ?>%
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill bg-gradient-to-r from-green-500 to-green-700" style="width: <?= isset($approved_residents_percentage) ? $approved_residents_percentage : 0 ?>%"></div>
                </div>
                <a href="manage_accounts.php?section=resident" class="inline-flex items-center mt-4 text-green-600 text-sm font-medium hover:underline">
                    <i class="fas fa-external-link-alt mr-2 text-sm"></i> View residents
                </a>
            </div>
            
            <!-- Linked Accounts Card -->
            <div class="stats-card">
                <div class="flex items-center mb-6">
                    <div class="icon-container bg-gradient-to-br from-purple-500 to-purple-700">
                        <i class="fas fa-handshake text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-700 mb-1">Linked Accounts</h3>
                        <p class="text-gray-500 text-sm">Accounts with patient records</p>
                    </div>
                </div>
                <div class="flex items-end justify-between mb-4">
                    <p class="text-3xl font-bold text-purple-600"><?= $stats['linked_accounts_count'] ?></p>
                    <span class="percentage-badge bg-purple-100 text-purple-800">
                        <?= isset($linked_percentage) ? $linked_percentage : 0 ?>%
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill bg-gradient-to-r from-purple-500 to-purple-700" style="width: <?= isset($linked_percentage) ? $linked_percentage : 0 ?>%"></div>
                </div>
                <a href="manage_accounts.php?section=linking" class="inline-flex items-center mt-4 text-purple-600 text-sm font-medium hover:underline">
                    <i class="fas fa-external-link-alt mr-2 text-sm"></i> Manage linking
                </a>
            </div>
            
            <!-- Pending Residents Card -->
            <div class="stats-card">
                <div class="flex items-center mb-6">
                    <div class="icon-container bg-gradient-to-br from-yellow-500 to-yellow-700">
                        <i class="fas fa-hourglass-half text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-700 mb-1">Pending Residents</h3>
                        <p class="text-gray-500 text-sm">Awaiting approval</p>
                    </div>
                </div>
                <div class="flex items-end justify-between mb-4">
                    <p class="text-3xl font-bold text-yellow-600"><?= $stats['total_pending_residents'] ?></p>
                    <div class="text-right">
                        <div class="text-sm text-gray-500 mb-1">Approval needed</div>
                        <div class="relative w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <?php
                            $max_pending = max($stats['total_pending_residents'], 10);
                            $pending_width = min(($stats['total_pending_residents'] / $max_pending) * 100, 100);
                            ?>
                            <div class="absolute h-full bg-yellow-500 rounded-full" style="width: <?= $pending_width ?>%"></div>
                        </div>
                    </div>
                </div>
                <a href="manage_accounts.php?section=resident" class="inline-flex items-center mt-4 text-yellow-600 text-sm font-medium hover:underline">
                    <i class="fas fa-external-link-alt mr-2 text-sm"></i> Review approvals
                </a>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Bar Chart: Account Status Distribution -->
            <div class="stats-card">
                <div class="chart-header">
                    <div class="chart-header-icon">
                        <i class="fas fa-chart-gantt"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Account Status Distribution</h2>
                        <p class="text-gray-600 text-sm">Overview of different account types</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="accountStatusChart"></canvas>
                </div>
            </div>
            
            <!-- Donut Chart: Linking Status -->
            <div class="stats-card">
                <div class="chart-header">
                    <div class="chart-header-icon">
                        <i class="fas fa-chart-area"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Account Linking Status</h2>
                        <p class="text-gray-600 text-sm">Total: <?= $stats['total_approved_residents'] ?> residents</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="linkingStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Additional Stats and Patient Records -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Quick Stats -->
            <div class="stats-card">
                <div class="chart-header">
                    <div class="chart-header-icon">
                        <i class="fas fa-table-list"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">System Statistics</h2>
                        <p class="text-gray-600 text-sm">Additional metrics</p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <!-- Inactive Staff -->
                    <div class="quick-stat-item">
                        <div class="flex items-center">
                            <div class="quick-stat-icon bg-gray-100">
                                <i class="fas fa-user-xmark text-gray-600"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-gray-700 font-medium">Inactive Staff</span>
                            </div>
                            <span class="text-xl font-bold text-gray-800"><?= $stats['total_inactive_staff'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Unlinked Accounts -->
                    <div class="quick-stat-item">
                        <div class="flex items-center">
                            <div class="quick-stat-icon bg-amber-100">
                                <i class="fas fa-chain-broken text-amber-600"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-gray-700 font-medium">Unlinked Accounts</span>
                            </div>
                            <span class="text-xl font-bold text-amber-600"><?= $stats['total_unlinked_residents'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Unlinked Patients -->
                    <div class="quick-stat-item">
                        <div class="flex items-center">
                            <div class="quick-stat-icon bg-orange-100">
                                <i class="fas fa-file-circle-xmark text-orange-600"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-gray-700 font-medium">Unlinked Patients</span>
                            </div>
                            <span class="text-xl font-bold text-orange-600"><?= $stats['total_unlinked_patients'] ?></span>
                        </div>
                    </div>
                    
                    <!-- Declined Residents -->
                    <div class="quick-stat-item">
                        <div class="flex items-center">
                            <div class="quick-stat-icon bg-red-100">
                                <i class="fas fa-user-ban text-red-600"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-gray-700 font-medium">Declined Residents</span>
                            </div>
                            <span class="text-xl font-bold text-red-600"><?= $stats['total_declined_residents'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Patient Records Overview -->
            <div class="stats-card lg:col-span-2">
                <div class="chart-header">
                    <div class="chart-header-icon">
                        <i class="fas fa-folder-tree"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Patient Records Overview</h2>
                        <p class="text-gray-600 text-sm">Patient data and linking progress</p>
                    </div>
                </div>
                
                <!-- Patient Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Total Patients -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6 border border-blue-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center">
                                <i class="fas fa-notes-medical text-white text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-blue-800"><?= $stats['total_patients'] ?></div>
                                <div class="text-blue-600 text-sm font-medium">Total</div>
                            </div>
                        </div>
                        <h3 class="font-semibold text-gray-700 mb-1">Patient Records</h3>
                        <p class="text-gray-600 text-sm">All records in system</p>
                    </div>
                    
                    <!-- Linked Patients -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6 border border-green-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-600 flex items-center justify-center">
                                <i class="fas fa-diagram-project text-white text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-green-800"><?= $stats['linked_accounts_count'] ?></div>
                                <div class="text-green-600 text-sm font-medium">Connected</div>
                            </div>
                        </div>
                        <h3 class="font-semibold text-gray-700 mb-1">Linked Patients</h3>
                        <p class="text-gray-600 text-sm">With resident accounts</p>
                    </div>
                    
                    <!-- Unlinked Patients -->
                    <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl p-6 border border-yellow-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-yellow-600 flex items-center justify-center">
                                <i class="fas fa-file-circle-question text-white text-xl"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold text-yellow-800"><?= $stats['total_unlinked_patients'] ?></div>
                                <div class="text-yellow-600 text-sm font-medium">To Link</div>
                            </div>
                        </div>
                        <h3 class="font-semibold text-gray-700 mb-1">Unlinked Patients</h3>
                        <p class="text-gray-600 text-sm">Need account linking</p>
                    </div>
                </div>
                
                <!-- Patient Records Linking Progress -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-700 text-lg">Linking Progress</h4>
                        <?php
                        $linked_patients_percentage = $stats['total_patients'] > 0 ? 
                            round(($stats['linked_accounts_count'] / $stats['total_patients']) * 100, 1) : 0;
                        ?>
                        <span class="text-2xl font-bold text-green-600"><?= $linked_patients_percentage ?>%</span>
                    </div>
                    <div class="bg-gray-200 rounded-full h-4 overflow-hidden mb-3">
                        <div class="bg-gradient-to-r from-green-500 to-green-700 h-full" style="width: <?= $linked_patients_percentage ?>%"></div>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                            <span><?= $stats['linked_accounts_count'] ?> linked</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                            <span><?= $stats['total_unlinked_patients'] ?> to link</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Section -->
        <div class="stats-card">
            <div class="chart-header">
                <div class="chart-header-icon">
                    <i class="fas fa-rocket"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Quick Actions</h2>
                    <p class="text-gray-600 text-sm">Common administrative tasks</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <!-- Add Staff Action -->
                <a href="manage_accounts.php?section=staff" class="quick-action-card">
                    <div class="flex items-center mb-4">
                        <div class="quick-action-icon bg-gradient-to-br from-blue-500 to-blue-700">
                            <i class="fas fa-user-gear text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 text-lg">Add Staff</h3>
                            <p class="text-gray-500 text-sm">Create new staff account</p>
                        </div>
                    </div>
                    <div class="flex items-center text-blue-600 font-medium">
                        <span>Go to Staff Management</span>
                        <i class="fas fa-chevron-right ml-2"></i>
                    </div>
                </a>
                
                <!-- Add Resident Action -->
                <a href="manage_accounts.php?section=resident" class="quick-action-card">
                    <div class="flex items-center mb-4">
                        <div class="quick-action-icon bg-gradient-to-br from-green-500 to-green-700">
                            <i class="fas fa-user-pen text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 text-lg">Add Resident</h3>
                            <p class="text-gray-500 text-sm">Create resident account</p>
                        </div>
                    </div>
                    <div class="flex items-center text-green-600 font-medium">
                        <span>Go to Resident Management</span>
                        <i class="fas fa-chevron-right ml-2"></i>
                    </div>
                </a>
                
                <!-- Link Accounts Action -->
                <a href="manage_accounts.php?section=linking" class="quick-action-card">
                    <div class="flex items-center mb-4">
                        <div class="quick-action-icon bg-gradient-to-br from-purple-500 to-purple-700">
                            <i class="fas fa-people-arrows text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 text-lg">Link Accounts</h3>
                            <p class="text-gray-500 text-sm">Link residents to patients</p>
                        </div>
                    </div>
                    <div class="flex items-center text-purple-600 font-medium">
                        <span>Go to Manual Linking</span>
                        <i class="fas fa-chevron-right ml-2"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const accountStatusLabels = ['Active Staff', 'Approved Residents', 'Pending Residents'];
        const accountStatusData = [
            <?= $stats['total_active_staff'] ?>,
            <?= $stats['total_approved_residents'] ?>,
            <?= $stats['total_pending_residents'] ?>
        ];
        
        const linkingStatusLabels = ['Linked Accounts', 'Unlinked Accounts'];
        const linkingStatusData = [
            <?= $stats['linked_accounts_count'] ?>,
            <?= $stats['total_unlinked_residents'] ?>
        ];
        
        // Bar Chart: Account Status Distribution
        const accountStatusCtx = document.getElementById('accountStatusChart').getContext('2d');
        const accountStatusChart = new Chart(accountStatusCtx, {
            type: 'bar',
            data: {
                labels: accountStatusLabels,
                datasets: [{
                    label: 'Number of Accounts',
                    data: accountStatusData,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0,
                            font: {
                                size: 12
                            }
                        },
                        title: {
                            display: true,
                            text: 'Number of Accounts',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
        
        // Donut Chart: Linking Status
        const linkingStatusCtx = document.getElementById('linkingStatusChart').getContext('2d');
        const linkingStatusChart = new Chart(linkingStatusCtx, {
            type: 'doughnut',
            data: {
                labels: linkingStatusLabels,
                datasets: [{
                    data: linkingStatusData,
                    backgroundColor: [
                        'rgba(139, 92, 246, 0.9)',
                        'rgba(251, 191, 36, 0.9)'
                    ],
                    borderColor: [
                        'rgb(139, 92, 246)',
                        'rgb(251, 191, 36)'
                    ],
                    borderWidth: 3,
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 13,
                                family: "'Poppins', sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#8b5cf6',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} accounts (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
        
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
    
</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>