/**
 * Gutenberg Font Awesome Icon Picker
 *
 * Registers a custom inline format type that allows inserting
 * Font Awesome icons within any rich text block.
 */

import './style.scss';

import { registerFormatType, insert, create } from '@wordpress/rich-text';
import { BlockControls } from '@wordpress/block-editor';
import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { Modal, SearchControl, Spinner, ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const FORMAT_NAME = 'pitea/fontawesome-icon';

/**
 * FontAwesome Icon Picker Component
 */
const FontAwesomeIconPicker = ({ onSelect, onClose }) => {
    const [search, setSearch] = useState('');
    const [icons, setIcons] = useState([]);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [initialLoad, setInitialLoad] = useState(true);
    const debounceRef = useRef(null);

    /**
     * Fetch icons from the ACF endpoint
     */
    const fetchIcons = useCallback(async (searchTerm, pageNum, append = false) => {
        setLoading(true);

        const formData = new FormData();
        formData.append('action', 'acf/fields/fontawesome_icon/query');
        formData.append('s', searchTerm);
        formData.append('paged', pageNum);

        try {
            const response = await fetch(window.ajaxurl, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.results) {
                if (append) {
                    setIcons(prev => [...prev, ...data.results]);
                } else {
                    setIcons(data.results);
                }
                setHasMore(data.more || false);
            }
        } catch (error) {
            console.error('Error fetching icons:', error);
        } finally {
            setLoading(false);
            setInitialLoad(false);
        }
    }, []);

    // Initial load
    useEffect(() => {
        fetchIcons('', 1, false);
    }, [fetchIcons]);

    // Handle search with debounce
    const handleSearch = (value) => {
        setSearch(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            setPage(1);
            fetchIcons(value, 1, false);
        }, 300);
    };

    // Handle scroll for infinite loading
    const handleScroll = (e) => {
        const { scrollTop, scrollHeight, clientHeight } = e.target;

        if (scrollHeight - scrollTop <= clientHeight + 50 && !loading && hasMore) {
            const nextPage = page + 1;
            setPage(nextPage);
            fetchIcons(search, nextPage, true);
        }
    };

    // Handle icon selection
    const handleSelect = (iconClass) => {
        onSelect(iconClass);
        onClose();
    };

    return (
        <Modal
            className="pitea-fa-icon-modal"
            title={__('Select Icon', 'pitea-customisation')}
            onRequestClose={onClose}
        >
            <div className="pitea-fa-icon-picker">
                <div className="pitea-fa-icon-picker__search">
                    <SearchControl
                        __nextHasNoMarginBottom
                        value={search}
                        onChange={handleSearch}
                        placeholder={__('Search icons...', 'pitea-customisation')}
                    />
                </div>

                <div
                    className="pitea-fa-icon-picker__grid"
                    onScroll={handleScroll}
                >
                    {initialLoad && loading ? (
                        <div className="pitea-fa-icon-picker__loading">
                            <Spinner />
                        </div>
                    ) : icons.length === 0 ? (
                        <div className="pitea-fa-icon-picker__no-results">
                            {__('No icons found', 'pitea-customisation')}
                        </div>
                    ) : (
                        <>
                            {icons.map((icon) => (
                                <button
                                    key={icon.id}
                                    type="button"
                                    className="pitea-fa-icon-picker__option"
                                    onClick={() => handleSelect(icon.id)}
                                    title={icon.label}
                                >
                                    <i className={icon.id}></i>
                                </button>
                            ))}
                            {loading && !initialLoad && (
                                <div className="pitea-fa-icon-picker__loading-more">
                                    <Spinner />
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </Modal>
    );
};

/**
 * Format Edit Component
 */
const FontAwesomeFormatEdit = ({ value, onChange }) => {
    const [isPickerOpen, setIsPickerOpen] = useState(false);

    const handleIconSelect = (iconClass) => {
        // Create the icon HTML element
        const iconHtml = `<i class="${iconClass}">&#8203;</i>`;

        // Insert the icon at the current cursor position
        const newValue = insert(
            value,
            create({
                html: iconHtml,
            })
        );

        onChange(newValue);
    };

    return (
        <>
            <BlockControls>
                <ToolbarGroup>
                    <ToolbarButton
                        icon={
                            <span
                                className="fa-solid fa-icons"
                                style={{
                                    width: '24px',
                                    height: '24px',
                                    fontSize: '20px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center'
                                }}
                            />
                        }
                        title={__('Insert Icon', 'pitea-customisation')}
                        onClick={() => setIsPickerOpen(!isPickerOpen)}
                    />
                </ToolbarGroup>
            </BlockControls>
            {isPickerOpen && (
                <FontAwesomeIconPicker
                    onSelect={handleIconSelect}
                    onClose={() => setIsPickerOpen(false)}
                />
            )}
        </>
    );
};

/**
 * Register the format type
 */
registerFormatType(FORMAT_NAME, {
    title: __('Font Awesome Icon', 'pitea-customisation'),
    tagName: 'i',
    className: null,
    edit: FontAwesomeFormatEdit,
});
