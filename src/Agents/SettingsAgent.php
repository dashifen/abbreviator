<?php

namespace Dashifen\Abbreviator\Agents;

use Timber\Timber;
use Dashifen\Abbreviator\Abbreviator;
use Dashifen\Repository\RepositoryException;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Repositories\PostValidity;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;
use Dashifen\WPHandler\Traits\ActionAndNonceTrait;
use Dashifen\Abbreviator\Repositories\Abbreviation;
use Dashifen\Abbreviator\Services\AbbreviationCollection;
use Dashifen\WPHandler\Repositories\MenuItems\SubmenuItem;

/**
 * Class SettingsAgent
 *
 * @property Abbreviator $handler
 *
 * @package Dashifen\Abbreviator\Agents
 */
class SettingsAgent extends AbstractPluginAgent
{
  use ActionAndNonceTrait;
  
  /**
   * initialize
   *
   * Uses addAction and/or addFilter to attach protected methods of this object
   * to the WordPress ecosystem of action and filter hooks.
   *
   * @throws HandlerException
   */
  public function initialize(): void
  {
    if (!$this->isInitialized()) {
      $this->addAction('admin_menu', 'addAbbreviatorSettings');
      $this->addAction('admin_post_' . $this->getAction(), 'saveAbbreviatorSettings');
    }
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
      'pageTitle'  => 'Abbreviator',
      'capability' => $this->getCapabilityForAction('access'),
      'method'     => 'showAbbreviatorSettings',
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
    
    // the other thing we want to set up here is an admin notice to tell the
    // visitor about the validity of any data that they just posted to the
    // server.  that information has been stored in a transient so we can get
    // it out of the database and use it as follows.
    
    $transient = $this->getTransient();
    $priorPostValidity = get_transient($transient);
    if ($priorPostValidity instanceof PostValidity) {
      
      // now that we know we have information about a prior post to share with
      // the visitor, we'll set up an anonymous function that WP will call when
      // we hit the admin_notices hook.  in the function, we either show a
      // success or failure template based on the validity of the previously
      // posted data.
      
      $notifier = function () use ($priorPostValidity): void {
        $twig = $priorPostValidity->valid
          ? 'abbreviator-success.twig'
          : 'abbreviator-failure.twig';
        
        Timber::render($twig, $priorPostValidity->problems);
      };
      
      // finally, we use addAction to hook that anonymous function to the
      // admin_notices hook.  when WP gets to it, it'll call our method and
      // show the correct information to the visitor.  then, we can delete our
      // transient because we're done with it.  the information in the
      // $priorPostValidity object will remain in memory due to the closure.
      
      $this->addAction('admin_notices', $notifier);
      delete_transient($transient);
    }
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
      'nonce'  => $this->getNonce(),
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
        // the database.  to do so, we construct an collection of Abbreviation
        // objects and store that information instead of the "raw" data
        // that was posted here.
  
        $abbreviations = new AbbreviationCollection();
        foreach ($postedData['abbreviations'] as $i => $abbreviation) {
          $abbreviations[] = new Abbreviation($abbreviation, $postedData['meanings'][$i]);
        }
        
        $this->handler->updateOption('abbreviations', $abbreviations);
      }
      
      $timeLimit = self::isDebug() ? 3600 : 300;
      set_transient($this->getTransient(), $postValidity, $timeLimit);
      wp_safe_redirect($_POST['_wp_http_referer']);
    }
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
    return $this->handler->getOptionNamePrefix() . 'transient';
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
}
