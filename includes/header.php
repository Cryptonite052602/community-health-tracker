<?php
ob_start(); // Start output buffering

require_once __DIR__ . '/auth.php';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Get current page to determine active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's profile picture if exists
$profile_picture = null;
if (isset($_SESSION['user']['id'])) {
    // Define paths for profile pictures
    $profile_dir = __DIR__ . '/../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($profile_dir)) {
        mkdir($profile_dir, 0777, true);
    }
    
    // Check for profile picture
    $user_id = $_SESSION['user']['id'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    foreach ($allowed_extensions as $ext) {
        $potential_file = $profile_dir . 'profile_' . $user_id . '.' . $ext;
        if (file_exists($potential_file)) {
            $profile_picture = '/community-health-tracker/uploads/profiles/profile_' . $user_id . '.' . $ext;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Luz Health Monitoring and Tracking</title>
    <link rel="icon" type="image/png" href="../asssets/images/Luz.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/community-health-tracker/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/community-health-tracker/assets/js/scripts.js" defer></script>
</head>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

body,
.sidebar,
.nav-tab,
.logout-btn,
.time-display-container,
.continue-btn,
.complete-btn,
.profile-upload-btn,
.profile-remove-btn,
.logout-cancel-btn,
.logout-confirm-btn {
    font-family: 'Poppins', sans-serif !important;
}

/* Smooth transition for sidebar */
.sidebar {
    transition: transform 0.3s ease-in-out;
}

.sidebar-hidden {
    transform: translateX(-100%);
}

/* Optional: Add overlay for mobile */
.overlay {
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;
}

.overlay-hidden {
    opacity: 0;
    pointer-events: none;
}

/* Blinking colon animation */
@keyframes blink {
    0% {
        opacity: 1;
    }

    50% {
        opacity: 0;
    }

    100% {
        opacity: 1;
    }
}

.blinking-colon {
    animation: blink 1s infinite;
}

/* CLEAN: Simple Navigation Tab Styles */
.nav-tab-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-tab {
    position: relative;
    transition: all 0.3s ease;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    z-index: 1;
}

.nav-tab.active {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.nav-tab.active::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 60%;
    height: 2px;
    background: white;
    border-radius: 2px;
}

.nav-tab:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
}

/* CLEAN: Simple Logout Button - UPDATED FOR FULL ROUND */
.logout-btn {
    background: #ef4444;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px !important; /* Full round radius */
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    outline: none;
}

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* NEW: Improved time display containers - Horizontal layout */
.time-display-container {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.6rem 1.2rem;
    border-radius: 0.75rem;
    margin-left: auto;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.staff-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

.user-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

.admin-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

/* NEW: Horizontal time display styles */
.time-display-horizontal {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-display-horizontal {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.time-display-main-horizontal {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 1px;
    white-space: nowrap;
}

.time-separator {
    height: 24px;
    width: 1px;
    background: rgba(255, 255, 255, 0.4);
    margin: 0 0.5rem;
}

.time-zone {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    opacity: 0.9;
    font-style: italic;
    font-weight: 500;
}

/* Hidden refresh indicator */
.refresh-indicator {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
    opacity: 0;
}

/* Form validation styles */
.form-input:invalid {
    border-color: #fca5a5;
}

.form-input:valid {
    border-color: #74b4fdff;
}

/* Updated Registration Button Styles with Rounded XL Sides */
.continue-btn, .complete-btn {
    width: 100%;
    border-radius: 9999px !important; /* rounded-full equivalent */
    padding: 0.75rem 2.8rem !important;
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: white !important;
    transition: all 0.3s ease !important;
    border: none !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    text-decoration: none !important; /* Remove underline for links */
}

/* First Registration Modal Button (Red) */
.continue-btn {
    background-color: #4A90E2 !important;
}

.continue-btn:hover {
    background-color: #337ed3ff !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(252, 86, 108, 0.3) !important;
}

/* Complete Registration, Login, and Book Appointment Buttons (Warm Blue) */
.complete-btn {
    background-color: #4A90E2 !important;
}

.complete-btn:hover {
    background-color: #357ABD !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(74, 144, 226, 0.3) !important;
}

.continue-btn:active, .complete-btn:active {
    transform: scale(0.98) !important;
}

.continue-btn:disabled, .complete-btn:disabled {
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
    opacity: 0.5 !important;
}

.continue-btn:disabled {
    background-color: #4A90E2 !important;
}

.complete-btn:disabled {
    background-color: #4A90E2 !important;
}

.continue-btn svg, .complete-btn svg {
    width: 16px !important;
    height: 16px !important;
    margin-left: 8px !important;
}

.continue-btn:disabled:hover, .complete-btn:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Logo image styles */
.logo-image {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Header title styles */
.header-title-container {
    display: flex;
    flex-direction: column;
}

.barangay-text {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    margin-bottom: 2px;
    opacity: 0.9;
}

.main-title {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1.2;
}

/* Profile section styles */
.profile-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 1rem;
    border-left: 1px solid rgba(255, 255, 255, 0.3);
}

.profile-avatar {
    background-color: #d1d5db;
    height: 32px;
    width: 32px;
    border-radius: 50%;
    background-size: cover;
    background-position: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.profile-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.profile-avatar.has-image::after {
    content: 'Change';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.profile-avatar.has-image:hover::after {
    opacity: 1;
}

.profile-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.welcome-text {
    color: white;
    font-size: 0.875rem;
}

.username-text {
    font-size: 0.75rem;
}

/* NEW: Enhanced Modal Styles */
.modal-overlay {
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;
}

.modal-content {
    transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
    transform: scale(0.95);
    opacity: 0;
}

.modal-content.open {
    transform: scale(1);
    opacity: 1;
}

.modal-close-btn {
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 50%;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.1);
    transform: rotate(90deg);
}

/* Profile Picture Upload Modal */
.profile-modal-content {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.profile-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #3C96E1;
    margin: 0 auto;
}

.profile-upload-btn {
    background: #3C96E1;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-upload-btn:hover {
    background: #2B7CC9;
    transform: translateY(-1px);
}

.profile-remove-btn {
    background: #ef4444;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-remove-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Logout Modal Styles */
.logout-modal-content {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.logout-modal-buttons {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.logout-cancel-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid #d1d5db;
    background: white;
    color: #4b5563;
    cursor: pointer;
}

.logout-cancel-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.logout-confirm-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    background: #ef4444;
    color: white;
    cursor: pointer;
}

.logout-confirm-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Add these new styles for disabled buttons */
.continue-btn:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

.continue-btn:disabled:hover {
    background-color: #4A90E2 !important;
    transform: none !important;
    box-shadow: none !important;
}

/* Responsive adjustments */
@media (max-width: 1024px) {

    .staff-nav-container,
    .user-nav-container,
    .admin-nav-container {
        flex-direction: column;
        gap: 0.5rem;
    }

    .nav-tab {
        position: relative;
        transition: all 0.3s ease;
        padding: 0.75rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 600;
        z-index: 1;
    }

    .nav-tab.active {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }

    .nav-tab.active::after {
        content: '';
        position: absolute;
        bottom: -6px;
        left: 50%;
        transform: translateX(-50%);
        width: 60%;
        height: 2px;
        background: white;
        border-radius: 2px;
    }

    .nav-tab:hover:not(.active) {
        background: rgba(255, 255, 255, 0.1);
    }

    /* CLEAN: Simple Logout Button - UPDATED FOR FULL ROUND */
    .logout-btn {
        background: #2B7CC9;
        padding: 0.75rem 1.5rem;
        border-radius: 9999px !important; /* Full round radius */
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        cursor: pointer;
        border: none;
        outline: none;
    }

    .logout-btn:hover {
        background: #3C96E1;
        transform: translateY(-1px);
        color: white;
    }

    /* NEW: Improved time display containers - Horizontal layout */
    .time-display-container {
        margin-left: 0;
        align-self: flex-end;
    }

    .nav-tab-container {
        justify-content: center;
    }

    .search-input {
        width: 200px;
    }
}

@media (max-width: 768px) {
    .time-display-container {
        padding: 0.5rem 1rem;
    }

    .date-display-horizontal {
        font-size: 0.8rem;
    }

    .time-display-main-horizontal {
        font-size: 0.9rem;
    }

    .nav-tab {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }

    .logout-btn {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }

    .time-zone {
        font-size: 0.75rem;
        margin-left: 0.25rem;
        opacity: 0.9;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
        display: none;
    }

    .logo-image {
        width: 50px;
        height: 50px;
    }

    .barangay-text {
        font-size: 0.8rem;
    }

    .main-title {
        font-size: 1.25rem;
    }

    .search-input {
        width: 180px;
    }

    .profile-section {
        padding-left: 0.5rem;
    }

    .profile-avatar {
        height: 28px;
        width: 28px;
    }
}

@media (max-width: 640px) {
    .time-display-horizontal {
        flex-direction: column;
        gap: 0.2rem;
    }

    .time-separator {
        display: none;
    }

    .date-display-horizontal {
        font-size: 0.75rem;
    }

    .time-display-main-horizontal {
        font-size: 0.85rem;
    }

    .nav-tab-container {
        flex-wrap: wrap;
        justify-content: center;
    }

    .nav-tab {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .logo-image {
        width: 45px;
        height: 45px;
    }

    .barangay-text {
        font-size: 0.75rem;
    }

    .main-title {
        font-size: 1.1rem;
    }

    .search-input {
        width: 150px;
        font-size: 0.875rem;
    }

    .profile-info {
        display: none;
    }
}
</style>

<body class="bg-[#F8F8F]">
    <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
            <!-- Admin Header - Updated to match user/staff design -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Health Center Admin Panel</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search Bar -->
                        <div class="search-container">
                            
                        </div>
                        
                        <!-- Profile Section -->
                        <div class="profile-section">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('admin')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-2xl text-gray-400 absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <span class="welcome-text">Welcome Super Admin!</span>
                                <span class="username-text"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                            </div>
                        </div>
                        
                        <!-- Enhanced Logout Button - UPDATED FOR FULL ROUND -->
                        <button type="button" onclick="showLogoutModal('admin')" class="logout-btn">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between admin-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="../admin/dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="../admin/manage_accounts.php"
                                    class="nav-tab <?= ($current_page == 'manage_accounts.php') ? 'active' : '' ?>">
                                    Manage Accounts
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container admin-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="admin-ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="admin-ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="admin-ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="admin-ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="admin-ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

        <?php elseif (isStaff()): ?>
            <!-- Staff Header -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Health Center Staff Panel</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-10">
                        <div class="flex items-center space-x-5">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('staff')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium">Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        </div>
                        <button type="button" onclick="showLogoutModal('staff')" class="logout-btn bg-white text-[#3C96E1] hover:bg-[#2B7CC9] hover:text-white">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between staff-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/existing_info_patients.php"
                                    class="nav-tab <?= ($current_page == 'existing_info_patients.php') ? 'active' : '' ?>">
                                    Medical Records
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/announcements.php"
                                    class="nav-tab <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container staff-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="staff-ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="staff-ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="staff-ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

        <?php elseif (isUser()): ?>
            <!-- User Header -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Resident Consultation Portal</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-10">
                        <div class="flex items-center space-x-5">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('user')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        </div>
                        <!-- Enhanced Logout Button - UPDATED FOR FULL ROUND -->
                        <button type="button" onclick="showLogoutModal('user')" class="logout-btn bg-white text-[#3C96E1] hover:bg-[#2B7CC9] hover:text-white">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between user-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="health_records.php"
                                    class="nav-tab <?= ($current_page == 'health_records.php') ? 'active' : '' ?>">
                                    My Record
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="announcements.php"
                                    class="nav-tab <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container user-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <!-- Public Header (Not logged in) -->
        <style>
            .mobile-menu {
                display: none;
            }

            .mobile-menu.active {
                display: block;
            }

            .touch-target {
                position: relative;
            }

            .touch-target::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 40px;
                height: 40px;
            }

            .circle-image {
                width: 65px;
                height: 65px;
                border-radius: 50%;
                object-fit: cover;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .nav-container {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            .logo-text {
                line-height: 1.2;
            }

            .nav-link {
                font-size: 1.1rem;
                padding: 0.5rem 1rem;
            }
        </style>
        </head>

        <body>
            

            <style>
                /* Mobile menu styles */
                .mobile-menu {
                    transition: all 0.3s ease;
                    max-height: 0;
                    overflow: hidden;
                }

                .mobile-menu-open {
                    max-height: 1000px;
                }

                /* Better touch targets for mobile */
                .touch-target {
                    min-height: 48px;
                    min-width: 48px;
                }
            </style>

            <script>
                // Mobile menu toggle
                function toggleMobileMenu() {
                    const mobileMenu = document.getElementById('mobile-menu');
                    mobileMenu.classList.toggle('mobile-menu-open');
                }

                // Close mobile menu when clicking outside
                document.addEventListener('click', function (event) {
                    const mobileMenu = document.getElementById('mobile-menu');
                    const mobileMenuButton = document.querySelector('.md\\:hidden.touch-target');

                    if (mobileMenu && mobileMenuButton &&
                        !mobileMenu.contains(event.target) &&
                        !mobileMenuButton.contains(event.target) &&
                        mobileMenu.classList.contains('mobile-menu-open')) {
                        mobileMenu.classList.remove('mobile-menu-open');
                    }
                });
            </script>

            <!-- Login Modal Only -->
            <div id="loginModal" class="fixed inset-0 hidden z-50 h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
                <div class="relative bg-white p-4 sm:p-6 rounded-lg shadow-lg w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto modal-content">
                    <!-- Close Button -->
                    <button onclick="closeModal()"
                        class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Logo at the top -->
                    <div class="flex justify-center mb-6 mx-4">
                        <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo" 
                             class="w-20 h-20 rounded-full object-cover border-4 border-[#3C96E1] shadow-lg">
                    </div>

                    <!-- Main Title -->
                    <div class="text-center mb-4 mx-4">
                        <h1 class="text-2xl font-bold text-[#4A90E2]">Barangay Luz Cebu City</h1>
                    </div>

                    <!-- Instruction Text -->
                    <div class="flex flex-col items-center mb-8 mx-4">
                        <p class="text-sm text-center text-gray-600 max-w-md leading-relaxed">
                            Please log in with your authorized account to access health records, appointments, and other health services.
                        </p>
                    </div>

                    <!-- Login Form -->
                    <form method="POST" action="auth/login.php" class="space-y-6">
                        <input type="hidden" name="role" value="user">
                        <div class="space-y-6 mx-4">
                            <!-- Username -->
                            <div>
                                <label for="login-username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                                <input type="text" name="username" id="login-username" placeholder="Enter Username"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" required />
                            </div>

                            <!-- Password -->
                            <div>
                                <label for="login-password" class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input id="login-password" name="password" type="password" placeholder="Password"
                                        class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" required />
                                    <button type="button" onclick="toggleLoginPassword()"
                                        class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                                        <i id="login-eyeIcon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Forgot Password -->
                            <div class="text-right mt-4">
                                <a href="#" class="text-sm text-[#3C96E2] hover:underline">Forgot your password?</a>
                            </div>

                            <!-- Login Button -->
                            <div class="mt-8">
                                <button type="submit"
                                    class="complete-btn bg-[#3C96E1] w-full p-3 rounded-full text-white transition-all duration-200 font-medium shadow-md hover:shadow-lg text-lg h-14">
                                    Login
                                </button>
                            </div>

                            <!-- Registration Notice -->
                            <div class="text-center text-sm text-gray-600 mt-6">
                                <p>New residents need to register at the Barangay Health Center to obtain login credentials.</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>

        <!-- Profile Picture Upload Modal -->
        <div id="profileModal" class="fixed inset-0 hidden z-[70] h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
            <div class="relative bg-white p-6 sm:p-8 rounded-lg shadow-lg w-full max-w-md mx-auto modal-content profile-modal-content">
                <!-- Close Button -->
                <button onclick="closeProfileModal()"
                    class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Modal Title -->
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Profile Picture</h3>
                    <p class="text-gray-600 mt-2">Upload or change your profile picture</p>
                </div>

                <!-- Current Profile Picture Preview -->
                <div class="mb-6">
                    <img id="profilePreview" src="<?php echo $profile_picture ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEyIiBmaWxsPSIjZDFkNWRiIi8+PHBhdGggZD0iTTEyIDExYTIgMiAwIDEgMCAwLTQgMiAyIDAgMCAwIDAgNHoiIGZpbGw9IiM5Y2EzYWYiLz48cGF0aCBkPSJNMTIgMTVhNCA0IDAgMCAwLTQgNGg4YTQgNCAwIDAgMC00LTR6IiBmaWxsPSIjOWNhM2FmIi8+PC9zdmc+' ?>"
                         alt="Profile Preview" class="profile-preview">
                </div>

                <!-- Upload Form -->
                <form id="profileUploadForm" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="user_id" value="<?php echo isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : '' ?>">
                    <input type="hidden" name="user_type" id="profileUserType" value="">
                    
                    <div>
                        <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-2">
                            Choose Profile Picture
                        </label>
                        <input type="file" id="profile_image" name="profile_image" 
                            accept=".jpg,.jpeg,.png,.gif"
                            class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-500 mt-2">Max file size: 2MB (JPEG, PNG, GIF)</p>
                        <div id="profileUploadError" class="text-xs text-red-500 mt-2 hidden"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="removeProfilePicture()" 
                            class="profile-remove-btn flex-1 <?php echo !$profile_picture ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?php echo !$profile_picture ? 'disabled' : '' ?>>
                            <i class="fas fa-trash-alt"></i>
                            Remove
                        </button>
                        <button type="submit" class="profile-upload-btn flex-1">
                            <i class="fas fa-upload"></i>
                            Upload
                        </button>
                    </div>
                </form>

                <!-- Loading Indicator -->
                <div id="profileLoading" class="hidden mt-4 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#3C96E1]"></div>
                    <p class="text-sm text-gray-600 mt-2">Uploading...</p>
                </div>
            </div>
        </div>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal" class="fixed inset-0 hidden z-[60] h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
            <div class="relative bg-white p-6 sm:p-8 rounded-lg shadow-lg w-full max-w-md mx-auto modal-content logout-modal-content">
                <!-- Close Button -->
                <button onclick="closeLogoutModal()"
                    class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Warning Icon -->
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                </div>

                <!-- Modal Title -->
                <div class="text-center mb-2">
                    <h3 class="text-xl font-bold text-gray-800">Confirm Logout</h3>
                </div>

                <!-- Modal Message -->
                <div class="text-center mb-6">
                    <p class="text-gray-600">Are you sure you want to logout?</p>
                    <p class="text-sm text-gray-500 mt-1">You will need to log in again to access your account.</p>
                </div>

                <!-- Modal Buttons -->
                <div class="logout-modal-buttons">
                    <button type="button" onclick="closeLogoutModal()" class="logout-cancel-btn">
                        Cancel
                    </button>
                    <button type="button" id="confirmLogoutBtn" class="logout-confirm-btn">
                        Yes, Logout
                    </button>
                </div>
            </div>
        </div>

        <main class="container mx-auto mt-24"> <!-- Added mt-24 to account for the fixed header height -->
            <!-- Your main content here -->
        </main>

        <!-- Hidden refresh indicator -->
        <div id="refreshIndicator" class="refresh-indicator"></div>

        <script>
            // Global variable to store logout URL
            let logoutUrl = '';

            // Function to open profile picture modal
            function openProfileModal(userType) {
                const modal = document.getElementById("profileModal");
                const modalContent = modal.querySelector('.modal-content');
                const userTypeInput = document.getElementById('profileUserType');
                
                // Set user type for form submission
                userTypeInput.value = userType;
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
                
                // Set focus to upload button for accessibility
                setTimeout(() => {
                    document.querySelector('.profile-upload-btn').focus();
                }, 50);
            }

            // Function to close profile modal
            function closeProfileModal() {
                const modal = document.getElementById("profileModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                }, 300);
            }

            // Function to show logout confirmation modal
            function showLogoutModal(userType) {
                // Set the logout URL based on user type
                switch(userType) {
                    case 'admin':
                        logoutUrl = '../auth/logout.php';
                        break;
                    case 'staff':
                        logoutUrl = '/community-health-tracker/auth/logout.php';
                        break;
                    case 'user':
                        logoutUrl = '../auth/logout_user.php';
                        break;
                    default:
                        logoutUrl = '../auth/logout.php';
                }
                
                const modal = document.getElementById("logoutModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
                
                // Set focus to cancel button for accessibility
                setTimeout(() => {
                    document.querySelector('.logout-cancel-btn').focus();
                }, 50);
            }

            // Function to close logout modal
            function closeLogoutModal() {
                const modal = document.getElementById("logoutModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                }, 300);
            }

            // Handle logout confirmation
            document.getElementById('confirmLogoutBtn').addEventListener('click', function() {
                // Redirect to logout URL
                window.location.href = logoutUrl;
            });

            // Profile picture upload functionality
            document.addEventListener('DOMContentLoaded', function() {
                const profileUploadForm = document.getElementById('profileUploadForm');
                const profileImageInput = document.getElementById('profile_image');
                const profilePreview = document.getElementById('profilePreview');
                const profileUploadError = document.getElementById('profileUploadError');
                const profileLoading = document.getElementById('profileLoading');
                const removeProfileBtn = document.querySelector('.profile-remove-btn');

                // Preview image when file is selected
                profileImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Validate file size (2MB)
                        if (file.size > 2 * 1024 * 1024) {
                            profileUploadError.textContent = 'File size exceeds 2MB limit.';
                            profileUploadError.classList.remove('hidden');
                            this.value = '';
                            return;
                        }

                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            profileUploadError.textContent = 'Invalid file type. Please upload JPEG, PNG, or GIF images.';
                            profileUploadError.classList.remove('hidden');
                            this.value = '';
                            return;
                        }

                        // Clear any previous errors
                        profileUploadError.classList.add('hidden');

                        // Create preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profilePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });

                // Handle form submission
                profileUploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const file = profileImageInput.files[0];
                    
                    if (!file) {
                        profileUploadError.textContent = 'Please select a file to upload.';
                        profileUploadError.classList.remove('hidden');
                        return;
                    }

                    // Show loading indicator
                    profileLoading.classList.remove('hidden');
                    profileUploadForm.classList.add('opacity-50');
                    
                    // Submit via AJAX
                    fetch('/community-health-tracker/auth/upload_profile.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update profile picture in header
                            updateProfilePicture(data.profile_url);
                            
                            // Show success message
                            alert('Profile picture updated successfully!');
                            
                            // Close modal
                            closeProfileModal();
                        } else {
                            // Show error
                            profileUploadError.textContent = data.message || 'Upload failed. Please try again.';
                            profileUploadError.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        profileUploadError.textContent = 'An error occurred. Please try again.';
                        profileUploadError.classList.remove('hidden');
                    })
                    .finally(() => {
                        // Hide loading indicator
                        profileLoading.classList.add('hidden');
                        profileUploadForm.classList.remove('opacity-50');
                    });
                });

                // Remove profile picture
                window.removeProfilePicture = function() {
                    if (!confirm('Are you sure you want to remove your profile picture?')) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('user_id', document.querySelector('input[name="user_id"]').value);
                    formData.append('user_type', document.getElementById('profileUserType').value);
                    formData.append('remove', '1');

                    // Show loading indicator
                    profileLoading.classList.remove('hidden');
                    profileUploadForm.classList.add('opacity-50');
                    
                    fetch('/community-health-tracker/auth/upload_profile.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update profile picture in header
                            updateProfilePicture(null);
                            
                            // Reset preview to default
                            profilePreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEyIiBmaWxsPSIjZDFkNWRiIi8+PHBhdGggZD0iTTEyIDExYTIgMiAwIDEgMCAwLTQgMiAyIDAgMCAwIDAgNHoiIGZpbGw9IiM5Y2EzYWYiLz48cGF0aCBkPSJNMTIgMTVhNCA0IDAgMCAwLTQgNGg4YTQgNCAwIDAgMC00LTR6IiBmaWxsPSIjOWNhM2FmIi8+PC9zdmc+';
                            
                            // Disable remove button
                            removeProfileBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            removeProfileBtn.disabled = true;
                            
                            // Show success message
                            alert('Profile picture removed successfully!');
                        } else {
                            alert(data.message || 'Failed to remove profile picture.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Hide loading indicator
                        profileLoading.classList.add('hidden');
                        profileUploadForm.classList.remove('opacity-50');
                    });
                };

                // Function to update profile picture in header
                function updateProfilePicture(imageUrl) {
                    const profileAvatars = document.querySelectorAll('.profile-avatar');
                    profileAvatars.forEach(avatar => {
                        if (imageUrl) {
                            avatar.style.backgroundImage = `url('${imageUrl}')`;
                            avatar.classList.add('has-image');
                            // Remove the icon if it exists
                            const icon = avatar.querySelector('i');
                            if (icon) {
                                icon.remove();
                            }
                        } else {
                            avatar.style.backgroundImage = '';
                            avatar.classList.remove('has-image');
                            // Add the default icon
                            if (!avatar.querySelector('i')) {
                                const icon = document.createElement('i');
                                icon.className = 'fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center';
                                avatar.appendChild(icon);
                            }
                        }
                    });
                }
            });

            // Close modal when clicking outside
            document.getElementById('profileModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProfileModal();
                }
            });

            document.getElementById('logoutModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeLogoutModal();
                }
            });

            // Close modals with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const profileModal = document.getElementById('profileModal');
                    const logoutModal = document.getElementById('logoutModal');
                    
                    if (!profileModal.classList.contains('hidden')) {
                        closeProfileModal();
                    } else if (!logoutModal.classList.contains('hidden')) {
                        closeLogoutModal();
                    }
                }
                
                // Handle Enter key on confirm button
                if (e.key === 'Enter' && document.activeElement.id === 'confirmLogoutBtn') {
                    document.getElementById('confirmLogoutBtn').click();
                }
            });

            // Function to update Philippine time in real-time
            function updatePhilippineTime() {
                const now = new Date();

                // Get the current time in the Philippines (UTC+8)
                // Since we're using the server's timezone setting (Asia/Manila),
                // we can use local time methods
                const hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';

                // Convert to 12-hour format
                let hours12 = hours % 12;
                hours12 = hours12 ? hours12 : 12; // Convert 0 to 12
                const hoursStr = hours12.toString().padStart(2, '0');

                // Format date
                const options = { month: 'short', day: 'numeric', year: 'numeric' };
                const dateStr = now.toLocaleDateString('en-US', options);

                // Update the elements for user
                if (document.getElementById('ph-date')) {
                    document.getElementById('ph-date').textContent = dateStr;
                    document.getElementById('ph-hours').textContent = hoursStr;
                    document.getElementById('ph-minutes').textContent = minutes;
                    document.getElementById('ph-seconds').textContent = seconds;
                    document.getElementById('ph-ampm').textContent = ampm;
                }

                // Update the elements for staff
                if (document.getElementById('staff-ph-date')) {
                    document.getElementById('staff-ph-date').textContent = dateStr;
                    document.getElementById('staff-ph-hours').textContent = hoursStr;
                    document.getElementById('staff-ph-minutes').textContent = minutes;
                    document.getElementById('staff-ph-seconds').textContent = seconds;
                    document.getElementById('staff-ph-ampm').textContent = ampm;
                }

                // Update the elements for admin
                if (document.getElementById('admin-ph-date')) {
                    document.getElementById('admin-ph-date').textContent = dateStr;
                    document.getElementById('admin-ph-hours').textContent = hoursStr;
                    document.getElementById('admin-ph-minutes').textContent = minutes;
                    document.getElementById('admin-ph-seconds').textContent = seconds;
                    document.getElementById('admin-ph-ampm').textContent = ampm;
                }

                // Update the hidden refresh indicator (for debugging/verification)
                document.getElementById('refreshIndicator').textContent = `Last refresh: ${now.toLocaleTimeString()}`;
            }

            // Update time immediately and then every second
            updatePhilippineTime();
            let timeInterval = setInterval(updatePhilippineTime, 1000);

            // Advanced time synchronization function
            function synchronizeTime() {
                const now = new Date();
                const milliseconds = now.getMilliseconds();

                // Calculate delay to sync with the next second change
                const delay = 1000 - milliseconds;

                // Clear existing interval
                clearInterval(timeInterval);

                // Set new interval that starts at the next second
                setTimeout(() => {
                    updatePhilippineTime();
                    timeInterval = setInterval(updatePhilippineTime, 1000);
                }, delay);
            }

            // Start synchronized timekeeping
            synchronizeTime();

            // Clean Navigation Tab Interaction
            document.addEventListener('DOMContentLoaded', function () {
                const navTabs = document.querySelectorAll('.nav-tab');

                navTabs.forEach(tab => {
                    tab.addEventListener('click', function (e) {
                        // Prevent default if it's not a link
                        if (this.getAttribute('href') === '#') {
                            e.preventDefault();
                        }

                        // Remove active class from all tabs
                        navTabs.forEach(t => t.classList.remove('active'));

                        // Add active class to clicked tab
                        this.classList.add('active');

                        // Store active state in sessionStorage
                        sessionStorage.setItem('activeNav', this.getAttribute('href'));
                    });
                });

                // Check if there's an active nav stored
                const activeNav = sessionStorage.getItem('activeNav');
                if (activeNav) {
                    const activeTab = document.querySelector(`.nav-tab[href="${activeNav}"]`);
                    if (activeTab) {
                        // Remove active class from all tabs first
                        navTabs.forEach(tab => tab.classList.remove('active'));
                        // Add active class to stored tab
                        activeTab.classList.add('active');
                    }
                }

                // Background time synchronization
                function backgroundTimeSync() {
                    // Check time accuracy every 30 seconds
                    setInterval(() => {
                        const now = new Date();
                        const expectedSeconds = (now.getSeconds() + 1) % 60;

                        // Schedule a check for the next second
                        setTimeout(() => {
                            const checkTime = new Date();
                            if (checkTime.getSeconds() !== expectedSeconds) {
                                // Time is out of sync, resynchronize
                                synchronizeTime();
                            }
                        }, 1000 - now.getMilliseconds());
                    }, 30000); // Check every 30 seconds
                }

                // Start background time synchronization
                backgroundTimeSync();

                // Handle mobile virtual keyboard issues
                if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    const inputs = document.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.addEventListener('focus', function () {
                            // Scroll the input into view with some padding
                            setTimeout(() => {
                                this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }, 300);
                        });
                    });
                }
            });

            // Page visibility API to optimize time updates
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    // Page is hidden, reduce update frequency to save resources
                    clearInterval(timeInterval);
                    timeInterval = setInterval(updatePhilippineTime, 5000); // Update every 5 seconds when tab is hidden
                } else {
                    // Page is visible, resume normal update frequency
                    clearInterval(timeInterval);
                    synchronizeTime(); // Resync time when returning to the tab
                }
            });

            // Enhanced Modal functions with smooth transitions
            function openModal() {
                const modal = document.getElementById("loginModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
            }

            function closeModal() {
                const modal = document.getElementById("loginModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                }, 300);
            }

            function toggleLoginPassword() {
                const input = document.getElementById("login-password");
                const icon = document.getElementById("login-eyeIcon");

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("fa-eye");
                    icon.classList.add("fa-eye-slash");
                } else {
                    input.type = "password";
                    icon.classList.remove("fa-eye-slash");
                    icon.classList.add("fa-eye");
                }
            }

            // Close modal when clicking outside
            document.getElementById('loginModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        </script>
        
    </body>

</html>