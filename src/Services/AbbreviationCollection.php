<?php

namespace Dashifen\Abbreviator\Services;

use Dashifen\Collection\AbstractCollection;
use Dashifen\Abbreviator\Agents\SettingsAgent;
use Dashifen\Abbreviator\Repositories\Abbreviation;

/**
 * Class AbbreviationCollection
 *
 * @property Abbreviation[] $collection
 *
 * @package Dashifen\Abbreviator\Services
 */
class AbbreviationCollection extends AbstractCollection
  implements AbbreviationCollectionInterface
{
  /**
   * getAbbreviationMeaningMap
   *
   * Returns a map of abbreviations to their meanings, e.g. for the settings
   * page when editing prior entries.
   *
   * @return array
   */
  public function getAbbreviationMeaningMap(): array
  {
    foreach ($this->collection as $abbreviation) {
      $map[$abbreviation->abbreviation] = $abbreviation->meaning;
    }
    
    return $map ?? [];
  }
  
  /**
   * getAbbreviations
   *
   * Returns an array of just the abbreviations so that we can find and
   * replace them within a post's content.
   *
   * @return array
   */
  public function getAbbreviations(): array
  {
    return array_map(fn($abbr) => $abbr->abbreviation, $this->collection);
  }
  
  /**
   * getTags
   *
   * Returns an array of just the tags so that we can use them to replace the
   * raw abbreviations within a post's content.
   *
   * @return array
   */
  public function getTags(): array
  {
    return array_map(fn($abbr) => $abbr->tag, $this->collection);
  }
  
  /**
   * offsetSet
   *
   * Adds the value to the collection at the specified offset.  Note: we can't
   * change the method's signature here, so we can't type hint our parameters.
   * Instead, we let the phpDocBlock help our IDEs in understanding what to
   * expect and throw exceptions if someone tries to use this object in a way
   * that it does not support.
   *
   * @param int          $offset
   * @param Abbreviation $value
   *
   * @return void
   * @throws AbbreviationCollectionException
   * @noinspection PhpMissingParamTypeInspection
   */
  public function offsetSet($offset, $value): void
  {
    if (!($value instanceof Abbreviation)) {
      throw new AbbreviationCollectionException(
        'AbbreviationCollection values must be instances of the Abbreviation object',
        AbbreviationCollectionException::NOT_AN_ABBREVIATION
      );
    }
    
    parent::offsetSet($offset, $value);
  }
}
