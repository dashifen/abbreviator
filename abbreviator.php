<?php

/**
 * Plugin Name: Abbreviator
 * Plugin URI: https://github.com/dashifen/abbreviator
 * Description: A WordPress plugin that allows you to define abbreviations and their meaning which are automatically added to the content of a post when encountered for the first time.
 * Author: David Dashifen Kees
 * Author URI: http://dashifen.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Version: 1.0.0
 *
 * @noinspection PhpIncludeInspection
 */

use Dashifen\Abbreviator\Abbreviator;
use Dashifen\Exception\Exception;

$autoloader = file_exists(dirname(ABSPATH) . '/deps/vendor/autoload.php')
  ? dirname(ABSPATH) . '/deps/vendor/autoload.php'    // production location
  : 'vendor/autoload.php';                            // development location

require_once($autoloader);

try {
  (function () {
    
    // by instantiating our object in this anonymous function, it doesn't
    // get added to the WP global scope.  this should prevent any accidental
    // re-initializations of the object.
    
    $abbreviator = new Abbreviator();
    $abbreviator->initialize();
  })();
} catch (Exception $e) {
  Abbreviator::catcher($e);
}

