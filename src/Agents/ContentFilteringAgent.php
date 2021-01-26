<?php

namespace Dashifen\Abbreviator\Agents;

use Dashifen\Abbreviator\Abbreviator;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;
use Dashifen\WPHandler\Traits\PostMetaManagementTrait;

/**
 * Class ContentFilteringAgent
 *
 * @property Abbreviator $handler
 *
 * @package Dashifen\Abbreviator\Agents
 */
class ContentFilteringAgent extends AbstractPluginAgent
{
  use PostMetaManagementTrait;
  
  private int $postId;
  
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
    $this->postId = get_the_ID();
    $abbreviationCollection = $this->handler->getAbbreviations();
    $abbreviations = $abbreviationCollection->getAbbreviations();
    
    $hasAbbreviations = !$this->shouldRecheckPost()
      ? $this->getPostMeta($this->postId, 'post-has-abbreviations', false)
      : $this->postHasAbbreviations($content, $abbreviations);
    
    if ($hasAbbreviations) {
      $tags = $abbreviationCollection->getTags();
      $content = str_replace($abbreviations, $tags, $content);
    }
    
    return $content;
  }
  
  /**
   * shouldRecheckPost
   *
   * Returns true if it's time to recheck this post for abbreviations and
   * false if it has been previously checked and remains unchanged.
   *
   * @return bool
   * @throws HandlerException
   */
  private function shouldRecheckPost(): bool
  {
    $lastModifiedUTC = get_post_modified_time('U', true);
    $lastAbbreviationCheck = $this->getPostMeta($this->postId, 'last-abbreviation-check', 0);
    return $lastModifiedUTC > $lastAbbreviationCheck;
  }
  
  /**
   * postHasAbbreviations
   *
   * Returns true if any of our abbreviations can be found within the
   * content.
   *
   * @param string $content
   * @param array  $abbreviations
   *
   * @return bool
   * @throws HandlerException
   */
  private function postHasAbbreviations(string $content, array $abbreviations): bool
  {
    // first, we're about to check this post for abbreviations.  therefore, we
    // want to record the current time in UTC so that we can skip this step
    // again in the future, or at least until the post gets updated and might,
    // therefore, have new abbreviations within it.
    
    $this->updatePostMeta($this->postId, 'last-abbreviation-check', gmdate('U'));
    
    // then, to see if this content has any of our abbreviations in it, we'll
    // create a regex that will match any of them and then test content with
    // it.  for example, if FUBAR and SNAFU are our abbreviations, the pattern
    // we create here is /FUBAR|SNAFU/ and that'll match either or both of
    // those anywhere in our content.
    
    $regex = '/' . join('|', array_keys($abbreviations)) . '/';
    return preg_match($regex, $content);
  }
  
  /**
   * getPostMetaNames
   *
   * Inherited from the PostMetaManagementTrait, this method returns an array
   * of meta keys that define the additional data that this object managers
   * about posts.
   *
   * @return array
   */
  protected function getPostMetaNames(): array
  {
    return ['post-has-abbreviations', 'last-abbreviation-check'];
  }
  
  /**
   * getPostMetaNamePrefix
   *
   * Inherited from the PostMetaManagementTrait, this method returns a string
   * that is prepended to the name of post meta keys internal to the other
   * methods of that trait to uniquely identify this object's metadata.
   *
   * @return string
   */
  public function getPostMetaNamePrefix(): string
  {
    return $this->handler->getOptionNamePrefix();
  }
}
