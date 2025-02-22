<?php
/**
 * Plugin Name: Abbreviator
 * Plugin URI: https://github.com/dashifen/abbreviator
 * Description: A WordPress plugin that allows you to define abbreviations and their meaning which are automatically added to the content of a post when encountered for the first time.
 * Author: David Dashifen Kees
 * Author URI: http://dashifen.com
 * Version: 2.0.0
 */

namespace Dashifen\WordPress\Plugins;

use Dashifen\Exception\Exception;
use Dashifen\WordPress\Plugins\Abbreviator\Abbreviator;
use Dashifen\WordPress\Plugins\Abbreviator\Agents\SettingsAgent;
use Dashifen\WordPress\Plugins\Abbreviator\Agents\ContentFilteringAgent;
use Dashifen\WPHandler\Agents\Collection\Factory\AgentCollectionFactory;

if (!class_exists(Abbreviator::class)) {
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

