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
      
      // we do this at priority level 50 just because it's likely at or
      // toward the end of the queue of filters on the content.
      
      $this->addFilter('the_content', 'addAbbrTags', 50);
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
    self::debug($postedData, true);
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
   * addAbbrTags
   *
   * Analyzes a post's content and adds any <abbr> tags that match the
   * information managed by this object.
   *
   * @param string $content
   *
   * @return string
   * @throws HandlerException
   * @throws TransformerException
   */
  protected function addAbbrTags(string $content): string
  {
    $abbrMeaningMap = $this->getOption('abbreviations', []);
    
    if (sizeof($abbrMeaningMap) > 0) {
      foreach (array_keys($abbrMeaningMap) as $abbr) {
        $content = $this->replaceAbbreviation($content, $abbr, $abbrMeaningMap[$abbr]);
      }
    }
    
    return $content;
  }
  
  /**
   * replaceAbbreviation
   *
   * Makes the necessary replacements within the content taking care to avoid
   * making any changes within HTML tags.
   *
   * @param string $content
   * @param string $abbr
   * @param string $title
   *
   * @return string
   */
  private function replaceAbbreviation(string $content, string $abbr, string $title): string
  {
    $replacement = sprintf('<abbr title="%s">%s</abbr>', $title, $abbr);
  
    // to make our replacements, we're going to split our $content based on
    // $abbr.  but, we want to make sure we do so at word boundaries.  so,
    // we use preg_split rather than explode.  then, as we loop, we re-
    // construct our $content from each of the parts.
  
    $reconstruction = "";
    $parts = preg_split("/\b$abbr\b/", $content);
    foreach ($parts as $i => $part) {
      $reconstruction .= $part;
    
      // as long as there is a next part, we need to add either our
      // $replacement or $abbr back onto our $reconstruction.  but, we
      // only want to add $replacement if we're not within a HTML tag.
      // the method below will tell us how to proceed.
    
      if (isset($parts[$i+1])) {
        $reconstruction .= $this->shouldAddReplacement($reconstruction)
          ? $replacement
          : $abbr;
      }
    }
  
    return $reconstruction;
  }
  
  /**
   * shouldAddReplacement
   *
   * Returns true if we should add our replacement to the reconstructed
   * content in the prior method and false otherwise.
   *
   * @param string $reconstruction
   *
   * @return bool
   */
  private function shouldAddReplacement (string $reconstruction): bool
  {
    // we do want to replace when we're not in the middle of a HTML tag
    // within our $reconstruction.  we can tell if that's the case by
    // counting the number of < and > characters.  if those counts are
    // equal, then every tag has been both opened and closed and,
    // therefore, we're not within a tag.
    
    $openers = substr_count($reconstruction, '<');
    $closers = substr_count($reconstruction, '>');
    return $openers === $closers;
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
}
