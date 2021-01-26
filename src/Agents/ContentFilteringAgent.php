<?php

namespace Dashifen\Abbreviator\Agents;

use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;

class ContentFilteringAgent extends AbstractPluginAgent
{
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
    $this->addFilter('the_content', 'addAbbrTags');
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
  
  
}
