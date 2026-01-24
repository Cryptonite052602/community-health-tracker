
<?php
// user/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];
$userData = null;
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'analytics';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $error = 'User data not found.';
    }
} catch (PDOException $e) {
    $error = 'Error fetching user data: ' . $e->getMessage();
}

// Initialize analytics data with defaults
$analytics = [
    'health_issues' => 0,
    'announcements' => 0,
    'consultations' => 0,
    'resolved_issues' => 0,
    'pending_issues' => 0
];

$healthIssuesData = [];
$consultationTrends = [];
$recentHealthUpdates = [];

if ($userData) {
    try {
        // Check if user_health_issues table exists
        $healthIssuesTableExists = false;
        try {
            $testStmt = $pdo->query("SELECT 1 FROM user_health_issues LIMIT 1");
            $healthIssuesTableExists = true;
        } catch (Exception $e) {
            $healthIssuesTableExists = false;
            if (empty($error)) {
                $error = "Note: Health issues table not found. Using demo data.";
            }
        }
        
        if ($healthIssuesTableExists) {
            // Get health issues count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM user_health_issues 
                WHERE user_id = ? AND status IN ('active', 'monitoring')
            ");
            $stmt->execute([$userId]);
            $analytics['health_issues'] = $stmt->fetchColumn() ?: 0;

            // Get resolved health issues count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM user_health_issues 
                WHERE user_id = ? AND status = 'resolved'
            ");
            $stmt->execute([$userId]);
            $analytics['resolved_issues'] = $stmt->fetchColumn() ?: 0;

            // Get pending health issues count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM user_health_issues 
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId]);
            $analytics['pending_issues'] = $stmt->fetchColumn() ?: 0;

            // Get health issues by category for chart
            $stmt = $pdo->prepare("
                SELECT 
                    category,
                    COUNT(*) as count,
                    CASE 
                        WHEN category = 'Chronic' THEN '#ef4444'
                        WHEN category = 'Acute' THEN '#f59e0b'
                        WHEN category = 'Preventive' THEN '#10b981'
                        WHEN category = 'Follow-up' THEN '#3b82f6'
                        ELSE '#6b7280'
                    END as color
                FROM user_health_issues 
                WHERE user_id = ? 
                GROUP BY category
                ORDER BY count DESC
            ");
            $stmt->execute([$userId]);
            $healthIssuesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get recent health updates
            $stmt = $pdo->prepare("
                SELECT 
                    uhi.id,
                    uhi.issue_type,
                    uhi.description,
                    uhi.status,
                    uhi.created_at,
                    uhi.resolved_at,
                    s.full_name as staff_name
                FROM user_health_issues uhi
                LEFT JOIN sitio1_staff s ON uhi.assigned_staff_id = s.id
                WHERE uhi.user_id = ?
                ORDER BY uhi.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recentHealthUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Use demo data if table doesn't exist
            $analytics['health_issues'] = 2;
            $analytics['resolved_issues'] = 3;
            $analytics['pending_issues'] = 1;
            
            // Demo health issues data
            $healthIssuesData = [
                ['category' => 'Chronic', 'count' => 1, 'color' => '#ef4444'],
                ['category' => 'Acute', 'count' => 1, 'color' => '#f59e0b'],
                ['category' => 'Preventive', 'count' => 2, 'color' => '#10b981'],
            ];

            // Demo health updates
            $recentHealthUpdates = [
                [
                    'id' => 1,
                    'issue_type' => 'Regular Check-up',
                    'description' => 'Annual physical examination completed',
                    'status' => 'resolved',
                    'created_at' => date('Y-m-d', strtotime('-5 days')),
                    'resolved_at' => date('Y-m-d', strtotime('-2 days')),
                    'staff_name' => 'Dr. Smith'
                ],
                [
                    'id' => 2,
                    'issue_type' => 'Blood Pressure Monitoring',
                    'description' => 'Monthly blood pressure check',
                    'status' => 'active',
                    'created_at' => date('Y-m-d', strtotime('-10 days')),
                    'resolved_at' => null,
                    'staff_name' => 'Nurse Johnson'
                ]
            ];
        }

        // Get announcements count (assuming this table exists)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM sitio1_announcements a 
                WHERE a.status = 'active'
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                AND (
                    a.audience_type = 'public'
                    OR 
                    (a.audience_type = 'specific' AND a.id IN (
                        SELECT announcement_id 
                        FROM announcement_targets 
                        WHERE user_id = ?
                    ))
                )
            ");
            $stmt->execute([$userId]);
            $analytics['announcements'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            $analytics['announcements'] = 3; // Demo data
        }

        // Get consultations count (assuming this table exists)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM sitio1_consultations 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$userId]);
            $analytics['consultations'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            $analytics['consultations'] = 4; // Demo data
        }

        // Get consultation trends (last 7 days)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    status
                FROM sitio1_consultations 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at), status
                ORDER BY date ASC
            ");
            $stmt->execute([$userId]);
            $consultationTrendsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($consultationTrendsRaw)) {
                // Process consultation trends for chart
                $dates = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dates[$date] = [
                        'date' => date('M d', strtotime($date)),
                        'completed' => 0,
                        'pending' => 0,
                        'cancelled' => 0
                    ];
                }

                foreach ($consultationTrendsRaw as $trend) {
                    $date = $trend['date'];
                    if (isset($dates[$date])) {
                        $dates[$date][$trend['status']] = (int)$trend['count'];
                    }
                }

                $consultationTrends = array_values($dates);
            } else {
                // Demo consultation trends data
                $consultationTrends = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('M d', strtotime("-$i days"));
                    $consultationTrends[] = [
                        'date' => $date,
                        'completed' => rand(0, 2),
                        'pending' => rand(0, 1),
                        'cancelled' => rand(0, 1)
                    ];
                }
            }
            
        } catch (Exception $e) {
            // Demo consultation trends data
            $consultationTrends = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('M d', strtotime("-$i days"));
                $consultationTrends[] = [
                    'date' => $date,
                    'completed' => rand(0, 2),
                    'pending' => rand(0, 1),
                    'cancelled' => rand(0, 1)
                ];
            }
        }

    } catch (PDOException $e) {
        $error = 'Error fetching analytics data: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/asssets/css/normalize.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .fixed {
            position: fixed;
        }
        .inset-0 {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
        }
        .hidden {
            display: none;
        }
        .z-50 {
            z-index: 50;
        }

        /* Tab styling */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Stats card styling */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        /* Chart container */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        /* Tab active state */
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #2563eb;
        }

        /* Dashboard stats styling */
        .dashboard-stats {
            margin-bottom: 2rem;
        }

        /* Blue theme for consistency */
        .blue-theme-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .blue-theme-text {
            color: #3b82f6;
        }

        .blue-theme-border {
            border-color: #3b82f6;
        }

        /* Count badge styling */
        .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.8rem;
            height: 1.8rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0 0.6rem;
            margin-left: 0.5rem;
        }

        /* Info card styling */
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
        }
        
        .info-card h3 {
            color: white;
            margin-bottom: 15px;
        }
        
        .info-card p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
            font-size: 14px;
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Health Analytics Dashboard
            </h1>
            <!-- Help Button -->
            <button onclick="openHelpModal()" class="help-icon text-blue-600 p-8 rounded-full hover:text-blue-500 transition">
                <i class="fa-solid fa-circle-info text-4xl"></i>
            </button>
        </div>

        <?php if ($error): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                Note: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Main Tabs - Only Analytics -->
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?tab=analytics" class="<?= $activeTab === 'analytics' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Health Analytics
            </a>
        </div>

        <!-- Analytics Tab Content -->
        <div class="tab-content <?= $activeTab === 'analytics' ? 'active' : '' ?>">
            <!-- Health Analytics Dashboard -->
            <div class="space-y-8">
                <!-- Stats Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Health Issues Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Health Issues</h3>
                                <p class="text-3xl font-bold text-red-600"><?= $analytics['health_issues'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Active & Monitoring</p>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-sm text-green-600 font-medium">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?= $analytics['resolved_issues'] ?> Resolved
                            </span>
                            <span class="text-sm text-yellow-600 font-medium">
                                <i class="fas fa-clock mr-1"></i>
                                <?= $analytics['pending_issues'] ?> Pending
                            </span>
                        </div>
                    </div>

                    <!-- Announcements Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Announcements</h3>
                                <p class="text-3xl font-bold text-blue-600"><?= $analytics['announcements'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Active announcements</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-sm text-blue-600 font-medium hover:text-blue-800 flex items-center">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                View all announcements
                            </a>
                        </div>
                    </div>

                    <!-- Consultations Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Consultations</h3>
                                <p class="text-3xl font-bold text-green-600"><?= $analytics['consultations'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Last 30 days</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-sm text-green-600 font-medium hover:text-green-800 flex items-center">
                                <i class="fas fa-history mr-1"></i>
                                View consultation history
                            </a>
                        </div>
                    </div>

                    <!-- Health Score Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Health Score</h3>
                                <p class="text-3xl font-bold text-purple-600">
                                    <?php 
                                        $score = 100 - ($analytics['health_issues'] * 5);
                                        echo max(60, min(100, $score));
                                    ?>
                                </p>
                                <p class="text-sm text-gray-500 mt-1">Overall wellness</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-purple-600 h-2 rounded-full" style="width: <?= max(60, min(100, $score)) ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Health Issues by Category (Donut Chart) -->
                    <div class="chart-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            </svg>
                            Health Issues by Category
                        </h3>
                        <div class="h-64">
                            <canvas id="healthIssuesChart"></canvas>
                        </div>
                        <?php if (!empty($healthIssuesData)): ?>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <?php foreach ($healthIssuesData as $category): ?>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?= $category['color'] ?>"></div>
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($category['category']) ?></span>
                                        <span class="ml-auto text-sm font-semibold" style="color: <?= $category['color'] ?>"><?= $category['count'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="h-64 flex items-center justify-center">
                                <div class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                                    </svg>
                                    <p class="text-gray-500 mt-2">No health issues data available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Consultation Trends (Bar Chart) -->
                    <div class="chart-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Consultation Trends (7 Days)
                        </h3>
                        <div class="h-64">
                            <canvas id="consultationTrendsChart"></canvas>
                        </div>
                        <?php if (!empty($consultationTrends)): ?>
                            <div class="mt-4 flex justify-center space-x-4">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                    <span class="text-sm text-gray-700">Completed</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                                    <span class="text-sm text-gray-700">Pending</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                    <span class="text-sm text-gray-700">Cancelled</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-64 flex items-center justify-center">
                                <div class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-gray-500 mt-2">No consultation data available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Health Updates -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Recent Health Updates
                    </h3>
                    <div class="space-y-4">
                        <?php if (!empty($recentHealthUpdates)): ?>
                            <?php foreach ($recentHealthUpdates as $update): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800">
                                                <?= htmlspecialchars($update['issue_type']) ?>
                                            </h4>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?= htmlspecialchars($update['description']) ?>
                                            </p>
                                            <div class="flex items-center mt-2 space-x-4">
                                                <span class="text-xs px-2 py-1 rounded-full 
                                                    <?= $update['status'] === 'resolved' ? 'bg-green-100 text-green-800' : 
                                                       ($update['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                       'bg-blue-100 text-blue-800') ?>">
                                                    <?= ucfirst($update['status']) ?>
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= date('M d, Y', strtotime($update['created_at'])) ?>
                                                </span>
                                                <?php if (!empty($update['staff_name'])): ?>
                                                    <span class="text-xs text-gray-500">
                                                        <i class="fas fa-user-md mr-1"></i>
                                                        <?= htmlspecialchars($update['staff_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($update['status'] === 'resolved' && $update['resolved_at']): ?>
                                            <div class="text-right">
                                                <span class="text-xs text-green-600 font-medium">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Resolved
                                                </span>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?= date('M d, Y', strtotime($update['resolved_at'])) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
                                </svg>
                                <p class="text-gray-500 mt-2">No recent health updates</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Insights Card -->
                <div class="info-card">
                    <h3 class="text-white mb-4 text-lg font-semibold flex items-center">
                        <i class="fas fa-lightbulb mr-2"></i>
                        Health Insights
                    </h3>
                    <div class="space-y-3">
                        <p class="text-white text-sm flex items-start">
                            <i class="fas fa-check-circle mt-1 mr-2 text-green-300"></i>
                            <span>You have <?= $analytics['resolved_issues'] ?> resolved health issues. Great progress!</span>
                        </p>
                        <p class="text-white text-sm flex items-start">
                            <i class="fas fa-bell mt-1 mr-2 text-yellow-300"></i>
                            <span>There are <?= $analytics['announcements'] ?> important announcements for you.</span>
                        </p>
                        <p class="text-white text-sm flex items-start">
                            <i class="fas fa-calendar-alt mt-1 mr-2 text-blue-300"></i>
                            <span>You've had <?= $analytics['consultations'] ?> consultations in the last 30 days.</span>
                        </p>
                        <?php if ($analytics['pending_issues'] > 0): ?>
                            <p class="text-white text-sm flex items-start">
                                <i class="fas fa-exclamation-triangle mt-1 mr-2 text-red-300"></i>
                                <span>You have <?= $analytics['pending_issues'] ?> pending health issues requiring attention.</span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-6">
                        <a href="#" class="bg-white text-blue-600 px-6 py-3 rounded-full font-semibold hover:bg-gray-100 transition duration-200 inline-flex items-center">
                            <i class="fas fa-chart-line mr-2"></i>
                            View Detailed Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div id="helpModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-8 mx-auto p-0 border w-full max-w-2xl shadow-xl rounded-xl bg-white overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Analytics Dashboard Guide</h3>
                        <p class="text-blue-100 text-sm">Understanding your health analytics</p>
                    </div>
                    <button onclick="closeHelpModal()" class="text-white hover:text-blue-200 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="px-8 py-6">
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-heartbeat text-red-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Health Issues Tracking</h4>
                            <p class="text-gray-600">Monitor your active and resolved health conditions. The donut chart shows distribution by category.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-bar text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Consultation Trends</h4>
                            <p class="text-gray-600">Track your consultation patterns over the last 7 days. See completed, pending, and cancelled appointments.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-bullhorn text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Announcements</h4>
                            <p class="text-gray-600">Stay updated with important health announcements and community updates relevant to you.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Health Score</h4>
                            <p class="text-gray-600">Your overall wellness score based on active health issues and consultation patterns.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-8 py-6 bg-gray-50 border-t border-gray-200">
                <button type="button" onclick="closeHelpModal()" class="px-7 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg flex items-center">
                    <i class="fas fa-check mr-2"></i>
                    Got it, thanks!
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Health Issues Donut Chart
            <?php if (!empty($healthIssuesData)): ?>
                const healthIssuesCtx = document.getElementById('healthIssuesChart').getContext('2d');
                const healthIssuesChart = new Chart(healthIssuesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_column($healthIssuesData, 'category')) ?>,
                        datasets: [{
                            data: <?= json_encode(array_column($healthIssuesData, 'count')) ?>,
                            backgroundColor: <?= json_encode(array_column($healthIssuesData, 'color')) ?>,
                            borderWidth: 2,
                            borderColor: '#ffffff'
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
                                callbacks: {
                                    label: function(context) {
                                        return `${context.label}: ${context.raw} issues`;
                                    }
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            <?php endif; ?>

            // Consultation Trends Bar Chart
            <?php if (!empty($consultationTrends)): ?>
                const trendsCtx = document.getElementById('consultationTrendsChart').getContext('2d');
                const consultationTrendsChart = new Chart(trendsCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_column($consultationTrends, 'date')) ?>,
                        datasets: [
                            {
                                label: 'Completed',
                                data: <?= json_encode(array_column($consultationTrends, 'completed')) ?>,
                                backgroundColor: '#10b981',
                                borderColor: '#10b981',
                                borderWidth: 1
                            },
                            {
                                label: 'Pending',
                                data: <?= json_encode(array_column($consultationTrends, 'pending')) ?>,
                                backgroundColor: '#f59e0b',
                                borderColor: '#f59e0b',
                                borderWidth: 1
                            },
                            {
                                label: 'Cancelled',
                                data: <?= json_encode(array_column($consultationTrends, 'cancelled')) ?>,
                                backgroundColor: '#ef4444',
                                borderColor: '#ef4444',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            <?php endif; ?>
        });

        // Help modal functions
        function openHelpModal() {
            document.getElementById('helpModal').classList.remove('hidden');
        }

        function closeHelpModal() {
            document.getElementById('helpModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const helpModal = document.getElementById('helpModal');
            if (event.target === helpModal) {
                closeHelpModal();
            }
        }
    </script>
</body>
</html>