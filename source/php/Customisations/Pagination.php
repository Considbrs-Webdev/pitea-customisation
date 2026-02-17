<?php

namespace PiteaCustomisation\Customisations;

class Pagination
{
    /**
     * Store the original full list for reference
     */
    private $originalList = [];
    private $currentPage = 1;
    private $totalPages = 0;

    public function __construct()
    {
        // Hook into Pagination component data filters
        // add_filter('ComponentLibrary/Component/Pagination/Data', [$this, 'storeOriginalData'], 5);
        // add_filter('ComponentLibrary/Component/Pagination/List', [$this, 'modifyList'], 10);
        // add_filter('ComponentLibrary/Component/Pagination/FirstItem', [$this, 'modifyFirstItem'], 10);
        // add_filter('ComponentLibrary/Component/Pagination/LastItem', [$this, 'modifyLastItem'], 10);
        // add_filter('ComponentLibrary/Component/Pagination/Attribute', [$this, 'addDataAttributes'], 10);
    }

    /**
     * Store original data before component processes it
     *
     * @param array $data
     * @return array
     */
    public function storeOriginalData(array $data): array
    {
        if (isset($data['list']) && is_array($data['list'])) {
            $this->originalList = $data['list'];
            $this->totalPages = count($data['list']);
        }
        if (isset($data['current'])) {
            $this->currentPage = (int) $data['current'];
        }

        return $data;
    }

    /**
     * Modify the list to exclude first and last pages
     * This ensures they always render separately with ellipses
     * Exception: Don't remove if current page is within first 2 or last 2 pages
     *
     * @param array $list
     * @return array
     */
    public function modifyList(array $list): array
    {
        if (empty($this->originalList) || $this->totalPages <= 5) {
            // If 5 or fewer pages, return as-is (no need for ellipses)
            return $list;
        }

        $firstKey = 0;
        $lastKey = $this->totalPages - 1;
        $currentKey = $this->currentPage - 1;

        // Remove first page from list if it exists and current is not page 1 or 2
        if (isset($list[$firstKey]) && $this->currentPage > 2) {
            unset($list[$firstKey]);
        }

        // Remove last page from list if it exists and current is not last or second-to-last page
        if (isset($list[$lastKey]) && $this->currentPage < ($this->totalPages - 1)) {
            unset($list[$lastKey]);
        }

        // Return list with preserved keys (don't re-index)
        return $list;
    }

    /**
     * Always return first item (unless current is page 1 or 2)
     *
     * @param mixed $firstItem
     * @return mixed
     */
    public function modifyFirstItem($firstItem)
    {
        if (empty($this->originalList)) {
            return $firstItem;
        }

        // When 5 or fewer pages, core handles it; don't add separate first item (avoids duplicate on desktop)
        if ($this->totalPages <= 5) {
            return $firstItem;
        }

        // If current page is 1 or 2, don't show first page separately (no ellipsis needed)
        if ($this->currentPage <= 2) {
            return false;
        }

        // Always return first item if it exists
        if (isset($this->originalList[0])) {
            $item = $this->originalList[0];
            $item['key'] = 0;
            return $item;
        }

        return $firstItem;
    }

    /**
     * Always return last item (unless current is last or second-to-last page)
     *
     * @param mixed $lastItem
     * @return mixed
     */
    public function modifyLastItem($lastItem)
    {
        if (empty($this->originalList)) {
            return $lastItem;
        }

        // When 5 or fewer pages, core handles it; don't add separate last item (avoids duplicate on desktop)
        if ($this->totalPages <= 5) {
            return $lastItem;
        }

        $lastKey = $this->totalPages - 1;

        // If current page is last or second-to-last, don't show last page separately (no ellipsis needed)
        if ($this->currentPage >= ($this->totalPages - 1)) {
            return false;
        }

        // Always return last item if it exists
        if (isset($this->originalList[$lastKey])) {
            $item = $this->originalList[$lastKey];
            $item['key'] = $lastKey;
            return $item;
        }

        return $lastItem;
    }

    /**
     * Add data attributes to the pagination component for CSS targeting
     * This filters the Attribute which can be either an array or string
     *
     * @param array|string $attribute
     * @return array|string
     */
    public function addDataAttributes($attribute)
    {
        // Build our additional attributes
        $additionalAttributes = [];
        if ($this->currentPage > 0 && $this->totalPages > 0) {
            $additionalAttributes['data-current-page'] = $this->currentPage;
            $additionalAttributes['data-total-pages'] = $this->totalPages;

            // Add class-like data attribute for edge cases
            if ($this->currentPage === 2) {
                $additionalAttributes['data-show-page-1'] = 'true';
            }
            if ($this->currentPage === ($this->totalPages - 1)) {
                $additionalAttributes['data-show-last-page'] = 'true';
            }
        }

        // If it's an array, merge and return array
        if (is_array($attribute)) {
            return array_merge($attribute, $additionalAttributes);
        }

        // If it's a string, append our attributes
        if (is_string($attribute)) {
            $newAttributes = [];
            foreach ($additionalAttributes as $key => $value) {
                $newAttributes[] = $key . '="' . esc_attr($value) . '"';
            }
            return $attribute . ' ' . implode(' ', $newAttributes);
        }

        // Fallback: return as-is
        return $attribute;
    }
}
