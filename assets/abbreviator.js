import {
  TextControl,
  Button,
  Popover,
  __experimentalHStack as HStack,
  __experimentalVStack as VStack
} from '@wordpress/components';

import {applyFormat, registerFormatType, removeFormat, useAnchor} from '@wordpress/rich-text';
import {RichTextToolbarButton} from "@wordpress/block-editor";
import {useSelect} from '@wordpress/data';
import {tool as toolIcon} from "@wordpress/icons";
import {useState} from "@wordpress/element";

// these two variables are used throughout the code below.  since we use the
// word "title" when referring to the <abbr> tag's attribute, we make the these
// variables a little more verbose so they don't collide elsewhere.

const formatTypeTitle = 'Abbreviation';
const formatTypeName = 'dash/abbreviation';
const allowedBlocks = ['core/paragraph'];

/**
 * The editor for our format type.
 *
 * @param isActive
 * @param value
 * @param onChange
 * @param contentRef
 *
 * @returns {JSX.Element|null}
 * @constructor
 */
const Abbreviator = ({isActive, value, onChange, contentRef}) => {
  
  // first, if this isn't an allowed block, then we can return null because
  // we do not want to add the <abbr> type at this time.  once we identify
  // the selected block, we can see if it's included in the above defined
  // allowedBlock array.
  
  const selectedBlock = useSelect((select) => {
    return select('core/block-editor').getSelectedBlock();
  }, []);
  
  if (selectedBlock && !allowedBlocks.includes(selectedBlock.name)) {
    return null;
  }
  
  // if we haven't returned, then we set up a function that will toggle the
  // visibility of our popover.  because the popover should start closed, we're
  // 90+ percent sure that's why the Boolean false value is passed to useState.
  
  const [isPopoverVisible, setIsPopoverVisible] = useState(false);
  const togglePopover = () => {
    setIsPopoverVisible((state) => !state);
  };
  
  return (
    <>
      <RichTextToolbarButton
        icon={toolIcon}
        label={formatTypeTitle}
        title={formatTypeTitle}
        isActive={isActive}
        role="menuitemcheckbox"
        onClick={() => {
          if (isActive) {
            onChange(removeFormat(value, formatTypeName));
          } else {
            togglePopover();
          }
        }}
      />
      {isPopoverVisible && (
        <InlineAbbrUI
          value={value}
          onChange={onChange}
          onClose={togglePopover}
          contentRef={contentRef}
        />
      )}
    </>
  );
};

function InlineAbbrUI({value, contentRef, onChange, onClose}) {
  const popoverAnchor = useAnchor({
    editableContentElement: contentRef.current,
    settings: abbreviator
  });
  
  const [title, setTitle] = useState('');
  
  return (
    <Popover
      className="block-editor-format-toolbar__abbreviator-popover"
      anchor={popoverAnchor}
      onClose={onClose}
    >
      <VStack
        as="form"
        spacing={4}
        className="block-editor-format-toolbar__abbreviator-container-content"
        onSubmit={(event) => {
          event.preventDefault();
          onChange(
            applyFormat(value, {
              type: formatTypeName,
              attributes: {title: title},
            })
          );
          onClose();
        }}
      >
        <TextControl
          __next40pxDefaultSize
          __nextHasNoMarginBottom
          label={'Add' + formatTypeTitle}
          value={title}
          onChange={(titleValue) => setTitle(titleValue)}
          help={'What does this abbreviation mean?'}
        />
        <HStack alignment="right">
          <Button
            __next40pxDefaultSize
            variant="primary"
            type="submit"
            text={'Apply'}
          />
        </HStack>
      </VStack>
    </Popover>
  );
}

// this small object is used above as the settings for our popoverAnchor.
// it's very similar to the object used in the registerFormatType function
// below, but because it's not exact, we can't simply use it again.  instead,
// we use the bits of it that we can so that changes to the object will also
// register the format type correctly.

const abbreviator = {
  formatTypeName,
  tagName: 'abbr',
  className: null,
  edit: Abbreviator,
  formatTypeTitle
};

registerFormatType(formatTypeName, {
  tagName: abbreviator.tagName,
  className: abbreviator.className,
  edit: abbreviator.edit,
  title: formatTypeTitle
});
