<?php

namespace Dashifen\WordPress\Plugins\Abbreviator\Repositories;

use Dashifen\Repository\Repository;
use Dashifen\Repository\RepositoryException;

/**
 * Class Abbreviation
 *
 * @property-read string $tag
 * @property-read string $abbreviation
 * @property-read string $meaning
 *
 * @package Dashifen\WordPress\Plugins\Abbreviator\Repositories
 */
class Abbreviation extends Repository
{
  protected string $abbreviation;
  protected string $meaning;
  
  /**
   * Abbreviation constructor.
   *
   * @param string $abbreviation
   * @param string $meaning
   *
   * @throws RepositoryException
   */
  public function __construct(string $abbreviation, string $meaning)
  {
    parent::__construct(
      [
        'abbreviation' => $abbreviation,
        'meaning' => $meaning,
      ]
    );
  }
  
  /**
   * __get
   *
   * This overrides our parent's method in order to return the value for our
   * $tag pseudo-property.  If the tag property is not what's being requested,
   * we just pass control to our parent.
   *
   * @param string $property
   *
   * @return string
   * @throws RepositoryException
   */
  public function __get(string $property): string
  {
    return $property === 'tag'
      ? sprintf('<abbr title="%s">%s</abbr>', $this->meaning, $this->abbreviation)
      : parent::__get($property);
  }
  
  /**
   * setAbbreviation
   *
   * Sets the abbreviation property.
   *
   * @param string $abbreviation
   *
   * @return void
   */
  protected function setAbbreviation(string $abbreviation): void
  {
    $this->abbreviation = $abbreviation;
  }
  
  /**
   * setMeaning
   *
   * Sets the meaning property
   *
   * @param string $meaning
   *
   * @return void
   */
  protected function setMeaning(string $meaning): void
  {
    $this->meaning = $meaning;
  }
}
