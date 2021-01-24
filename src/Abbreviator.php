<?php

namespace Dashifen\Abbreviator;

use Timber\Timber;
use Dashifen\Repository\RepositoryException;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Repositories\PostValidity;
use Dashifen\WPHandler\Traits\ActionAndNonceTrait;
use Dashifen\WPHandler\Traits\OptionsManagementTrait;
use Dashifen\WPHandler\Repositories\MenuItems\SubmenuItem;
use Dashifen\WPHandler\Handlers\Plugins\AbstractPluginHandler;

class Abbreviator extends AbstractPluginHandler
{
  use OptionsManagementTrait;
  use ActionAndNonceTrait;
  
  public const SLUG = 'abbreviator';
  
  /**
   * initialize
   *
   * Uses addAction and/or addFilter to attach protected methods of this object
   * to the WordPress ecosystem of action and filter hooks.
   *
   * @return void
   * @throws HandlerException
   */
  public function initialize(): void
  {
    if (!$this->isInitialized()) {
      $this->addFilter('timber/locations', 'addTwigLocation');
      $this->addAction('admin_menu', 'addAbbreviatorSettings');
      $this->addAction('admin_post_' . $this->getAction(), 'saveAbbreviatorSettings');
    }
  }
  
  /**
   * addTwigLocation
   *
   * Adds our /assets/twigs folder to the list of places where Timber will
   * look for template files.
   *
   * @param array $locations
   *
   * @return array
   */
  protected function addTwigLocation(array $locations): array
  {
    $locations[] = $this->getPluginDir() . '/assets/twigs/';
    return $locations;
  }
  
  /**
   * addAbbreviatorSettings
   *
   * Adds a submenu item to the Dashboard's Settings menu which allows us to
   * define our abbreviations and their meanings.
   *
   * @return void
   * @throws RepositoryException
   * @throws HandlerException
   */
  protected function addAbbreviatorSettings(): void
  {
    $submenuItemSettings = [
      'pageTitle' => 'Abbreviator',
      'capability' => $this->getCapabilityForAction('access'),
      'method' => 'showAbbreviatorSettings',
    ];
    
    $hook = $this->addSettingsPage(new SubmenuItem($this, $submenuItemSettings));
    $this->addAction('load-' . $hook, 'loadAbbreviatorSettings');
  }
  
  /**
   * loadAbbreviatorSettings
   *
   * Fires when the abbreviator settings page is loaded before content is shown
   * so we can prepare its display.
   *
   * @return void
   * @throws HandlerException
   */
  protected function loadAbbreviatorSettings(): void
  {
    $this->addAction('admin_enqueue_scripts', 'addAssets');
  }
  
  /**
   * addAssets
   *
   * Adds the assets for this plugin on the pages that requires them.
   *
   * @return void
   */
  protected function addAssets(): void
  {
    $this->enqueue('assets/abbreviator.min.css');
    $this->enqueue('assets/abbreviator.min.js');
  }
  
  /**
   * showAbbreviatorSettings
   *
   * Displays the abbreviator settings page.
   *
   * @return void
   */
  protected function showAbbreviatorSettings(): void
  {
    $context = [
      'nonce' => $this->getNonce(),
      'action' => $this->getAction(),
    ];
    
    Timber::render('abbreviator-settings.twig', $context);
  }
  
  /**
   * saveAbbreviatorSettings
   *
   * Saves the information posted to the server related to abbreviations and
   * their meanings on this site.
   *
   * @return void
   * @throws HandlerException
   * @throws RepositoryException
   * @throws TransformerException
   */
  protected function saveAbbreviatorSettings(): void
  {
    $postedData = $_POST;
    if ($this->isValidActionAndNonce()) {
      $postValidity = $this->validatePostedData($postedData);
      if ($postValidity->valid) {
        
        // if our posted data has been deemed valid, then we can save it in
        // the database.  to do so, we want to make a mapping of the database
        // that links abbreviations and meanings.  we already know the same
        // number is in each of the arrays we're using herein because,
        // otherwise, they wouldn't have been valid.
        
        $abbreviations = array_combine(
          $postedData['abbreviations'],
          $postedData['meanings']
        );
        
        $this->updateOption('abbreviations', $abbreviations);
      }
      
      set_transient($this->getTransient(), $postValidity, 300);
      wp_safe_redirect($_POST['_wp_http_referer']);
    }
  }
  
  /**
   * validatePostedData
   *
   * Returns a PostValidity object to inform the calling scope of the results
   * of the tests we perform on our posted data herein.
   *
   * @param array $postedData
   *
   * @return PostValidity
   * @throws RepositoryException
   */
  private function validatePostedData(array &$postedData): PostValidity
  {
    $problems = [];
    
    // the only thing we'll check for is a different number of abbreviations
    // and meanings.  we'll pull those data out of $_POST and remove blank
    // rows.  then, if the size of those arrays is different, we've found a
    // problem.
  
    $cleaner = fn($array) => array_filter(array_map('trim', $array));
    $postedData['meanings'] = $cleaner($postedData['meanings'] ?? []);
    $postedData['abbreviations'] = $cleaner($postedData['abbreviations'] ?? []);
    if (sizeof($postedData['abbreviations']) !== sizeof($postedData['meanings'])) {
      $problems[] = 'Please be sure to enter an equal number of abbreviations and meanings.';
    }
    
    return new PostValidity($problems);
  }
  
  /**
   * getTransient
   *
   * Returns the name of our post validity transient so that we don't spell
   * it wrong when it's used above.
   *
   * @return string
   */
  private function getTransient(): string
  {
    return $this->getOptionNamePrefix() . 'transient';
  }
  
  /**
   * getOptionNames
   *
   * Inherited from the OptionManagementTrait, this method returns a list of
   * option names managed by this object without their prefix; that's added
   * automatically when necessary.
   *
   * @return string[]
   */
  protected function getOptionNames(): array
  {
    return ['abbreviations'];
  }
  
  /**
   * getOptionNamePrefix
   *
   * Inherited from the OptionManagementTrait, this method returns the unique
   * prefix for the options managed by this plugin.
   *
   * @return string
   */
  public function getOptionNamePrefix(): string
  {
    return self::SLUG . '-';
  }
}
