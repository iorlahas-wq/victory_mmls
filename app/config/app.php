<?php
declare(strict_types=1);

/*
 * ============================================================
 * APPLICATION BOOTSTRAP
 * ============================================================
 */

require_once __DIR__ . '/env.php';


/*
 * ------------------------------------------------------------
 * Timezone
 * ------------------------------------------------------------
 */

date_default_timezone_set('Africa/Lagos');


/*
 * ------------------------------------------------------------
 * Session
 * ------------------------------------------------------------
 */

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_name(
        env(
            'SESSION_NAME',
            'victory_mmls_session'
        )
    );

    session_start();
}


/*
 * ------------------------------------------------------------
 * Application Constants
 * ------------------------------------------------------------
 */

define(
    'APP_NAME',
    env(
        'APP_NAME',
        "Ebenezer's Kitchen Multimedia Learning System"
    )
);

define(
    'APP_ENV',
    env('APP_ENV', 'local')
);

define(
    'APP_DEBUG',
    filter_var(
        env('APP_DEBUG', 'false'),
        FILTER_VALIDATE_BOOLEAN
    )
);

define(
    'APP_URL',
    rtrim(
        env(
            'APP_URL',
            'http://localhost/Victory_MMLS'
        ),
        '/'
    )
);


/*
 * ------------------------------------------------------------
 * Application Paths
 * ------------------------------------------------------------
 */

define(
    'BASE_PATH',
    dirname(__DIR__, 2)
);

define(
    'UPLOAD_PATH',
    BASE_PATH . '/uploads'
);


/*
 * ------------------------------------------------------------
 * Core Application Files
 * ------------------------------------------------------------
 */

require_once BASE_PATH . '/app/config/database.php';

require_once BASE_PATH . '/app/includes/functions.php';