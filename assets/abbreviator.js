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

const formatTypeTitle = 'Abbreviation';
const formatTypeName = 'dash/abbreviation';

const Abbreviator = ({isActive, value, onChange, contentRef}) => {
  const [isPopoverVisible, setIsPopoverVisible] = useState(false);
  const togglePopover = () => {
    setIsPopoverVisible((state) => !state);
  };
  
  const selectedBlock = useSelect((select) => {
    return select('core/block-editor').getSelectedBlock();
  }, []);
  
  if (selectedBlock && selectedBlock.name !== 'core/paragraph') {
    return null;
  }
  
  return (
    <>
      <RichTextToolbarButton
        icon={toolIcon}
        label="Abbreviation"
        title="Abbreviation"
        isActive={isActive}
        role="menuitemcheckbox"
        onClick={() => {
          if (isActive) {
            onChange(removeFormat(value, name));
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
