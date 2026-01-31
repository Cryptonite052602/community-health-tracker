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

// Initialize chartData with defaults BEFORE the if block
$chartData = [
    ['category' => 'Health Records', 'count' => 0, 'color' => '#ef4444', 'icon' => 'fas fa-heartbeat'],
    ['category' => 'Announcements', 'count' => 0, 'color' => '#3b82f6', 'icon' => 'fas fa-bullhorn'],
    ['category' => 'Consultations', 'count' => 0, 'color' => '#10b981', 'icon' => 'fas fa-file-medical-alt']
];

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
                $announcementStats[$stat['status']] = (int) $stat['count'];
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
                        'user_agent' => 'Chrome/Windows',
                        'browser' => 'Chrome',
                        'device' => 'Desktop'
                    ],
                    [
                        'action_type' => 'logout',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                        'ip_address' => '192.168.1.1',
                        'user_agent' => 'Chrome/Windows',
                        'browser' => 'Chrome',
                        'device' => 'Desktop'
                    ],
                    [
                        'action_type' => 'login',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
                        'ip_address' => '192.168.1.1',
                        'user_agent' => 'Firefox/Windows',
                        'browser' => 'Firefox',
                        'device' => 'Desktop'
                    ],
                    [
                        'action_type' => 'login',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-3 days')),
                        'ip_address' => '192.168.1.100',
                        'user_agent' => 'Mobile Safari',
                        'browser' => 'Safari',
                        'device' => 'Mobile'
                    ],
                    [
                        'action_type' => 'logout',
                        'action_timestamp' => date('Y-m-d H:i:s', strtotime('-4 days')),
                        'ip_address' => '192.168.1.100',
                        'user_agent' => 'Mobile Safari',
                        'browser' => 'Safari',
                        'device' => 'Mobile'
                    ]
                ];
            }

            // Process activity log for better display
            foreach ($activityLog as &$activity) {
                if (!isset($activity['browser'])) {
                    // Parse user agent to get browser info
                    $ua = $activity['user_agent'] ?? '';
                    if (stripos($ua, 'chrome') !== false) {
                        $activity['browser'] = 'Chrome';
                    } elseif (stripos($ua, 'firefox') !== false) {
                        $activity['browser'] = 'Firefox';
                    } elseif (stripos($ua, 'safari') !== false) {
                        $activity['browser'] = 'Safari';
                    } else {
                        $activity['browser'] = 'Browser';
                    }

                    if (stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false || stripos($ua, 'iphone') !== false) {
                        $activity['device'] = 'Mobile';
                    } else {
                        $activity['device'] = 'Desktop';
                    }
                }

                // Format time
                $activity['formatted_time'] = date('h:i A', strtotime($activity['action_timestamp']));
                $activity['formatted_date'] = date('M d, Y', strtotime($activity['action_timestamp']));
                $activity['time_ago'] = getTimeAgo($activity['action_timestamp']);
            }

        } catch (Exception $e) {
            // Demo activity log on error
            $activityLog = [
                [
                    'action_type' => 'login',
                    'action_timestamp' => date('Y-m-d H:i:s'),
                    'ip_address' => '192.168.1.1',
                    'browser' => 'Chrome',
                    'device' => 'Desktop',
                    'formatted_time' => date('h:i A'),
                    'formatted_date' => date('M d, Y'),
                    'time_ago' => 'Just now'
                ]
            ];
        }

        // Update chartData with actual values
        $chartData = [
            ['category' => 'Health Records', 'count' => $analytics['health_issues'], 'color' => '#ef4444', 'icon' => 'fas fa-heartbeat'],
            ['category' => 'Announcements', 'count' => $analytics['announcements'], 'color' => '#3b82f6', 'icon' => 'fas fa-bullhorn'],
            ['category' => 'Consultations', 'count' => $analytics['consultations'], 'color' => '#10b981', 'icon' => 'fas fa-file-medical-alt']
        ];

    } catch (PDOException $e) {
        $error = 'Error fetching analytics data: ' . $e->getMessage();
    }
}

