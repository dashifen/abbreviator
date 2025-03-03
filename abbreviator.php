<?php
/**
 * Plugin Name: Abbreviator
 * Plugin URI: https://github.com/dashifen/abbreviator
 * Description: A WordPress plugin that allows you to add inline <abbr> tags to paragraph blocks.
 * Author: David Dashifen Kees
 * Author URI: http://dashifen.com
 * Version: 3.0.0
 */

namespace Dashifen\WordPress\Plugin;

use Dashifen\Exception\Exception;
use Dashifen\WordPress\Plugins\Abbreviator\Abbreviator;

if (!class_exists(Abbreviator::class)) {
  require_once __DIR__ . '/vendor/autoload.php';
}

try {
  $abbreviator = new Abbreviator();
  $abbreviator->initialize();
} catch (Exception $e) {
  Abbreviator::catcher($e);
}
