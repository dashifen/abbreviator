<?php

namespace Dashifen\Abbreviator\Agents;

use Dashifen\Abbreviator\Abbreviator;
use Dashifen\Transformer\TransformerException;
use Dashifen\WPHandler\Handlers\HandlerException;
use Dashifen\WPHandler\Agents\AbstractPluginAgent;
use Dashifen\Abbreviator\Repositories\Abbreviation;
use Dashifen\WPHandler\Traits\PostMetaManagementTrait;
use Dashifen\Abbreviator\Services\AbbreviationCollection;

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
  
  private AbbreviationCollection $abbreviations;
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
    $this->abbreviations = $this->handler->getAbbreviations();
    
    // before we do a bunch of work to see if there are abbreviations in
    // this post, we'll see if we've already done it.  if we don't need to
    // recheck the post, then we'll just grab our flag out of the database.
    // as long as we get a false value back from the shouldRecheckPost method,
    // we know that we can rely on our previously saved metadata within this
    // one.
    
    $hasAbbreviations = ($useMetadata = !$this->shouldRecheckPost())
      ? $this->getPostMeta($this->postId, 'post-has-abbreviations', false)
      : $this->postHasAbbreviations($content);
    
    if ($hasAbbreviations) {
      
      // if we're using our metadata, then there should be a record of our
      // prior replacement in the database.  this uses a bit more database
      // space instead of requiring that we do a lot of string manipulations.
      // we haven't benchmarked it, but we suspect that a single additional
      // DB selection is more efficient that regular expression and string
      // manipulation.
      
      $replacedContent = $useMetadata
        ? $this->getPostMeta($this->postId, 'replaced-content')
        : '';
      
      if (empty($replacedContent)) {
        
        // but, if we're here, then we need to get replacement content.  either
        // we've never done so for this post or the post changed and we can no
        // longer rely on the prior metadata.  regardless, we get the replaced
        // content and then store it in the database for next time.
        
        $replacedContent = $this->produceReplacedContent($content);
        $this->updatePostMeta($this->postId, 'replaced-content', $replacedContent);
      }
      
      $content = $replacedContent;
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
    // there are two ways that a recheck is triggered:  the post has been
    // updated since the last check or one of our watched files was.  either of
    // these might indicate an expectation of the display changing, so we'll
    // make sure it might change as follows.  since the first criteria involves
    // less work than the second, we'll test that one first.
    
    $lastModifiedUTC = get_post_modified_time('U', true);
    $lastAbbreviationCheck = $this->getPostMeta($this->postId, 'last-abbreviation-check', 0);
    if ($lastModifiedUTC > $lastAbbreviationCheck) {
      return true;
    }
    
    // still here?  then we want to check our watched files.  by default, we
    // watch the theme's stylesheet.  but, we offer a filter so that users of
    // this plugin can change this list as they might need to.
    
    $watchedFiles = [$this->getStylesheetDir() . '/style.css'];
    $watchedFiles = apply_filters('abbreviator-watched-files', $watchedFiles);
    
    foreach ($watchedFiles as $file) {
      if (is_file($file) & filemtime($file) > $lastAbbreviationCheck) {
        
        // to try and save time, the first file that triggers a need to perform
        // our re-check ends this loop.  that way, we don't keep looking at
        // more files once we no longer need to.
        
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * postHasAbbreviations
   *
   * Returns true if any of our abbreviations can be found within the
   * content.
   *
   * @param string $content
   *
   * @return bool
   * @throws HandlerException
   */
  private function postHasAbbreviations(string $content): bool
  {
    // first, we're about to check this post for abbreviations.  therefore, we
    // want to record the current time in UTC so that we can skip this step
    // again in the future, or at least until the post gets updated and might,
    // therefore, have new abbreviations within it.
    
    $this->updatePostMeta($this->postId, 'last-abbreviation-check', gmdate('U'));
    
    // then, to see if this content has any of our abbreviations in it, we'll
    // create a regex that will match any of them and then test content with
    // it.  for example, if FUBAR and SNAFU are our abbreviations, the pattern
    // we create here is /\bFUBAR\b|\bSNAFU\b/ and that'll match either or both
    // of those anywhere in our content.
    
    $regex = '/\b' . join('\b|\b', $this->abbreviations->getAbbreviations()) . '\b/';
    $matchCount = preg_match_all($regex, $content);
    if ($matchCount > 1) {
      
      // if we found matches, we want to see if any of them are within HTML
      // tags.  we do this by stripping out the tags and checking again.  if
      // the same number of matches are listed before and after we remove tags,
      // we're good to go.  if not, we simply identify this in the post meta
      // so we can react to this fact again later.
      
      $postHasAbbreviationsInTags = $matchCount !== preg_match_all($regex, strip_tags($content));
      $this->updatePostMeta($this->postId, 'post-has-abbrs-in-tags', $postHasAbbreviationsInTags);
    }
    
    // before we return, we'll record the results of our regex test.  this
    // keeps us from having to do it again until the next time the post is
    // updated saving us some time.
    
    $hasAbbreviations = $matchCount >= 1;
    $this->updatePostMeta($this->postId, 'post-has-abbreviations', $hasAbbreviations);
    return $hasAbbreviations;
  }
  
  /**
   * produceReplacedContent
   *
   * This method determines how we want to proceed with respect to our
   * replacements, performs them, and returns the results.
   *
   * @param string $content
   *
   * @return string
   * @throws HandlerException
   */
  private function produceReplacedContent(string $content): string
  {
    // there are two options for our replacement:  when there are and when
    // there aren't abbreviations in HTML tags (e.g. in an image's alt
    // attribute).
  
    return $this->getPostMeta($this->postId, 'post-has-abbrs-in-tags')
      ? $this->makeReplacementsAroundTags($content)
      : $this->makeReplacements($content);
  }
  
  /**
   * makeReplacementsAroundTags
   *
   * If we identify an abbreviation within an HTML tag, this method does its
   * best to replace the ones that are outside tags.
   *
   * @param string $content
   *
   * @return string
   * @throws HandlerException
   */
  private function makeReplacementsAroundTags(string $content): string
  {
    // it's possible that we can't do this replacement, so before we start to
    // try, we'll check.  as long as we think we're good to go, we'll give it a
    // shot.
    
    if ($couldReplace = $this->canReplace($content)) {
      foreach ($this->abbreviations as $abbreviation) {
        /** @var Abbreviation $abbreviation */
        
        $reconstruction = '';
        $parts = preg_split('/\b' . $abbreviation->abbreviation . '\b/', $content);
        foreach ($parts as $i => $part) {
          $reconstruction .= $part;
          
          // $parts is an array of the everything before and after the current
          // abbreviation.  we keep concatenating these parts back into the
          // reconstruction variable, but we now have to decide if we add the
          // abbreviation or its tag into that string to join this part and
          // the next one, assuming there is, in fact, a next part.
          
          if (isset($parts[$i + 1])) {
            $reconstruction .= $this->isWithinTag($reconstruction)
              ? $abbreviation->abbreviation
              : $abbreviation->tag;
          }
        }
        
        // now that we've reconstructed our content from the parts we split it
        // into, we copy that reconstruction into the original variable.  this
        // preps us to replace another abbreviation or to return our content
        // to the calling scope below.
        
        $content = $reconstruction;
      }
    }
    
    // before we're done, we'll set a flag related to whether or not we could
    // replace within this post's content.  notice that our Boolean stores
    // if we could replace, but our post meta is named as if we did not.  that
    // means we save the opposite of our variable.
    
    $this->updatePostMeta($this->postId, 'could-not-replace', !$couldReplace);
    return $content;
  }
  
  /**
   * canReplaceAroundTags
   *
   * Returns true if we can replace abbreviations around our tags or if the
   * content of this post prevents this work.
   *
   * @param $content
   *
   * @return bool
   */
  private function canReplace($content): bool
  {
    // to determine if it's possible for us to safely make replacements, we
    // count the < and > symbols.  as long as they're evenly matched, we can
    // proceed because we'll be able to use them to determine when we're within
    // a tag and when we're not.
    
    return substr_count($content, '<') === substr_count($content, '>');
  }
  
  /**
   * isWithinTag
   *
   * Returns true if our parameter string indicates that we've begun but not
   * yet completed a tag in that string.
   *
   * @param string $content
   *
   * @return bool
   */
  private function isWithinTag(string $content): bool
  {
    // in this context, an open tag is one that has a < symbol to begin it but
    // we haven't yet added a > to the content to finish it.  we can tell this
    // if the number of < symbols is not the same as the > ones in our content.
    // the canReplace method checks that these counts are equal, so here we'll
    // call that method and then return it's opposite.
    
    return !$this->canReplace($content);
  }
  
  /**
   * makeReplacements
   *
   * Makes the necessary replacements within $content using str_replace for
   * content blocks without abbreviations in tags.
   *
   * @param string $content
   *
   * @return string
   */
  private function makeReplacements(string $content): string
  {
    // if we're here, then the only abbreviations in our $content are outside
    // of tags.  that means we can do our replacements using str_replace and
    // the arrays we can extract from our abbreviation collection.
  
    $tags = $this->abbreviations->getTags();
    $abbreviations = $this->abbreviations->getAbbreviations();
    $content = str_replace($abbreviations, $tags, $content);
    return $content;
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
    return [
      'last-abbreviation-check',
      'post-has-abbreviations',
      'post-has-abbrs-in-tags',
      'could-not-replace',
      'replaced-content',
    ];
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
