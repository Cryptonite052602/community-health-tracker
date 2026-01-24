<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

// Fetch active announcements for landing page
$announcements = [];
$hasAnnouncements = false;
try {
    $stmt = $pdo->prepare("SELECT title, message, priority, post_date, expiry_date, image_path 
                          FROM sitio1_announcements 
                          WHERE status = 'active' AND audience_type = 'landing_page' 
                          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                          ORDER BY 
                            CASE priority 
                                WHEN 'high' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'normal' THEN 3
                            END,
                            post_date DESC 
                          LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasAnnouncements = !empty($announcements);
} catch (PDOException $e) {
    // Silently fail - announcements are not critical for page load
    error_log("Error fetching announcements: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Luz - Health Monitoring and Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --warm-blue: #3a7bd5;
            --warm-blue-light: #4a90e2;
            --warm-blue-dark: #2a6bc5;
            --off-white: #f8fafc;
        }
        
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px; /* Add scroll padding for anchor links */
        }
        
        body {
            background-color: var(--off-white);
            margin: 0;
            padding: 0;
        }
        
        .warm-blue-bg {
            background-color: var(--warm-blue);
        }
        
        .warm-blue-light-bg {
            background-color: var(--warm-blue-light);
        }
        
        .warm-blue-text {
            color: var(--warm-blue);
        }
        
        .section-title01 {
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .section-title01::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background-color: var(--warm-blue);
            border-radius: 2px;
        }
        .section-title02 {
            position: relative;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .section-title02::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background-color: white;
            border-radius: 2px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(58, 123, 213, 0.1);
            border: 1px solid rgba(58, 123, 213, 0.1);
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(58, 123, 213, 0.15);
        }
        
        .service-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .announcement-priority {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .floating-announcement-container {
            z-index: 9999;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Header Styles - FIXED with proper padding */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #F8FAFC;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            padding-top: 1rem;
            padding-bottom: 1rem;
            height: auto;
        }
        
        .mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .mobile-menu.show {
            max-height: 500px;
            transition: max-height 0.5s ease-in;
        }

        .touch-target {
            min-width: 44px;
            min-height: 44px;
        }

        .circle-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4A90E2;
        }

        .logo-text {
            line-height: 1.2;
        }

        .complete-btn {
            padding: 12px 28px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .complete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74, 144, 226, 0.3);
        }

        .nav-link {
            position: relative;
            padding: 8px 0;
        }

        .nav-link.active {
            color: #4A90E2;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        /* Modal Styles */
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

        /* Login Modal Specific */
        .login-modal-content {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* SECTION STYLES - PROPER SPACING */
        section {
            width: 100%;
            margin: 0;
            padding: 0;
            position: relative;
        }
        
        /* Hero Section - Full height with proper spacing from header */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
            padding-top: 100px; /* Match this with header height */
        }
        
        /* Content sections with visual hierarchy */
        .section-padding {
            padding: 6rem 0;
        }
        
        .section-padding-lg {
            padding: 8rem 0;
        }
        
        .section-padding-md {
            padding: 4rem 0;
        }
        
        /* Ensure proper scroll margin for anchor links */
        section[id] {
            scroll-margin-top: 100px; /* Adjust based on actual header height */
        }
        
        /* Remove any default margins and paddings */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Consistent spacing for content within sections */
        .content-spacing > * + * {
            margin-top: 1.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                min-height: calc(100vh - 80px);
                padding-top: 80px;
            }
            
            .section-padding {
                padding: 4rem 0;
            }
            
            .section-padding-lg {
                padding: 5rem 0;
            }
            
            .section-padding-md {
                padding: 3rem 0;
            }
            
            section[id] {
                scroll-margin-top: 80px;
            }
            
            html {
                scroll-padding-top: 80px;
            }
        }
        
        @media (max-width: 640px) {
            .section-padding {
                padding: 3rem 0;
            }
            
            .section-padding-lg {
                padding: 4rem 0;
            }
            
            .section-padding-md {
                padding: 2.5rem 0;
            }
        }
        
        /* Gradient background for hero */
        .hero-gradient {
            background: linear-gradient(135deg, #3a7bd5 0%, #2a6bc5 100%);
        }
        
        /* Smooth transitions */
        .transition-all {
            transition: all 0.3s ease;
        }
        
        /* Card hover effects */
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(58, 123, 213, 0.15);
        }
        
        /* Floating button animation */
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        /* Better spacing for content */
        .grid-spacing {
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .grid-spacing {
                gap: 1.5rem;
            }
        }
        
        /* Consistent button styling */
        .btn-primary {
            background-color: white;
            color: var(--warm-blue);
            padding: 1.5rem 2.2rem;
            border-radius: 30rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(58, 123, 213, 0.3);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: var(--warm-blue);
            border: 2px solid var(--warm-blue);
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background-color: var(--warm-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Consistent text sizes */
        .text-lead {
            font-size: 1.125rem;
            line-height: 1.7;
            color: #4a5568;
        }
        
        /* Image styling */
        .responsive-img {
            width: 100%;
            height: auto;
            max-width: 100%;
            border-radius: 0.5rem;
        }
        
        /* Form styling */
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--warm-blue);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }
        
        /* Header mobile menu positioning */
        #mobile-menu {
            position: absolute;
            left: 0;
            right: 0;
            top: 100%;
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="font-sans antialiased">
    <!-- Header Navigation - FIXED AT TOP -->
    <header class="main-header">
        <div class="header-content">
            <nav class="text-black">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center">
                        <!-- Logo/Title with two-line text -->
                        <div class="flex items-center">
                            <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                                class="circle-image mr-4">
                            <div class="logo-text">
                                <div class="font-bold text-xl leading-tight">Barangay Luz</div>
                                <div class="text-lg text-gray-700">Monitoring and Tracking</div>
                            </div>
                        </div>

                        <!-- Mobile menu button - hidden on desktop -->
                        <button class="md:hidden touch-target p-2" onclick="toggleMobileMenu()">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>

                        <!-- Desktop navigation - centered nav list -->
                        <div class="hidden md:flex items-center flex-1 justify-center">
                            <!-- Centered nav links -->
                            <ul class="flex items-center space-x-8 font-semibold">
                                <li>
                                    <a href="#home"
                                        class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Home</a>
                                </li>
                                <li>
                                    <a href="#about"
                                        class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">About</a>
                                </li>
                                <li>
                                    <a href="#services"
                                        class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Services</a>
                                </li>
                                <li>
                                    <a href="#contact"
                                        class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Contact</a>
                                </li>
                            </ul>
                        </div>

                        <!-- Login button - positioned to the right -->
                        <div class="hidden md:flex items-center">
                            <a href="#" onclick="openLoginModal()"
                                class="complete-btn bg-[#4A90E2] text-lg text-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg px-6 py-3">
                                Resident Login
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Mobile menu content - only shows on mobile -->
        <div id="mobile-menu" class="mobile-menu md:hidden bg-white border-t border-gray-200 shadow-lg">
            <div class="px-4 pt-4 pb-6 space-y-2">
                <a href="#home" onclick="toggleMobileMenu()" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-[#4A90E2] rounded-lg transition-all duration-300 nav-link">Home</a>
                <a href="#about" onclick="toggleMobileMenu()" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-[#4A90E2] rounded-lg transition-all duration-300 nav-link">About</a>
                <a href="#services" onclick="toggleMobileMenu()" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-[#4A90E2] rounded-lg transition-all duration-300 nav-link">Services</a>
                <a href="#contact" onclick="toggleMobileMenu()" class="block px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-[#4A90E2] rounded-lg transition-all duration-300 nav-link">Contact</a>
                <a href="#" onclick="openLoginModal(); toggleMobileMenu();"
                    class="complete-btn bg-[#4A90E2] text-white px-5 py-3 rounded-full transition-all text-center mt-4 flex items-center justify-center gap-2 nav-link shadow-md hover:shadow-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content - Starts right after header -->
    <main class="content-wrapper">
        <!-- Floating Announcement Icon -->
        <?php if ($hasAnnouncements): ?>
            <div id="floatingAnnouncement" class="floating-announcement-container fixed bottom-6 right-6 z-[9999]">
                <div class="relative">
                    <?php 
                    $hasHighPriority = false;
                    foreach ($announcements as $announcement) {
                        if ($announcement['priority'] == 'high') {
                            $hasHighPriority = true;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($hasHighPriority): ?>
                        <div class="absolute -top-1 -right-1 z-10">
                            <div class="relative">
                                <div class="animate-ping absolute inline-flex h-4 w-4 rounded-full bg-red-500 opacity-75"></div>
                                <div class="relative inline-flex rounded-full h-4 w-4 bg-red-600"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button onclick="scrollToAnnouncements()"
                        class="w-14 h-14 bg-gradient-to-br from-[#3a7bd5] to-[#2a6bc5] rounded-full shadow-xl hover:shadow-2xl transform hover:scale-110 transition-all duration-300 flex items-center justify-center group relative border-3 border-white ring-3 ring-blue-300 ring-opacity-50 animate-float">
                        
                        <div class="relative">
                            <i class="fa-solid fa-bullhorn text-xl text-white"></i>
                            
                            <?php if ($hasHighPriority): ?>
                                <i class="fas fa-exclamation text-xs text-red-300 absolute -top-1 -right-1 bg-white rounded-full p-0.5"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="absolute left-full ml-3 top-1/2 transform -translate-y-1/2 hidden group-hover:block min-w-max z-50">
                            <div class="bg-gray-900 text-white text-sm rounded-lg py-2 px-3 shadow-xl">
                                <span class="font-semibold">View Announcements</span>
                                <div class="text-xs text-gray-300 mt-1"><?= count($announcements) ?> new update(s)</div>
                            </div>
                            <div class="absolute right-full top-1/2 transform -translate-y-1/2">
                                <div class="w-0 h-0 border-t-4 border-b-4 border-l-4 border-transparent border-l-gray-900"></div>
                            </div>
                        </div>
                    </button>
                    
                    <div class="absolute -bottom-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center shadow-lg border-2 border-white">
                        <?= count($announcements) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Informative Barangay Health Center Display -->
        <section id="home" class="hero-gradient text-white hero-section">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center space-x-2 bg-white/10 px-4 py-2 rounded-full mb-6">
                        <i class="fas fa-medkit"></i>
                        <span class="text-sm font-medium">B0. Luz Health Center</span>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-6">
                        Barangay Luz Health Monitoring System
                    </h1>
                    
                    <p class="text-xl text-white mb-8 max-w-3xl mx-auto">
                        Your trusted partner in community healthcare. Providing accessible, quality healthcare services for every resident of Barangay Luz, Cebu City.
                    </p>
                    
                    <div class="flex flex-wrap justify-center gap-4 mb-12">
                        <button onclick="openLearnMoreModal()" 
                                class="btn-primary bg-white text-[#3a7bd5] hover:bg-blue-50">
                            <i class="fas fa-info-circle mr-2"></i>Learn More
                        </button>
                       
                    </div>
                </div>
                
                <!-- Quick Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 grid-spacing">
                    <div class="info-card card-hover">
                        <div class="flex items-start space-x-4">
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-map-marker-alt text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Location</h3>
                                <p class="text-gray-600">Barangay Luz, Cebu City</p>
                                <p class="text-gray-500 text-sm mt-1">Near Luz Elementary School</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card card-hover">
                        <div class="flex items-start space-x-4">
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-clock text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Operating Hours</h3>
                                <p class="text-gray-600">Mon-Fri: 8:00 AM - 5:00 PM</p>
                                <p class="text-gray-600">Saturday: 8:00 AM - 12:00 PM</p>
                                <p class="text-gray-500 text-sm mt-1">Emergency: 24/7</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card card-hover">
                        <div class="flex items-start space-x-4">
                            <div class="bg-blue-100 p-3 rounded-lg">
                                <i class="fas fa-phone text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2">Contact</h3>
                                <p class="text-gray-600">(032) 123-4567</p>
                                <p class="text-gray-600">0917-123-4567</p>
                                <p class="text-gray-500 text-sm mt-1">healthcenter@barangayluz.gov.ph</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center grid-spacing">
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="text-3xl font-bold mb-1">15+</div>
                        <div class="text-blue-200 text-sm">Medical Staff</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="text-3xl font-bold mb-1">5K+</div>
                        <div class="text-blue-200 text-sm">Residents Served</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="text-3xl font-bold mb-1">28</div>
                        <div class="text-blue-200 text-sm">Years Service</div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="text-3xl font-bold mb-1">200+</div>
                        <div class="text-blue-200 text-sm">Monthly Consultations</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: Health Services -->
        <section id="services" class="bg-white section-padding-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="section-title01 text-3xl md:text-4xl font-bold text-gray-900">
                        Our Health Services
                    </h2>
                    <p class="text-gray-600 max-w-3xl mx-auto text-lg text-lead">
                        Comprehensive healthcare services designed to meet the diverse needs of our community members
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 grid-spacing">
                    <!-- Service 1 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-stethoscope text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Primary Care Consultation</h4>
                        <p class="text-gray-600 text-lead">
                            Comprehensive medical check-ups, diagnosis, and treatment for common illnesses.
                        </p>
                    </div>
                    
                    <!-- Service 2 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-syringe text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Immunization Program</h4>
                        <p class="text-gray-600 text-lead">
                            Complete vaccination schedule for children, adults, and senior citizens.
                        </p>
                    </div>
                    
                    <!-- Service 3 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-user-injured text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Emergency Services</h4>
                        <p class="text-gray-600 text-lead">
                            24/7 emergency medical services with basic life support.
                        </p>
                    </div>
                    
                    <!-- Service 4 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-heartbeat text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Maternal & Child Health</h4>
                        <p class="text-gray-600 text-lead">
                            Prenatal and postnatal care, family planning services.
                        </p>
                    </div>
                    
                    <!-- Service 5 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-brain text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Mental Health Services</h4>
                        <p class="text-gray-600 text-lead">
                            Counseling services and mental health awareness programs.
                        </p>
                    </div>
                    
                    <!-- Service 6 -->
                    <div class="info-card text-center card-hover">
                        <div class="service-icon mx-auto">
                            <i class="fas fa-tablets text-2xl warm-blue-text"></i>
                        </div>
                        <h4 class="text-xl font-bold text-gray-900 mb-3">Pharmacy Services</h4>
                        <p class="text-gray-600 text-lead">
                            Basic medicines available at subsidized rates for residents.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 3: Announcements Display -->
        <section id="announcementsSection" class="warm-blue-light-bg text-white section-padding">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="section-title02 text-3xl md:text-4xl font-bold text-white">
                        Latest Announcements
                    </h2>
                    <p class="text-white max-w-3xl mx-auto text-lg">
                        Stay informed with important updates, health advisories, and community events
                    </p>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-16 text-center border border-white/20">
                        <div class="mb-6">
                            <i class="fas fa-bullhorn text-6xl text-white/50"></i>
                        </div>
                        <h3 class="text-2xl font-semibold text-white mb-3">No Announcements Yet</h3>
                        <p class="text-white max-w-md mx-auto text-lg">
                            Check back soon for important health updates and community announcements.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12 grid-spacing">
                        <?php foreach ($announcements as $index => $announcement): ?>
                            <div class="bg-white backdrop-blur-sm rounded-xl overflow-hidden border border-white/20 info-card card-hover">
                                <div class="p-6">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                <span class="announcement-priority bg-red-500/50 text-red-100 border border-red-400/50">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>High Priority
                                                </span>
                                            <?php elseif ($announcement['priority'] == 'medium'): ?>
                                                <span class="announcement-priority bg-yellow-500/50 text-yellow-100 border border-yellow-400/50">
                                                    <i class="fas fa-exclamation-circle mr-2"></i>Medium Priority
                                                </span>
                                            <?php else: ?>
                                                <span class="announcement-priority bg-blue-500/50 text-blue-100 border border-blue-400/50">
                                                    <i class="fas fa-info-circle mr-2"></i>Announcement
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-blue-200">
                                            <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-white mb-3">
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h3>
                                    
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($announcement['title']) ?>"
                                                 class="responsive-img h-48 object-cover">
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-blue-100 whitespace-pre-line mb-4 text-lead">
                                        <?= nl2br(htmlspecialchars(substr($announcement['message'], 0, 150))) ?>...
                                    </div>

                                    <button onclick="openAnnouncementModal(<?= $index ?>)"
                                            class="text-white hover:text-blue-200 font-semibold flex items-center text-sm">
                                        Read Full Announcement
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="text-center">
                        <button onclick="openAnnouncementsModal()"
                                class="btn-primary bg-white text-[#3a7bd5] hover:bg-blue-50">
                            <i class="fas fa-newspaper mr-3"></i>
                            View All Announcements
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- SECTION 4: Testimonials -->
        <section id="about" class="bg-white section-padding">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="section-title text-3xl md:text-4xl font-bold text-gray-900">
                        What Our Community Says
                    </h2>
                    <p class="text-gray-600 max-w-3xl mx-auto text-lg text-lead">
                        Hear from our dedicated healthcare providers and community members
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 grid-spacing">
                    <!-- Testimonial 1 -->
                    <div class="info-card card-hover">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                <i class="fas fa-user-md text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Dr. Maria Santos</h4>
                                <p class="text-gray-600 text-sm">Barangay Health Officer</p>
                            </div>
                        </div>
                        <p class="text-gray-700 text-lead">
                            "Our health center is committed to providing accessible and quality healthcare to every resident."
                        </p>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="info-card card-hover">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                <i class="fas fa-user-tie text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Capt. Juan Dela Cruz</h4>
                                <p class="text-gray-600 text-sm">Barangay Captain</p>
                            </div>
                        </div>
                        <p class="text-gray-700 text-lead">
                            "The health monitoring system has transformed how we manage community health needs."
                        </p>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="info-card card-hover">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                <i class="fas fa-user-nurse text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Nurse Lisa Mendoza</h4>
                                <p class="text-gray-600 text-sm">Head Nurse</p>
                            </div>
                        </div>
                        <p class="text-gray-700 text-lead">
                            "The digital health records system has made our work more efficient and focused."
                        </p>
                    </div>
                </div>
            </div>
        </section>

       

        <!-- Footer -->
        <footer class="warm-blue-bg text-white">
            <div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                    <!-- Column 1: About -->
                    <div>
                        <h3 class="text-xl font-bold mb-6">Barangay Luz Health Center</h3>
                        <p class="text-blue-100 mb-6 text-lead">
                            Providing quality healthcare services to Barangay Luz residents with compassion and excellence.
                        </p>
                        <div class="flex space-x-4">
                            <a href="https://www.facebook.com/BarangayLuzCebuCity2023" target="_blank" 
                               class="bg-white/10 p-3 rounded-lg hover:bg-white/20 transition">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="bg-white/10 p-3 rounded-lg hover:bg-white/20 transition">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Column 2: Quick Links -->
                    <div>
                        <h3 class="text-xl font-bold mb-6">Quick Links</h3>
                        <ul class="space-y-3">
                            <li>
                                <a href="#home" class="text-blue-100 hover:text-white transition flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i> Home
                                </a>
                            </li>
                            <li>
                                <a href="#services" class="text-blue-100 hover:text-white transition flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i> Our Services
                                </a>
                            </li>
                            <li>
                                <a href="#announcementsSection" class="text-blue-100 hover:text-white transition flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i> Announcements
                                </a>
                            </li>
                            <li>
                                <a href="#about" class="text-blue-100 hover:text-white transition flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2"></i> About Us
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Column 3: Contact Info -->
                    <div>
                        <h3 class="text-xl font-bold mb-6">Contact Info</h3>
                        <ul class="space-y-3">
                            <li class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-3 text-blue-200"></i>
                                <span class="text-blue-100">Barangay Luz, Cebu City</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-phone mr-3 text-blue-200"></i>
                                <span class="text-blue-100">(032) 123-4567</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-envelope mr-3 text-blue-200"></i>
                                <span class="text-blue-100">healthcenter@barangayluz.gov.ph</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Column 4: Hours -->
                    <div>
                        <h3 class="text-xl font-bold mb-6">Operating Hours</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-blue-100">Monday - Friday</span>
                                <span class="text-white">8:00 AM - 5:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-100">Saturday</span>
                                <span class="text-white">8:00 AM - 12:00 PM</span>
                            </div>
                            <div class="pt-3 mt-3 border-t border-white/20">
                                <span class="text-blue-200 text-sm">Emergency services available 24/7</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-blue-400 mt-10 pt-8 flex flex-col md:flex-row justify-between items-center">
                    <div class="mb-4 md:mb-0">
                        <p class="text-blue-200">
                            &copy; <?= date('Y') ?> Barangay Luz Health Center. All rights reserved.
                        </p>
                    </div>
                    <div class="flex items-center space-x-6">
                        <a href="/privacy.php" class="text-blue-200 hover:text-white text-sm">Privacy Policy</a>
                        <a href="/terms.php" class="text-blue-200 hover:text-white text-sm">Terms of Service</a>
                        <span class="text-blue-200 text-sm">Version 1.0</span>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 hidden z-50 h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
        <div class="relative bg-white p-4 sm:p-6 rounded-lg shadow-lg w-full max-w-md mx-auto max-h-[90vh] overflow-y-auto modal-content login-modal-content">
            <!-- Close Button -->
            <button onclick="closeLoginModal()"
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
                <p class="text-sm text-center text-gray-600 max-w-md leading-relaxed text-lead">
                    Please log in with your authorized account to access health records and other health services.
                </p>
            </div>

            <!-- Login Form -->
            <form method="POST" action="/includes/auth/login.php" class="space-y-6">
                <input type="hidden" name="role" value="user">
                <div class="space-y-6 mx-4">
                    <!-- Username -->
                    <div>
                        <label for="login-username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="login-username" placeholder="Enter Username"
                            class="form-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1]" required />
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input id="login-password" name="password" type="password" placeholder="Password"
                                class="form-input w-full p-3 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1]" required />
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
                        <p class="text-lead">New residents need to register at the Barangay Health Center to obtain login credentials.</p>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Other Modals -->
    <div id="learnMoreModal" class="fixed inset-0 z-50 hidden bg-black/40 backdrop-blur-sm flex items-center justify-center px-4">
        <div class="relative w-full max-w-7xl h-[92vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <div class="sticky top-0 z-20 bg-white border-b border-blue-100 px-10 py-6 flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-bold warm-blue-text">
                        Community Health Essentials
                    </h2>
                    <p class="text-base text-gray-500 mt-1">
                        A complete guide to wellness, prevention, and safety
                    </p>
                </div>

                <button onclick="closeLearnMoreModal()"
                    class="w-12 h-12 flex items-center justify-center rounded-full
                           bg-blue-50 warm-blue-text hover:bg-blue-100 transition text-xl">
                    ✕
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-10 py-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-8">
                        <h3 class="flex items-center gap-4 text-2xl font-semibold warm-blue-text mb-6">
                            <span class="bg-blue-100 px-4 py-2 rounded-xl warm-blue-text">✔</span>
                            Daily Health Tips
                        </h3>
                        <ul class="space-y-4 text-gray-700 text-lg leading-relaxed text-lead">
                            <li>• Get 7–9 hours of quality sleep</li>
                            <li>• Drink at least 8 glasses of water</li>
                            <li>• Exercise for 30 minutes daily</li>
                            <li>• Eat fruits and vegetables daily</li>
                            <li>• Practice mindfulness or meditation</li>
                        </ul>
                    </div>

                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-8">
                        <h3 class="flex items-center gap-4 text-2xl font-semibold warm-blue-text mb-6">
                            <span class="bg-blue-100 px-4 py-2 rounded-xl">🩺</span>
                            Preventive Care
                        </h3>
                        <ul class="space-y-4 text-gray-700 text-lg text-lead">
                            <li>• Annual physical checkups</li>
                            <li>• Updated vaccinations</li>
                            <li>• Age-appropriate screenings</li>
                            <li>• Chronic condition monitoring</li>
                            <li>• Dental exams twice a year</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="announcementsModal" class="fixed inset-0 hidden z-50 bg-black/30">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <button onclick="closeAnnouncementsModal()"
                    class="absolute top-4 right-4 z-50 text-gray-500 hover:text-gray-700 bg-white rounded-full p-2 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="overflow-y-auto flex-1 p-8">
                    <div class="text-center mb-8">
                        <div class="flex items-center justify-center gap-3 mb-4">
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-bullhorn text-2xl text-blue-600"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900">All Announcements</h2>
                        </div>
                        <p class="text-gray-600 max-w-2xl mx-auto text-lead">
                            Stay updated with all important announcements from Barangay Luz Health Center
                        </p>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-bullhorn text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No announcements available at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($announcements as $index => $announcement): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow card-hover">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> High Priority
                                                </span>
                                            <?php elseif ($announcement['priority'] == 'medium'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> Medium Priority
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                    <i class="fas fa-info-circle mr-1"></i> Announcement
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-gray-900 mb-3">
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h3>
                                    
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($announcement['title']) ?>"
                                                 class="responsive-img h-64 object-cover">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-gray-700 whitespace-pre-line text-lead">
                                        <?= nl2br(htmlspecialchars($announcement['message'])) ?>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-alt mr-2"></i>
                                                <span>Posted: <?= date('F j, Y', strtotime($announcement['post_date'])) ?></span>
                                            </div>
                                            <?php if ($announcement['expiry_date']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-clock mr-2"></i>
                                                    <span>Valid until: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <div class="text-center">
                        <p class="text-gray-600 mb-4 text-lead">
                            For the latest updates, please check this section regularly or contact the Barangay Health Center.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('show');
        }

        // Modal functions
        function openLoginModal() {
            const modal = document.getElementById("loginModal");
            const modalContent = modal.querySelector('.modal-content');
            
            modal.classList.remove("hidden");
            modal.classList.add("flex");
            document.body.style.overflow = 'hidden';
            
            // Trigger animation
            setTimeout(() => {
                modalContent.classList.add('open');
            }, 10);
            
            // Set focus to username input for accessibility
            setTimeout(() => {
                document.getElementById('login-username').focus();
            }, 50);
        }

        function closeLoginModal() {
            const modal = document.getElementById("loginModal");
            const modalContent = modal.querySelector('.modal-content');
            
            modalContent.classList.remove('open');
            
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.classList.remove("flex");
                modal.classList.add("hidden");
                document.body.style.overflow = 'auto';
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

        // Function to scroll to announcements section
        function scrollToAnnouncements() {
            const announcementsSection = document.getElementById('announcementsSection');
            if (announcementsSection) {
                // Use scrollIntoView with offset
                const headerHeight = document.querySelector('.main-header').offsetHeight;
                const targetPosition = announcementsSection.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        }

        // Announcement Modal Functions
        function openAnnouncementsModal() {
            document.getElementById('announcementsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAnnouncementsModal() {
            document.getElementById('announcementsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openAnnouncementModal(index) {
            openAnnouncementsModal();
        }

        // Learn More Modal Functions
        function openLearnMoreModal() {
            document.getElementById('learnMoreModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeLearnMoreModal() {
            document.getElementById('learnMoreModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const announcementsModal = document.getElementById('announcementsModal');
            const learnMoreModal = document.getElementById('learnMoreModal');
            const loginModal = document.getElementById('loginModal');
            
            if (announcementsModal && !announcementsModal.classList.contains('hidden') && 
                event.target === announcementsModal) {
                closeAnnouncementsModal();
            }
            
            if (learnMoreModal && !learnMoreModal.classList.contains('hidden') && 
                event.target === learnMoreModal) {
                closeLearnMoreModal();
            }
            
            if (loginModal && !loginModal.classList.contains('hidden') && 
                event.target === loginModal) {
                closeLoginModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAnnouncementsModal();
                closeLearnMoreModal();
                closeLoginModal();
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    // Close mobile menu if open
                    const mobileMenu = document.getElementById('mobile-menu');
                    if (mobileMenu.classList.contains('show')) {
                        mobileMenu.classList.remove('show');
                    }
                    
                    // Calculate scroll position accounting for fixed header
                    const headerHeight = document.querySelector('.main-header').offsetHeight;
                    const targetPosition = targetElement.offsetTop - headerHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Form submission handlers
        document.getElementById('contactForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for your message! We will get back to you soon.');
            this.reset();
        });

        // Update active nav link on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            const headerHeight = document.querySelector('.main-header').offsetHeight;
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.scrollY >= (sectionTop - headerHeight - 50)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}` || 
                    (current === '' && link.getAttribute('href') === '#')) {
                    link.classList.add('active');
                }
            });
        });

        // Initialize modal animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for all login buttons
            document.querySelectorAll('[onclick*="openLoginModal"]').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openLoginModal();
                });
            });
            
            // Update scroll padding based on actual header height
            const headerHeight = document.querySelector('.main-header').offsetHeight;
            document.documentElement.style.scrollPaddingTop = headerHeight + 'px';
            
            // Set scroll margin for all sections
            document.querySelectorAll('section[id]').forEach(section => {
                section.style.scrollMarginTop = headerHeight + 'px';
            });
        });
    </script>
</body>
</html>