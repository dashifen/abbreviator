<?php

namespace Dashifen\WordPress\Plugins\Abbreviator;

use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Handlers\Plugins\AbstractPluginHandler;

class Abbreviator extends AbstractPluginHandler
{
  /**
   * Uses addAction and/or addFilter to attach protected methods of this object
   * to the ecosystem of WordPress action and filter hooks.
   *
   * @return void
   * @throws HandlerException
   */
  public function initialize(): void
  {
    $this->addAction('enqueue_block_editor_assets', 'enqueueAssets');
  }
  
  /**
   * Enqueues the assets for this plugin in the block editor.
   *
   * @return void
   */
  protected function enqueueAssets(): void
  {
    $assets = require $this->pluginDir . '/build/abbreviator.asset.php';
    $this->enqueue('build/abbreviator.js', $assets['dependencies']);
  }
}
