<?php


namespace Dashifen\Abbreviator\Services;


use Dashifen\Collection\CollectionInterface;

interface AbbreviationCollectionInterface extends CollectionInterface
{
  /**
   * getAbbreviationMeaningMap
   *
   * Returns a map of abbreviations to their meanings, e.g. for the settings
   * page when editing prior entries.
   *
   * @return array
   */
  public function getAbbreviationMeaningMap(): array;
  
  /**
   * getAbbreviations
   *
   * Returns an array of just the abbreviations so that we can find and
   * replace them within a post's content.
   *
   * @return array
   */
  public function getAbbreviations(): array;
  
  /**
   * getTags
   *
   * Returns an array of just the tags so that we can use them to replace the
   * raw abbreviations within a post's content.
   *
   * @return array
   */
  public function getTags(): array;
}
