<?php

namespace PiteaCustomisation\Customisations\Typesense;

/**
 * Class SchemaLocale
 *
 * Modifies the Typesense collection schema to use the Swedish locale ('sv')
 * for string fields. This prevents Typesense from folding Swedish characters
 * (å, ä, ö) into their unaccented counterparts (a, o), ensuring accurate
 * search results for Swedish content.
 */
class SchemaLocale
{
    public function __construct()
    {
        add_filter('Municipio/TypesenseSearch/Collection/getSchema', [$this, 'setSwedishLocale']);
    }

    /**
     * Appends the 'sv' locale to string fields in the Typesense schema.
     *
     * @param array $schema The original Typesense schema.
     * @return array The modified schema.
     */
    public function setSwedishLocale(array $schema): array
    {
        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            return $schema;
        }

        foreach ($schema['fields'] as &$field) {
            // Apply locale to string fields (and string arrays if any exist)
            if (isset($field['type']) && in_array($field['type'], ['string', 'string[]'])) {
                // Skip fields that explicitly shouldn't be indexed for search
                if (isset($field['index']) && $field['index'] === false) {
                    continue;
                }
                
                $field['locale'] = 'sv';
            }
        }

        return $schema;
    }
}
