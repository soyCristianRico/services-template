<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for server monitoring and security
    | checks. You can customize thresholds, notification settings, and
    | security scanning options.
    |
    */

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Disk Space Monitoring
        |--------------------------------------------------------------------------
        */
        'disk' => [
            'warning_threshold' => env('SERVER_MONITOR_DISK_WARNING', 80),
            'critical_threshold' => env('SERVER_MONITOR_DISK_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Memory Usage Monitoring
        |--------------------------------------------------------------------------
        */
        'memory' => [
            'warning_threshold' => env('SERVER_MONITOR_MEMORY_WARNING', 80),
            'critical_threshold' => env('SERVER_MONITOR_MEMORY_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | CPU Load Monitoring
        |--------------------------------------------------------------------------
        */
        'cpu' => [
            'warning_threshold' => env('SERVER_MONITOR_CPU_WARNING', 70),
            'critical_threshold' => env('SERVER_MONITOR_CPU_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Swap Usage Monitoring
        |--------------------------------------------------------------------------
        |
        | Smart swap monitoring that considers both swap percentage and available RAM.
        |
        | The system uses intelligent logic to avoid false positives:
        | - Normal swap usage (0-60%) with plenty of available RAM is considered OK
        | - Only alerts when swap usage indicates real memory pressure
        | - Memory pressure is detected when less than 15% RAM is available
        |
        | Thresholds work as follows:
        | - WARNING: threshold% swap usage WITH memory pressure, OR >60% swap usage
        | - CRITICAL: threshold% swap usage WITH memory pressure, OR >80% swap usage
        |
        */
        'swap' => [
            'warning_threshold' => env('SERVER_MONITOR_SWAP_WARNING', 20),
            'critical_threshold' => env('SERVER_MONITOR_SWAP_CRITICAL', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        /*
        |--------------------------------------------------------------------------
        | Developer Email
        |--------------------------------------------------------------------------
        |
        | Who gets the alerts. This is the technical contact of the site, not its
        | owner: a full disk or a modified crontab means nothing to the client and
        | everything to whoever maintains the server.
        |
        | Accepts several addresses separated by commas:
        |
        |     DEVELOPER_EMAIL="tu@agencia.com,guardia@agencia.com"
        |
        | When empty, the package falls back to looking up users holding the role
        | below — the original behaviour, which needs spatie/laravel-permission.
        | Setting this is the recommended path: it works on any app, with or
        | without a roles system, and does not depend on someone having created
        | the right user.
        |
        */
        'mail_to' => env('DEVELOPER_EMAIL'),

        /*
        |--------------------------------------------------------------------------
        | Admin Role
        |--------------------------------------------------------------------------
        |
        | Fallback recipients, used only when no developer email is configured.
        | Users with this role will receive email alerts when issues are detected.
        | Requires the user model to expose a `roles` relation.
        |
        */
        'admin_role' => env('SERVER_MONITOR_ADMIN_ROLE', 'admin'),

        /*
        |--------------------------------------------------------------------------
        | User Model
        |--------------------------------------------------------------------------
        |
        | The fully qualified class name of your User model. Only used by the
        | role fallback described above.
        |
        */
        'user_model' => env('SERVER_MONITOR_USER_MODEL', 'App\\Models\\User'),

        /*
        |--------------------------------------------------------------------------
        | Notification Channels
        |--------------------------------------------------------------------------
        |
        | Available channels: mail, slack, teams, etc.
        | Currently only mail is implemented.
        |
        */
        'channels' => ['mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Whitelisted Users
        |--------------------------------------------------------------------------
        |
        | System users that should be ignored during security scans.
        |
        */
        'whitelisted_users' => [
            'forge',
            'root',
            'www-data',
            'mysql',
            'redis',
            'nobody',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Directories
        |--------------------------------------------------------------------------
        |
        | User directories that should be ignored during security scans.
        |
        */
        'whitelisted_directories' => [
            '/home/forge',
            '/home/root',
            '/var/www',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Paths for Malware Scanning
        |--------------------------------------------------------------------------
        |
        | Directories to exclude from malware pattern scanning.
        |
        */
        'excluded_paths' => [
            'vendor',
            'node_modules',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
            '.git',
            'public/storage',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Security Files
        |--------------------------------------------------------------------------
        |
        | Files that may contain legitimate security-related patterns and should
        | be excluded from malware pattern detection.
        |
        */
        'whitelisted_security_files' => [
            // Add your security-related files here
            // Example: 'app/Services/Security/SecurityService.php',
            'src/Services/Security/SecurityScannerService.php',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Processes
        |--------------------------------------------------------------------------
        |
        | Process names that should be ignored during security scans.
        | These are legitimate system processes.
        |
        */
        'whitelisted_processes' => [
            'php-fpm',
            'php-fpm: master',
            'php-fpm: pool',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Vendor Paths
        |--------------------------------------------------------------------------
        |
        | Vendor directory patterns that are legitimate and should not trigger alerts.
        | These are common in framework packages.
        |
        */
        'excluded_vendor_patterns' => [
            '/Resources/assets',
            '/Resources/views',
            '/Resources/lang',
            '/tests/fixtures',
            '/tests/stubs',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted .htaccess Files
        |--------------------------------------------------------------------------
        |
        | Specific .htaccess file paths that are known to be legitimate.
        | These are relative to the project root.
        | Note: .htaccess files in storage/app/public with "Deny from all" are
        | automatically considered legitimate and don't need to be listed here.
        |
        | Add your project-specific .htaccess files here if needed.
        | Example: 'storage/app/public/.htaccess'
        |
        */
        'whitelisted_htaccess' => [
            // Add project-specific .htaccess paths here if needed
            // 'storage/app/public/.htaccess',
        ],

        /*
        |--------------------------------------------------------------------------
        | Enhanced Security Detection Options
        |--------------------------------------------------------------------------
        |
        | Enable/disable specific security detection features.
        |
        */
        'detections' => [
            'suspicious_processes' => env('SERVER_MONITOR_CHECK_PROCESSES', true),
            'suspicious_uploads' => env('SERVER_MONITOR_CHECK_UPLOADS', true),
            'suspicious_htaccess' => env('SERVER_MONITOR_CHECK_HTACCESS', true),
            'fake_image_files' => env('SERVER_MONITOR_CHECK_FAKE_IMAGES', true),
            'file_integrity' => env('SERVER_MONITOR_CHECK_INTEGRITY', true),
            'malware_patterns' => env('SERVER_MONITOR_CHECK_MALWARE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Detection Frequency Strategy
        |--------------------------------------------------------------------------
        |
        | How often to run different security checks for optimal threat detection.
        |
        | CRITICAL (every 15 min via security:check):
        | - PHP processes in /tmp/
        | - PHP files in storage/uploads/
        | - Suspicious files in public/
        | - File integrity (index.php, artisan)
        | - Malicious .htaccess files
        | - Fake image files with PHP
        |
        | IMPORTANT (every 30 min):
        | - Malware pattern scanning
        | - Crontab modifications
        |
        | ROUTINE (daily):
        | - Failed login analysis
        | - Large file detection
        | - SSH key modifications
        | - System file changes
        |
        */
        'frequencies' => [
            'critical_attack_detection' => env('SERVER_MONITOR_FREQ_CRITICAL', 15),   // security:check
            'malware_patterns' => env('SERVER_MONITOR_FREQ_MALWARE', 30),           // malware:check
            'crontab_monitoring' => env('SERVER_MONITOR_FREQ_CRONTAB', 5),          // crontab:monitor
            'comprehensive_scan' => env('SERVER_MONITOR_FREQ_COMPREHENSIVE', 1440), // daily (1440 min)
        ],

        /*
        |--------------------------------------------------------------------------
        | Critical File Monitoring
        |--------------------------------------------------------------------------
        |
        | Laravel files that should be monitored for unauthorized changes.
        |
        */
        'critical_files' => [
            'public/index.php',
            'bootstrap/app.php',
            'artisan',
            'composer.json',
            'composer.lock',
            '.env',
        ],

        /*
        |--------------------------------------------------------------------------
        | Upload Directory Protection
        |--------------------------------------------------------------------------
        |
        | Directories where PHP files should never be allowed.
        |
        */
        'protected_upload_dirs' => [
            'storage/app/public',
            'public/uploads',
            'public/images',
            'public/files',
            'public/documents',
            'public/media',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Paths for Large Files Check
        |--------------------------------------------------------------------------
        |
        | Directories to exclude from large files scanning.
        | These paths will be ignored when checking for large recently created files.
        |
        | La galería son ficheros grandes subidos a mano desde el admin: es su
        | trabajo aparecer de golpe y pesar. Sin excluirla, cada subida llega como
        | alerta de seguridad.
        |
        */
        'excluded_large_files_paths' => [
            storage_path('app/public'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded System Files
        |--------------------------------------------------------------------------
        |
        | System files to exclude from modified system files check.
        | By default, files like /etc/passwd and /etc/shadow are monitored because
        | changes to them could indicate a security breach. However, on managed
        | servers (like Forge), these files change frequently during normal
        | operations (user creation, password changes, package installations).
        |
        | Add specific files here to exclude them from monitoring.
        | Example: '/etc/passwd', '/etc/passwd-', '/etc/shadow', '/etc/shadow-'
        |
        */
        'excluded_system_files' => [],

        /*
        |--------------------------------------------------------------------------
        | Scan Cache Duration
        |--------------------------------------------------------------------------
        |
        | Duration in minutes to cache scan results to avoid duplicate alerts.
        |
        */
        'scan_cache_duration' => env('SERVER_MONITOR_CACHE_DURATION', 60),

        /*
        |--------------------------------------------------------------------------
        | Alert Cooldown
        |--------------------------------------------------------------------------
        |
        | Minimum time in minutes between alerts for the same files.
        |
        */
        'alert_cooldown' => env('SERVER_MONITOR_ALERT_COOLDOWN', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how often various monitoring tasks should run.
    | These are suggestions - you can customize in your console routes.
    |
    | IMPORTANT: security:check now includes critical attack detections
    | and should run frequently (every 15 minutes) for fast threat response.
    |
    */
    'scheduling' => [
        'server_monitor' => 'everyTenMinutes',
        'security_check' => 'everyFifteenMinutes',        // CRITICAL: includes upload/process detection
        'malware_check' => 'everyThirtyMinutes',
        'crontab_monitor' => 'everyFiveMinutes',
        'comprehensive_check' => 'dailyAt("04:30")',      // Daily summary + non-critical checks
    ],
];