// Helper function to calculate time ago
function getTimeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Existing styles remain the same */
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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        /* .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        } */

        .chart-container,
        .chart-container-two {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            width: 100%;
            /* ✅ always fluid */
        }

        /* Optional: tighter padding on small screens */
        @media (max-width: 640px) {

            .chart-container,
            .chart-container-two {
                padding: 16px;
            }
        }


        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #2563eb;
        }

        .blue-theme-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .blue-theme-text {
            color: #3b82f6;
        }

        .blue-theme-border {
            border-color: #3b82f6;
        }

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

        /* Activity Log Styles */
        .activity-log-container {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-log-item {
            display: flex;
            align-items: flex-start;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            background: #f8fafc;
            border-left: 4px solid;
            transition: all 0.2s ease;
        }

        .activity-log-item:hover {
            background: #f1f5f9;
            transform: translateX(2px);
        }

        .activity-log-item.login {
            border-left-color: #10b981;
        }

        .activity-log-item.logout {
            border-left-color: #ef4444;
        }

        .activity-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .activity-icon.login {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }

        .activity-icon.logout {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        }

        .activity-content {
            flex: 1;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }

        .activity-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }

        .activity-time {
            font-size: 12px;
            color: #6b7280;
            background: #f3f4f6;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .activity-details {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
        }

        .activity-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        .activity-detail i {
            font-size: 11px;
        }

        /* Bold Icons */
        .bold-icon {
            font-weight: 900 !important;
        }

        /* Chart legend items */
        .chart-legend-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f9fafb;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .chart-legend-item:hover {
            background: #f3f4f6;
        }

        .chart-legend-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            margin-right: 12px;
        }

        /* Stats card icons */
        .stats-icon-container {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class=" px-4 py-6 -mt-24">
        <!-- Dashboard Header -->
        <!-- <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold flex items-center">
                <div class="stats-icon-container bg-blue-100 mr-3">
                    <i class="fas fa-chart-pie text-blue-600 text-xl bold-icon"></i>
                </div>
                Health Analytics Dashboard
            </h1>
           
            <button onclick="openHelpModal()" class="help-icon text-blue-600 hover:text-blue-500 transition">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-circle-question text-2xl bold-icon"></i>
                </div>
            </button>
        </div> -->

        <?php if ($error): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 flex items-center">
                <div class="w-8 h-8 bg-yellow-200 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-exclamation-circle text-yellow-600 bold-icon"></i>
                </div>
                <span>Note: <?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                <div class="w-8 h-8 bg-green-200 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check-circle text-green-600 bold-icon"></i>
                </div>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Tabs - Only Analytics -->
        <!-- <div class="flex border-b border-gray-200 mb-6">
            <a href="?tab=analytics" class="<?= $activeTab === 'analytics' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <i class="fas fa-chart-pie text-lg mr-2 bold-icon"></i>
                Health Analytics
            </a>
        </div> -->

        <!-- Analytics Tab Content -->
        <div class="tab-content <?= $activeTab === 'analytics' ? 'active' : '' ?>">
            <!-- Health Analytics Dashboard -->
            <div class="space-y-8">
                <!-- Stats Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Consultations Card -->
                    <div class="stats-card">
                        <div class="flex items-center mt-4">
                            <div class="stats-icon-container bg-green-100">
                                <svg width="66" height="66" viewBox="0 0 66 66" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0 4C0 1.79086 1.79086 0 4 0H62C64.2091 0 66 1.79086 66 4V62C66 64.2091 64.2091 66 62 66H4C1.79086 66 0 64.2091 0 62V4Z"
                                        fill-opacity="0.3" />
                                    <path
                                        d="M36.8105 25.2857C37.8182 25.2857 38.8032 24.9841 39.641 24.419C40.4788 23.8539 41.1318 23.0507 41.5174 22.1109C41.9031 21.1712 42.004 20.1372 41.8074 19.1395C41.6108 18.1419 41.1256 17.2256 40.413 16.5063C39.7005 15.7871 38.7927 15.2973 37.8045 15.0988C36.8162 14.9004 35.7918 15.0022 34.8609 15.3915C33.9299 15.7807 33.1342 16.4399 32.5744 17.2856C32.0146 18.1314 31.7158 19.1257 31.7158 20.1429C31.7158 21.5068 32.2526 22.8149 33.208 23.7794C34.1634 24.7439 35.4593 25.2857 36.8105 25.2857ZM36.8105 17.5714C37.3143 17.5714 37.8069 17.7222 38.2258 18.0048C38.6447 18.2873 38.9712 18.6889 39.164 19.1588C39.3568 19.6287 39.4072 20.1457 39.3089 20.6445C39.2107 21.1433 38.968 21.6015 38.6118 21.9611C38.2555 22.3208 37.8016 22.5657 37.3075 22.6649C36.8133 22.7641 36.3012 22.7132 35.8357 22.5185C35.3702 22.3239 34.9724 21.9943 34.6925 21.5715C34.4126 21.1486 34.2632 20.6514 34.2632 20.1429C34.2632 19.4609 34.5315 18.8068 35.0093 18.3246C35.487 17.8423 36.1349 17.5714 36.8105 17.5714ZM47 35.5714C47 35.9124 46.8658 36.2394 46.6269 36.4806C46.3881 36.7217 46.0641 36.8571 45.7263 36.8571C40.1046 36.8571 37.2961 33.9948 35.0401 31.695C34.6039 31.2498 34.1867 30.8271 33.7664 30.435L31.6282 35.3979L37.5509 39.668C37.7158 39.787 37.8503 39.944 37.9431 40.126C38.0358 40.3079 38.0842 40.5096 38.0842 40.7143V49.7143C38.0842 50.0553 37.95 50.3823 37.7112 50.6234C37.4723 50.8645 37.1483 51 36.8105 51C36.4727 51 36.1488 50.8645 35.9099 50.6234C35.671 50.3823 35.5368 50.0553 35.5368 49.7143V41.3764L30.5902 37.8086L25.2423 50.227C25.1433 50.4567 24.98 50.6523 24.7724 50.7897C24.5647 50.927 24.3219 51.0001 24.0737 51C23.8988 51.0004 23.7257 50.9637 23.5658 50.8923C23.2562 50.7564 23.0126 50.502 22.8888 50.185C22.7649 49.868 22.7707 49.5143 22.9051 49.2016L31.5152 29.2136C30.0329 28.9484 28.1845 29.4064 25.9906 30.5925C24.2408 31.5668 22.6078 32.7407 21.1235 34.0912C20.8758 34.3153 20.551 34.4326 20.2187 34.4181C19.8864 34.4036 19.5729 34.2585 19.3452 34.0137C19.1175 33.769 18.9937 33.444 19.0002 33.1083C19.0068 32.7727 19.1431 32.4529 19.3801 32.2173C19.7782 31.8396 29.2018 23.0196 35.0974 28.1866C35.7072 28.7202 36.2883 29.3116 36.8487 29.8854C39.0697 32.1482 41.1665 34.2857 45.7263 34.2857C46.0641 34.2857 46.3881 34.4212 46.6269 34.6623C46.8658 34.9034 47 35.2304 47 35.5714Z"
                                        fill="#16A34A" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-700">Consultations</h3>
                                <p class="text-3xl font-bold text-green-600"><?= $analytics['consultations'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Consultations Visits</p>
                            </div>
                        </div>
                        <!-- <div class="mt-4">
                            <a href="profile.php" class="text-sm text-green-600 font-medium hover:text-green-800 flex items-center">
                                <i class="fas fa-history mr-2 bold-icon"></i>
                                View consultation history
                            </a>
                        </div> -->
                    </div>

                    <!-- Announcements Card -->
                    <div class="stats-card">
                        <div class="flex items-center mt-4">
                            <div class="stats-icon-container bg-blue-100">
                                <svg width="66" height="66" viewBox="0 0 66 66" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0 4C0 1.79086 1.79086 0 4 0H62C64.2091 0 66 1.79086 66 4V62C66 64.2091 64.2091 66 62 66H4C1.79086 66 0 64.2091 0 62V4Z"
                                        fill-opacity="0.3" />
                                    <path
                                        d="M49.4948 26.2183L20.61 17.3589C20.219 17.2449 19.8069 17.2234 19.4062 17.2961C19.0055 17.3688 18.6273 17.5338 18.3013 17.7779C17.9754 18.0221 17.7107 18.3387 17.5282 18.7028C17.3458 19.0668 17.2505 19.4684 17.25 19.8756V43.5006C17.25 44.1968 17.5266 44.8645 18.0188 45.3568C18.5111 45.8491 19.1788 46.1256 19.875 46.1256C20.126 46.1257 20.3757 46.0898 20.6166 46.019L34.3125 41.8157V43.5006C34.3125 44.1968 34.5891 44.8645 35.0813 45.3568C35.5736 45.8491 36.2413 46.1256 36.9375 46.1256H42.1875C42.8837 46.1256 43.5514 45.8491 44.0437 45.3568C44.5359 44.8645 44.8125 44.1968 44.8125 43.5006V38.5952L49.4948 37.1596C50.0368 36.9968 50.512 36.6641 50.8505 36.2107C51.1891 35.7573 51.3729 35.2071 51.375 34.6413V28.735C51.3726 28.1694 51.1885 27.6196 50.85 27.1665C50.5116 26.7134 50.0365 26.381 49.4948 26.2183ZM34.3125 39.0709L19.875 43.5006V19.8756L34.3125 24.3053V39.0709ZM42.1875 43.5006H36.9375V41.0102L42.1875 39.3991V43.5006ZM48.75 34.6413H48.732L36.9375 38.2638V25.1125L48.732 28.7219H48.75V34.6281V34.6413Z"
                                        fill="#2563EB" />
                                </svg>

                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-700">Announcements</h3>
                                <p class="text-3xl font-bold text-blue-600"><?= $analytics['announcements'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Active announcements</p>
                            </div>
                        </div>
                        <!-- <div class="mt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-green-600 font-medium flex items-center">
                                    <i class="fas fa-check-circle mr-2 bold-icon"></i>
                                    <?= $announcementStats['accepted'] ?> Accepted
                                </span>
                                <span class="text-yellow-600 font-medium flex items-center">
                                    <i class="fas fa-clock mr-2 bold-icon"></i>
                                    <?= $announcementStats['pending'] ?> Pending
                                </span>
                            </div>
                        </div> -->
                    </div>

                    <!-- Health Issues Card -->
                    <div class="stats-card">
                        <div class="flex items-center mt-4">
                            <div class="stats-icon-container bg-violet-200">
                                <svg width="66" height="66" viewBox="0 0 66 66" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0 4C0 1.79086 1.79086 0 4 0H62C64.2091 0 66 1.79086 66 4V62C66 64.2091 64.2091 66 62 66H4C1.79086 66 0 64.2091 0 62V4Z"
                                        fill-opacity="0.3" />
                                    <path
                                        d="M26.125 27.5C26.125 27.1353 26.2699 26.7856 26.5277 26.5277C26.7856 26.2699 27.1353 26.125 27.5 26.125H38.5C38.8647 26.125 39.2144 26.2699 39.4723 26.5277C39.7301 26.7856 39.875 27.1353 39.875 27.5C39.875 27.8647 39.7301 28.2144 39.4723 28.4723C39.2144 28.7301 38.8647 28.875 38.5 28.875H27.5C27.1353 28.875 26.7856 28.7301 26.5277 28.4723C26.2699 28.2144 26.125 27.8647 26.125 27.5ZM27.5 34.375H38.5C38.8647 34.375 39.2144 34.2301 39.4723 33.9723C39.7301 33.7144 39.875 33.3647 39.875 33C39.875 32.6353 39.7301 32.2856 39.4723 32.0277C39.2144 31.7699 38.8647 31.625 38.5 31.625H27.5C27.1353 31.625 26.7856 31.7699 26.5277 32.0277C26.2699 32.2856 26.125 32.6353 26.125 33C26.125 33.3647 26.2699 33.7144 26.5277 33.9723C26.7856 34.2301 27.1353 34.375 27.5 34.375ZM33 37.125H27.5C27.1353 37.125 26.7856 37.2699 26.5277 37.5277C26.2699 37.7856 26.125 38.1353 26.125 38.5C26.125 38.8647 26.2699 39.2144 26.5277 39.4723C26.7856 39.7301 27.1353 39.875 27.5 39.875H33C33.3647 39.875 33.7144 39.7301 33.9723 39.4723C34.2301 39.2144 34.375 38.8647 34.375 38.5C34.375 38.1353 34.2301 37.7856 33.9723 37.5277C33.7144 37.2699 33.3647 37.125 33 37.125ZM49.5 19.25V37.9311C49.5012 38.2924 49.4305 38.6503 49.2921 38.984C49.1537 39.3177 48.9504 39.6206 48.6939 39.875L39.875 48.6939C39.6206 48.9504 39.3177 49.1537 38.984 49.2921C38.6503 49.4305 38.2924 49.5012 37.9311 49.5H19.25C18.5207 49.5 17.8212 49.2103 17.3055 48.6945C16.7897 48.1788 16.5 47.4793 16.5 46.75V19.25C16.5 18.5207 16.7897 17.8212 17.3055 17.3055C17.8212 16.7897 18.5207 16.5 19.25 16.5H46.75C47.4793 16.5 48.1788 16.7897 48.6945 17.3055C49.2103 17.8212 49.5 18.5207 49.5 19.25ZM19.25 46.75H37.125V38.5C37.125 38.1353 37.2699 37.7856 37.5277 37.5277C37.7856 37.2699 38.1353 37.125 38.5 37.125H46.75V19.25H19.25V46.75ZM39.875 39.875V44.8078L44.8061 39.875H39.875Z"
                                        fill="#9333EA" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold text-gray-700">Health Records</h3>
                                <p class="text-3xl font-bold text-[#9333EA]"><?= $analytics['health_issues'] ?></p>
                                <p class="text-sm text-gray-500 mt-1">Linked patient records</p>
                            </div>
                        </div>
                        <!-- <div class="mt-4">
                            <a href="profile.php" class="text-sm text-red-600 font-medium hover:text-red-800 flex items-center">
                                <i class="fas fa-external-link-alt mr-2 bold-icon"></i>
                                View health records
                            </a>
                        </div> -->
                    </div>
                </div>

                <!-- Charts and Activity Log Section -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- LEFT: 40% -->
                    <div class="chart-container lg:flex-[0_0_40%]">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-chart-pie text-blue-600 text-lg bold-icon"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Health Overview Distribution
                            </h3>
                        </div>

                        <div class="flex flex-col md:flex-row">
                            <div class="h-64 w-full md:w-1/2">
                                <canvas id="overviewChart"></canvas>
                            </div>

                            <div class="mt-4 md:mt-0 md:border-l border-gray-200 md:pl-4 md:ml-4 w-full md:w-1/2">
                                <?php foreach ($chartData as $item): ?>
                                    <div class="chart-legend-item">
                                        <div class="chart-legend-icon" style="background-color: <?= $item['color'] ?>20;">
                                            <i class="<?= $item['icon'] ?>"
                                                style="color: <?= $item['color'] ?>; font-weight: 900;"></i>
                                        </div>
                                        <div class="flex-1">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?= $item['category'] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: 60% -->
                    <div class="chart-container-two lg:flex-[0_0_58.5%]">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-history text-purple-600 text-lg bold-icon"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Recent Activity Log
                            </h3>
                        </div>

                        <div class="activity-log-container custom-scrollbar"> <?php if (!empty($activityLog)): ?>
                                <div class="space-y-2"> <?php foreach ($activityLog as $activity): ?>
                                        <div class="activity-log-item <?= $activity['action_type'] ?>">
                                            <div class="activity-icon <?= $activity['action_type'] ?>">
                                                <?php if ($activity['action_type'] == 'login'): ?> <i
                                                        class="fas fa-sign-in-alt text-green-600 text-lg bold-icon"></i>
                                                <?php else: ?> <i
                                                        class="fas fa-sign-out-alt text-red-600 text-lg bold-icon"></i>
                                                <?php endif; ?> </div>
                                            <div class="activity-content">
                                                <div class="activity-header">
                                                    <div>
                                                        <div class="activity-title">
                                                            <?php if ($activity['action_type'] == 'login'): ?> <span
                                                                    class="text-green-600">Account Login</span> <?php else: ?> <span
                                                                    class="text-red-600">Account Logout</span> <?php endif; ?>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            <?= $activity['formatted_date'] ?> </p>
                                                    </div> <span class="activity-time"> <?= $activity['formatted_time'] ?>
                                                    </span>
                                                </div>
                                                <div class="activity-details">
                                                    <div class="activity-detail"> <i
                                                            class="fas fa-clock text-gray-400 bold-icon"></i>
                                                        <span><?= $activity['time_ago'] ?></span> </div>
                                                    <div class="activity-detail"> <i
                                                            class="fas fa-network-wired text-gray-400 bold-icon"></i>
                                                        <span><?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?></span>
                                                    </div>
                                                    <div class="activity-detail"> <?php if ($activity['device'] == 'Mobile'): ?>
                                                            <i class="fas fa-mobile-alt text-gray-400 bold-icon"></i> <?php else: ?>
                                                            <i class="fas fa-desktop text-gray-400 bold-icon"></i> <?php endif; ?>
                                                        <span><?= $activity['browser'] ?> • <?= $activity['device'] ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> <?php endforeach; ?>
                                </div> <?php else: ?>
                                <div class="text-center py-8">
                                    <div
                                        class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-history text-2xl text-gray-300 bold-icon"></i> </div>
                                    <p class="text-gray-500">No recent activity found</p>
                                    <p class="text-sm text-gray-400 mt-1">Your login/logout activities will appear here</p>
                                </div> <?php endif; ?>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between text-sm text-gray-600"> <span class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div> Login Activities
                                </span> <span class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div> Logout Activities
                                </span> </div>
                        </div>
                    </div>



                    <!-- Announcement Response Stats -->
                    <!-- <div class="chart-container">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-bell text-yellow-600 text-lg bold-icon"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Announcement Response Statistics</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div
                            class="bg-green-50 border border-green-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-check text-green-600 text-2xl bold-icon"></i>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold text-green-800"><?= $announcementStats['accepted'] ?>
                                    </p>
                                    <p class="text-sm text-green-600 font-medium">Accepted</p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-clock text-yellow-600 text-2xl bold-icon"></i>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold text-yellow-800"><?= $announcementStats['pending'] ?>
                                    </p>
                                    <p class="text-sm text-yellow-600 font-medium">Pending Response</p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-gray-100 border border-gray-300 rounded-xl p-5 hover:shadow-md transition-shadow">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-gray-200 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-times text-gray-600 text-2xl bold-icon"></i>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold text-gray-800"><?= $announcementStats['dismissed'] ?>
                                    </p>
                                    <p class="text-sm text-gray-600 font-medium">Dismissed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
                </div>
            </div>
        </div>

        <!-- Help Modal -->
        <!-- <div id="helpModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-8 mx-auto p-0 border w-full max-w-2xl shadow-xl rounded-xl bg-white overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-white mb-1">Analytics Dashboard Guide</h3>
                        <p class="text-blue-100 text-sm">Understanding your health analytics</p>
                    </div>
                    <button onclick="closeHelpModal()" class="text-white hover:text-blue-200 transition-colors">
                        <i class="fas fa-times text-2xl bold-icon"></i>
                    </button>
                </div>
            </div>
            
            <div class="px-8 py-6">
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-heartbeat text-red-600 text-lg bold-icon"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Health Records</h4>
                            <p class="text-gray-600">Number of patient profiles linked to your account from the sitio1_patients table.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-bullhorn text-blue-600 text-lg bold-icon"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Announcements</h4>
                            <p class="text-gray-600">Active announcements targeted to you. Track your responses and pending items.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-file-medical-alt text-green-600 text-lg bold-icon"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-lg mb-2">Consultations</h4>
                            <p class="text-gray-600">Total consultation notes from all linked patient profiles in the consultation_notes table.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-history text-purple-600 text-lg bold-icon"></i>
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
                    <i class="fas fa-check mr-2 bold-icon"></i>
                    Got it, thanks!
                </button>
            </div>
        </div>
    </div> -->

        <script>
            // Initialize Charts
            document.addEventListener('DOMContentLoaded', function () {
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
                                    label: function (context) {
                                        return `${context.label}: ${context.raw}`;
                                    }
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });

                // Add animation to activity log items
                const activityItems = document.querySelectorAll('.activity-log-item');
                activityItems.forEach((item, index) => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-10px)';

                    setTimeout(() => {
                        item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        item.style.opacity = '1';
                        item.style.transform = 'translateX(0)';
                    }, index * 100);
                });
            });

            // Help modal functions
            function openHelpModal() {
                document.getElementById('helpModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeHelpModal() {
                document.getElementById('helpModal').classList.add('hidden');
                document.body.style.overflow = 'auto';
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const helpModal = document.getElementById('helpModal');
                if (event.target === helpModal) {
                    closeHelpModal();
                }
            }

            // Close modal with Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeHelpModal();
                }
            });
        </script>
</body>

</html>