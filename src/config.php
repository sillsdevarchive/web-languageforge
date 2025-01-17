<?php

/*---------------------------------------------------------------
 * Application Environment
 *---------------------------------------------------------------
 *
 * You can load different configurations depending on your
 * current environment. Setting the environment also influences
 * things like logging and error reporting.
 *
 * This can be set to anything, but default usage is:
 *
 *     development
 *     testing
 *     production
 *
 * NOTE: If you change these, also change the error_reporting() code in index.php
 *
 */

if (! defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

if (file_exists('userConfig.php')) {
    require_once('userConfig.php');
}

/*---------------------------------------------------------------
 * General xForge Configuration
 *---------------------------------------------------------------
 */

if (! defined('SF_DATABASE')) {
    define('SF_DATABASE', 'scriptureforge');
}

if (! defined('MONGODB_CONN')) {
    define('MONGODB_CONN', 'mongodb://localhost:27017');
}

if (! defined('USE_MINIFIED_JS')) {
    if (defined('ENVIRONMENT') and ENVIRONMENT === 'development') {
        define('USE_MINIFIED_JS', false);
    } else {
        define('USE_MINIFIED_JS', true);
    }
}

if (! defined('USE_CDN')) {
    if (defined('ENVIRONMENT') and ENVIRONMENT === 'development') {
        define('USE_CDN', false);
    } else {
        define('USE_CDN', true);
    }
}

if (! defined('REMEMBER_ME_SECRET')) {
    define('REMEMBER_ME_SECRET', 'not_a_secret');
}

if (! defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', 'googleClientId');
}

if (! defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', 'googleClientSecret');
}

if (! defined('GATHERWORDS_CLIENT_ID')) {
    define('GATHERWORDS_CLIENT_ID', 'gatherWordsClientId');
}

if (! defined('PARATEXT_CLIENT_ID')) {
    define('PARATEXT_CLIENT_ID', 'paratextClientId');
}

if (! defined('PARATEXT_API_TOKEN')) {
    define('PARATEXT_API_TOKEN', 'paratextApiToken');
}

define('NG_BASE_FOLDER', 'angular-app/');
define('BCRYPT_COST', 7);

if (! defined('JWT_KEY')) {
    define('JWT_KEY', 'this_is_not_a_secret_dev_only');
}

if (! defined('SR_TRANSLATE_FOLDER')) {
    define('SR_TRANSLATE_FOLDER', '/var/lib/scriptureforge/translate/sendreceive');
}

if (!defined('BUGSNAG_API_KEY')) {
    define('BUGSNAG_API_KEY', 'missing-bugsnag-api-key');
}

if (!defined('BUGSNAG_NOTIFY_RELEASE_STAGES')) {
    // The real values will be set by gulp
    define('BUGSNAG_NOTIFY_RELEASE_STAGES', 'not-set' );
}
