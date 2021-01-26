<?php

namespace Dashifen\Abbreviator;

use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Traits\OptionsManagementTrait;
use Dashifen\Abbreviator\Services\AbbreviationCollection;
use Dashifen\WPHandler\Handlers\Plugins\AbstractPluginHandler;

class Abbreviator extends AbstractPluginHandler
{
  use OptionsManagementTrait;
  
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
      $this->addAction('init', 'initializeAgents');
      $this->addFilter('timber/locations', 'addTwigLocation');
      $this->addAction('wp_ajax_get-abbreviations', 'ajaxGetAbbreviations');
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
   * ajaxGetAbbreviations
   *
   * Gathers abbreviations and returns them to the client.
   *
   * @return void
   * @throws HandlerException
   * @throws TransformerException
   */
  protected function ajaxGetAbbreviations(): void
  {
    wp_die(
      json_encode(
        $this->getAbbreviations()->getAbbreviationMeaningMap()
      )
    );
  }
  
  /**
   * getAbbreviations
   *
   * A convenience method that helps to type hint the otherwise mixed type
   * information that is returned from our getOption method.
   *
   * @return AbbreviationCollection
   * @throws HandlerException
   * @throws TransformerException
   */
  public function getAbbreviations(): AbbreviationCollection
  {
    return $this->getOption('abbreviations', new AbbreviationCollection());
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
