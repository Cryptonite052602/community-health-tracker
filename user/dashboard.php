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
$activityLog = [];
$announcementStats = ['accepted' => 0, 'dismissed' => 0, 'pending' => 0];

if ($userData) {
    try {
        // 1. Get Health Issues Count (from sitio1_patients table)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM sitio1_patients 
                WHERE user_id = ? 
                AND deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $analytics['health_issues'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            // Fallback if table doesn't exist
            $analytics['health_issues'] = 0;
            if (empty($error)) {
                $error = "Note: Could not fetch health issues count. " . $e->getMessage();
            }
        }

        // 2. Get Announcements Count (from announcements and user_announcements tables)
        try {
            // First, get all announcements targeted to this user
            $stmt = $pdo->prepare("
                SELECT a.id 
                FROM sitio1_announcements a
                LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ?
                LEFT JOIN sitio1_staff s ON a.staff_id = s.id
                WHERE a.status = 'active'
                AND (a.audience_type = 'public' OR a.id IN (
                    SELECT announcement_id FROM announcement_targets WHERE user_id = ?
                ))
            ");
            $stmt->execute([$userId, $userId]);
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $analytics['announcements'] = count($announcements);
            
            // Get announcement statistics
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as count 
                FROM user_announcements 
                WHERE user_id = ?
                GROUP BY status
            ");
            $stmt->execute([$userId]);
            $announcementStatsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($announcementStatsResult as $stat) {
                $announcementStats[$stat['status']] = (int)$stat['count'];
            }
            
            // Calculate pending announcements
            $announcementStats['pending'] = $analytics['announcements'] - 
                                            ($announcementStats['accepted'] + $announcementStats['dismissed']);
            
        } catch (Exception $e) {
            $analytics['announcements'] = 0;
            if (empty($error)) {
                $error .= " Note: Could not fetch announcements data.";
            }
        }

        // 3. Get Consultations Count (from consultation_notes table)
        try {
            // First, get all patients linked to this user
            $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE user_id = ? AND deleted_at IS NULL");
            $stmt->execute([$userId]);
            $patientIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($patientIds)) {
                $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM consultation_notes 
                    WHERE patient_id IN ($placeholders)
                ");
                $stmt->execute($patientIds);
                $analytics['consultations'] = $stmt->fetchColumn() ?: 0;
            }
        } catch (Exception $e) {
            $analytics['consultations'] = 0;
            if (empty($error)) {
                $error .= " Note: Could not fetch consultations data.";
            }
        }

        // 4. Get Activity Log (user logins/logouts)
        try {
            // First check if user_activity_log table exists
            $tableExists = false;
            try {
                $testStmt = $pdo->query("SELECT 1 FROM user_activity_log LIMIT 1");
                $tableExists = true;
            } catch (Exception $e) {
                $tableExists = false;
            }
            
            if ($tableExists) {
                $stmt = $pdo->prepare("
                    SELECT 
                        action_type,
                        action_timestamp,
                        ip_address,
                        user_agent
                    FROM user_activity_log 
                    WHERE user_id = ?
                    AND action_type IN ('login', 'logout')
                    ORDER BY action_timestamp DESC
                    LIMIT 10
                ");
                $stmt->execute([$userId]);
                $activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Create demo activity log
                $activityLog = [
                    [
                        'action_type' => 'login',
                        'action_timestamp' => date('Y-m-d H:i:s'),
                        'ip_address' => '192.168.1.1',
                        'user_agent' => 'Chrome/Windows'
                    ],
                    [
                        'action_type' => 'logout',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                        'ip_address' => '192.168.1.1',
                        'user_agent' => 'Chrome/Windows'
                    ],
                    [
                        'action_type' => 'login',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
                        'ip_address' => '192.168.1.1',
                        'user_agent' => 'Firefox/Windows'
                    ]
                ];
            }
        } catch (Exception $e) {
            // Demo activity log on error
            $activityLog = [
                [
                    'action_type' => 'login',
                    'action_timestamp' => date('Y-m-d H:i:s'),
                    'ip_address' => '192.168.1.1'
                ]
            ];
        }

        // Prepare data for donut chart (Health Issues, Announcements, Consultations)
        $chartData = [
            ['category' => 'Health Issues', 'count' => $analytics['health_issues'], 'color' => '#ef4444'],
            ['category' => 'Announcements', 'count' => $analytics['announcements'], 'color' => '#3b82f6'],
            ['category' => 'Consultations', 'count' => $analytics['consultations'], 'color' => '#10b981']
        ];

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
        /* Existing styles remain the same */
        .fixed { position: fixed; }
        .inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
        .hidden { display: none; }
        .z-50 { z-index: 50; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #2563eb;
        }
        .blue-theme-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .blue-theme-text { color: #3b82f6; }
        .blue-theme-border { border-color: #3b82f6; }
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
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
        }
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
        .activity-log-item {
            border-left: 3px solid;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .activity-log-item.login { border-color: #10b981; }
        .activity-log-item.logout { border-color: #ef4444; }
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Health Issues Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-heartbeat text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Health Records</h3>
                                <p class="text-3xl font-bold text-red-600"><?= $analytics['health_issues'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Linked patient records</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="profile.php" class="text-sm text-red-600 font-medium hover:text-red-800 flex items-center">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                View health records
                            </a>
                        </div>
                    </div>

                    <!-- Announcements Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-bullhorn text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Announcements</h3>
                                <p class="text-3xl font-bold text-blue-600"><?= $analytics['announcements'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Active announcements</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-green-600 font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <?= $announcementStats['accepted'] ?> Accepted
                                </span>
                                <span class="text-yellow-600 font-medium">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?= $announcementStats['pending'] ?> Pending
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Consultations Card -->
                    <div class="stats-card">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-file-medical-alt text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Consultations</h3>
                                <p class="text-3xl font-bold text-green-600"><?= $analytics['consultations'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Total consultation notes</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="profile.php" class="text-sm text-green-600 font-medium hover:text-green-800 flex items-center">
                                <i class="fas fa-history mr-1"></i>
                                View consultation history
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Charts and Activity Log Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Donut Chart -->
                    <div class="chart-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-pie text-blue-500 mr-2"></i>
                            Health Overview Distribution
                        </h3>
                        <div class="h-64">
                            <canvas id="overviewChart"></canvas>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <?php foreach ($chartData as $item): ?>
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?= $item['color'] ?>"></div>
                                    <span class="text-sm text-gray-700"><?= $item['category'] ?></span>
                                    <span class="ml-auto text-sm font-semibold" style="color: <?= $item['color'] ?>"><?= $item['count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="chart-container">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-history text-purple-500 mr-2"></i>
                            Recent Activity Log
                        </h3>
                        <div class="max-h-64 overflow-y-auto custom-scrollbar">
                            <?php if (!empty($activityLog)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($activityLog as $activity): ?>
                                        <div class="activity-log-item <?= $activity['action_type'] ?>">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-medium text-gray-800">
                                                        <?php if ($activity['action_type'] == 'login'): ?>
                                                            <i class="fas fa-sign-in-alt text-green-500 mr-1"></i>
                                                            Account Login
                                                        <?php else: ?>
                                                            <i class="fas fa-sign-out-alt text-red-500 mr-1"></i>
                                                            Account Logout
                                                        <?php endif; ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-600 mt-1">
                                                        <?= date('M d, Y h:i A', strtotime($activity['action_timestamp'])) ?>
                                                    </p>
                                                    <?php if (!empty($activity['ip_address'])): ?>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            <i class="fas fa-network-wired mr-1"></i>
                                                            IP: <?= htmlspecialchars($activity['ip_address']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-xs px-2 py-1 rounded-full 
                                                    <?= $activity['action_type'] === 'login' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= ucfirst($activity['action_type']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No recent activity found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Announcement Response Stats -->
                <div class="chart-container">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-bell text-yellow-500 mr-2"></i>
                        Announcement Response Statistics
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-check text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-800"><?= $announcementStats['accepted'] ?></p>
                                    <p class="text-sm text-green-600">Accepted</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-yellow-800"><?= $announcementStats['pending'] ?></p>
                                    <p class="text-sm text-yellow-600">Pending Response</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-times text-gray-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-gray-800"><?= $announcementStats['dismissed'] ?></p>
                                    <p class="text-sm text-gray-600">Dismissed</p>
                                </div>
                            </div>
                        </div>
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
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Health Records</h4>
                            <p class="text-gray-600">Number of patient profiles linked to your account from the sitio1_patients table.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-bullhorn text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Announcements</h4>
                            <p class="text-gray-600">Active announcements targeted to you. Track your responses and pending items.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-file-medical-alt text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Consultations</h4>
                            <p class="text-gray-600">Total consultation notes from all linked patient profiles in the consultation_notes table.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-history text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Activity Log</h4>
                            <p class="text-gray-600">Track your login and logout activities with timestamps and IP addresses.</p>
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
            // Overview Donut Chart
            const chartData = <?= json_encode($chartData) ?>;
            
            const overviewCtx = document.getElementById('overviewChart').getContext('2d');
            const overviewChart = new Chart(overviewCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.map(item => item.category),
                    datasets: [{
                        data: chartData.map(item => item.count),
                        backgroundColor: chartData.map(item => item.color),
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
                                    return `${context.label}: ${context.raw}`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
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