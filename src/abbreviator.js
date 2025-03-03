import {registerFormatType, toggleFormat} from '@wordpress/rich-text';
import {RichTextToolbarButton} from "@wordpress/block-editor";
import {useSelect} from '@wordpress/data';

const Abbreviator = ({isActive, onChange, value}) => {
  const selectedBlock = useSelect((select) => {
    return select('core/block-editor').getSelectedBlock();
  }, []);
  
  if (selectedBlock && selectedBlock.name !== 'core/paragraph') {
    return null;
  }
  
  return (
    <RichTextToolbarButton
      icon="editor-code"
      title="Abbreviation"
      isActive={isActive}
      onClick={() => {
        onChange(
          toggleFormat(value, {
            type: 'dash/abbreviation'
          })
        );
      }}
    />
  );
};

registerFormatType('dash/abbreviation', {
  title: 'Abbreviation',
  tagName: 'abbr',
  className: null,
  edit: Abbreviator
});
