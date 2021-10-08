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
 * Version: 2.0.0
 *
 * @noinspection PhpIncludeInspection
 */

use Dashifen\Exception\Exception;
use Dashifen\Abbreviator\Abbreviator;
use Dashifen\Abbreviator\Agents\SettingsAgent;
use Dashifen\Abbreviator\Agents\ContentFilteringAgent;
use Dashifen\WPHandler\Agents\Collection\Factory\AgentCollectionFactory;

if (!class_exists('Dashifen\Abbreviator\Abbreviator')) {
  require_once 'vendor/autoload.php';
}

try {
  (function () {
    $abbreviator = new Abbreviator();
    $acf = new AgentCollectionFactory();
    
    // we only want to filter content on the public side, and we only need
    // the settings agent on the admin side.  therefore, we register only
    // the agent we need based on the current request as follows, and this
    // prevents any unnecessary hook attachments.
    
    $agent = !is_admin()
      ? ContentFilteringAgent::class
      : SettingsAgent::class;
    
    $acf->registerAgent($agent);
    $abbreviator->setAgentCollection($acf);
    $abbreviator->initialize();
  })();
} catch (Exception $e) {
  Abbreviator::catcher($e);
}

