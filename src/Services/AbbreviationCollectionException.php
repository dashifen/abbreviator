<?php

namespace Dashifen\WordPress\Plugins\Abbreviator\Services;

use Dashifen\Exception\Exception;

/**
 * Class AbbreviationCollectionException
 *
 * @package Dashifen\WordPress\Plugins\Abbreviator\Services
 */
class AbbreviationCollectionException extends Exception
{
  public const NOT_AN_ABBREVIATION = 1;
}
