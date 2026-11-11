<?php

namespace App\Support;

class PivotQueryHelper
{
    /**
     * Build subquery SQL for document accreditations (FDA, NDA, CSO, PhilGEPS, FFEDIS).
     */
    public static function documentAccreditation(string $docType, string $tagKey = 'partner', bool $includeCertNo = false): string
    {
        $codeAlias = $tagKey === 'center' ? 'center_code' : 'partner_code';
        $certNoSelect = $includeCertNo ? ', d.registration_number' : '';
        $certNoAlias = $includeCertNo ? ", registration_number AS {$docType}_cert_no" : '';
        $normalizedDocType = str_replace('_', '-', $docType);

        return <<<SQL
            SELECT {$codeAlias},
                   compliance_status AS {$docType}_status,
                   validity AS {$docType}_date_accredited{$certNoAlias}
            FROM (
                SELECT tag.value AS {$codeAlias},
                       CASE d.compliance_status
                           WHEN 'for_processing' THEN 'For Processing'
                           WHEN 'for_renewal' THEN 'For Renewal'
                           WHEN 'accredited' THEN 'Accredited'
                           WHEN 'platinum' THEN 'Platinum'
                           WHEN 'red_accreditation' THEN 'Red Accreditation'
                           WHEN 'red_registration' THEN 'Red Registration'
                           WHEN 'retailer_of_bodaco' THEN 'Retailer of BODACO'
                           ELSE d.compliance_status
                       END AS compliance_status,
                       d.validity{$certNoSelect},
                       ROW_NUMBER() OVER (
                           PARTITION BY tag.value
                           ORDER BY d.validity DESC, d.id DESC
                       ) AS rn
                FROM documents d
                JOIN tags doc_tag
                  ON doc_tag.taggable_type = 'document'
                 AND doc_tag.taggable_id = d.id
                 AND doc_tag.`key` = 'document'
                 AND doc_tag.value = '{$normalizedDocType}'
                JOIN tags tag
                  ON tag.taggable_type = 'document'
                 AND tag.taggable_id = d.id
                 AND tag.`key` = '{$tagKey}'
            ) ranked
            WHERE rn = 1
            SQL;
    }

    /**
     * Build subquery SQL for concatenated value chain operations (Production, Consolidation, Processing, Marketing).
     */
    public static function valueChain(string $foreignKey = 'partner_id'): string
    {
        $whereClause = $foreignKey !== 'center_id' ? "WHERE {$foreignKey} IS NOT NULL" : '';

        return <<<SQL
            SELECT units.{$foreignKey},
                   GROUP_CONCAT(units.label
                       ORDER BY units.sort_no
                       SEPARATOR ', '
                   ) AS value_chain
            FROM (
                SELECT DISTINCT {$foreignKey}, label, sort_no
                FROM (
                    SELECT {$foreignKey}, 'Production' AS label, 1 AS sort_no FROM production_units {$whereClause}
                    UNION ALL
                    SELECT {$foreignKey}, 'Consolidation' AS label, 2 AS sort_no FROM consolidation_units {$whereClause}
                    UNION ALL
                    SELECT {$foreignKey}, 'Processing' AS label, 3 AS sort_no FROM processing_units {$whereClause}
                    UNION ALL
                    SELECT {$foreignKey}, 'Marketing' AS label, 4 AS sort_no FROM marketing_units {$whereClause}
                ) AS labels
            ) AS units
            GROUP BY units.{$foreignKey}
            SQL;
    }

    /**
     * Build subquery SQL for concatenated product categories (Milk, Meat, Manure, Hide).
     */
    public static function products(string $foreignKey = 'partner_id', string $pivotTable = 'partner_product'): string
    {
        return <<<SQL
            SELECT labels.{$foreignKey},
                   GROUP_CONCAT(labels.category_label
                       ORDER BY labels.category_sort
                       SEPARATOR ', '
                   ) AS products
            FROM (
                SELECT DISTINCT {$foreignKey}, category_label, category_sort
                FROM (
                    SELECT p_rel.{$foreignKey},
                           CASE p.category
                               WHEN 'milk' THEN 'Milk'
                               WHEN 'meat' THEN 'Meat'
                               WHEN 'manure' THEN 'Manure'
                               WHEN 'hide' THEN 'Hide'
                               ELSE NULL
                           END AS category_label,
                           CASE p.category
                               WHEN 'milk' THEN 1
                               WHEN 'meat' THEN 2
                               WHEN 'manure' THEN 3
                               WHEN 'hide' THEN 4
                               ELSE 5
                           END AS category_sort
                    FROM {$pivotTable} p_rel
                    JOIN products p ON p.id = p_rel.product_id
                    WHERE p.category IN ('milk', 'meat', 'manure', 'hide')
                ) AS products
            ) AS labels
            GROUP BY labels.{$foreignKey}
            SQL;
    }

    /**
     * Build subquery SQL for Milk Feeding Program participation and initial date.
     */
    public static function milkFeedingProgram(string $foreignKey = 'partner_id'): string
    {
        $isCenter = $foreignKey === 'center_id';
        $extraSelect = $isCenter ? ", 'Yes' AS mfp_participant" : '';

        return <<<SQL
            SELECT {$foreignKey},
                   MIN(date_started) AS date_engaged_mfp{$extraSelect}
            FROM milk_feeding_programs
            WHERE {$foreignKey} IS NOT NULL
            GROUP BY {$foreignKey}
            SQL;
    }

    /**
     * Build subquery SQL for Kadiwa ni Pangulo engagement status.
     */
    public static function kadiwaEngagement(): string
    {
        return <<<'SQL'
            SELECT id AS partner_id,
                   CASE WHEN is_knpo_partner = 1 THEN 'Yes' ELSE 'No' END AS kadiwa_engagement
            FROM partners
            SQL;
    }

    /**
     * Build subquery SQL for consolidator indicator.
     */
    public static function consolidator(): string
    {
        return <<<'SQL'
            SELECT partner_id,
                   CASE WHEN COUNT(*) > 0 THEN 'Yes' ELSE 'No' END AS consolidator
            FROM consolidation_units
            WHERE partner_id IS NOT NULL
            GROUP BY partner_id
            SQL;
    }
}
