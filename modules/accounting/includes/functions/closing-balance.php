<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Get closest next financial year
 *
 * @param string $date
 *
 * @return void
 */
function erp_acct_clsbl_get_closest_next_fn_year( $date ) {
    global $wpdb;

    $sql = "SELECT id, start_date FROM {$wpdb->prefix}erp_acct_financial_years
        WHERE start_date > '%s' ORDER BY start_date ASC LIMIT 1";

    return $wpdb->get_row( $wpdb->prepare( $sql, $date ) );
}

/**
 * Close balance sheet now
 *
 * @param array $args
 *
 * @return void
 */
function erp_acct_clsbl_close_balance_sheet_now( $args ) {
    $balance_sheet  = erp_acct_get_balance_sheet($args);
    $assets         = $balance_sheet['rows1'];
    $liability      = $balance_sheet['rows2'];
    $next_f_year_id = $args['f_year_id'];

    // ledgers
    global $wpdb;
    $sql     = "SELECT id, chart_id, name, slug FROM {$wpdb->prefix}erp_acct_ledgers";
    $ledgers = $wpdb->get_results( $sql, ARRAY_A );

    foreach ( $ledgers as $ledger ) {
        // assets
        foreach ( $assets as $asset ) {
            if ( ! empty( $asset['id'] ) ) {
                if ( $asset['id'] === $ledger['id'] ) {
                    erp_acct_insert_into_opening_balance(
                        $next_f_year_id, $ledger['chart_id'], $ledger['id'], 'ledger', $asset['balance'], 0.00
                    );
                }
            }
        } // assets loop

        // liability
        foreach ( $liability as $liab_equ ) {
            if ( ! empty( $liab_equ['id'] ) ) {
                if ( $liab_equ['id'] === $ledger['id'] ) {
                    erp_acct_insert_into_opening_balance(
                        $next_f_year_id, $ledger['chart_id'], $ledger['id'], 'ledger', 0.00, $liab_equ['balance']
                    );
                }
            }
        } // liability loop
    } // ledger loop

    // get accounts receivable
    $accounts_receivable = erp_acct_clsbl_get_accounts_receivable_balance_with_people( $args );

    foreach ( $accounts_receivable as $acc_receivable ) {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $acc_receivable['id'], 'people', $acc_receivable['balance'], 0.00
        );
    }

    // get accounts payable
    $accounts_payable = erp_acct_clsbl_get_accounts_payable_balance_with_people( $args );

    foreach ( $accounts_payable as $acc_payable ) {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $acc_payable['id'], 'people', 0.00, abs($acc_payable['balance'])
        );
    }

    // sales tax receivable
    $tax_receivable = erp_acct_clsbl_sales_tax_agency( $args, 'receivable' );

    foreach ( $tax_receivable as $receivable_agency ) {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $receivable_agency['id'], 'tax_agency', $receivable_agency['balance'], 0.00
        );
    }

    // sales tax payable
    $tax_payable = erp_acct_clsbl_sales_tax_agency( $args, 'payable' );

    foreach ( $tax_payable as $payable_agency ) {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $payable_agency['id'], 'tax_agency', 0.00, abs($payable_agency['balance'])
        );
    }

    // owner's equity ( ledger_id: 30 ) with profit/loss
    $owners_equity_ledger = 30;

    if ( $balance_sheet['owners_equity'] > 0 ) {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $owners_equity_ledger, 'ledger', $balance_sheet['owners_equity'], 0.00
        );
    } else {
        erp_acct_clsbl_insert_into_opening_balance(
            $next_f_year_id, null, $owners_equity_ledger, 'ledger', 0.00, $balance_sheet['owners_equity']
        );
    }
}

/**
 * Insert closing balance data into opening balance
 *
 * @param int $f_year_id
 * @param int $chart_id
 * @param int $ledger_id
 * @param string $type
 * @param int $debit
 * @param int $credit
 *
 * @return void
 */
function erp_acct_clsbl_insert_into_opening_balance($f_year_id, $chart_id, $ledger_id, $type, $debit, $credit) {
    global $wpdb;

    $wpdb->insert(
        "{$wpdb->prefix}erp_acct_opening_balances",
        [
            'financial_year_id' => $f_year_id,
            'chart_id'          => $chart_id,
            'ledger_id'         => $ledger_id,
            'type'              => $type,
            'debit'             => $debit,
            'credit'            => $credit,
            'created_at'        => date('Y-m-d H:i:s'),
            'created_by'        => get_current_user_id()
        ]
    );

}

/**
 * Get accounts receivable balance with people
 *
 * @param array $args
 *
 * @return array
 */
function erp_acct_clsbl_get_accounts_receivable_balance_with_people( $args ) {
    global $wpdb;

    // mainly ( debit - credit )
    $sql = "SELECT invoice.customer_id AS id, SUM( debit - credit ) AS balance
        FROM {$wpdb->prefix}erp_acct_invoice_account_details AS invoice_acd
        LEFT JOIN {$wpdb->prefix}erp_acct_invoices AS invoice ON invoice_acd.invoice_no = invoice.voucher_no
        WHERE invoice_acd.trn_date BETWEEN '%s' AND '%s' GROUP BY invoice_acd.invoice_no HAVING balance > 0";

    $data = $wpdb->get_results( $wpdb->prepare( $sql, $args['start_date'], $args['end_date'] ), ARRAY_A );

    return erp_acct_clsbl_people_ar_calc_with_opening_balance( $args['start_date'], $data, $sql );
}

/**
 * Get accounts payable balance with people
 *
 * @param array $args
 *
 * @return array
 */
function erp_acct_clsbl_get_accounts_payable_balance_with_people( $args ) {
    global $wpdb;

    $bill_sql = "SELECT bill.vendor_id AS id, SUM( debit - credit ) AS balance
        FROM {$wpdb->prefix}erp_acct_bill_account_details AS bill_acd
        LEFT JOIN {$wpdb->prefix}erp_acct_bills AS bill ON bill_acd.bill_no = bill.voucher_no
        WHERE bill_acd.trn_date BETWEEN '%s' AND '%s' GROUP BY bill_acd.bill_no HAVING balance < 0";

    $purchase_sql = "SELECT purchase.vendor_id AS id, SUM( debit - credit ) AS balance
        FROM {$wpdb->prefix}erp_acct_purchase_account_details AS purchase_acd
        LEFT JOIN {$wpdb->prefix}erp_acct_purchase AS purchase ON purchase_acd.purchase_no = purchase.voucher_no
        WHERE purchase_acd.trn_date BETWEEN '%s' AND '%s' GROUP BY purchase_acd.purchase_no HAVING balance < 0";

    $bill_data     = $wpdb->get_results( $wpdb->prepare( $bill_sql, $args['start_date'], $args['end_date'] ), ARRAY_A );
    $purchase_data = $wpdb->get_results( $wpdb->prepare( $purchase_sql, $args['start_date'], $args['end_date'] ), ARRAY_A );

    return erp_acct_vendor_ap_calc_with_opening_balance(
        $args['start_date'], $bill_data, $purchase_data, $bill_sql, $purchase_sql
    );
}

/**
 * Get people account receivable calculate with opening balance within financial year date range
 *
 * @param string $bs_start_date
 * @param float $data => account details data on balance sheet date range
 * @param string $sql
 * @param string $type
 *
 * @return array
 */
function erp_acct_clsbl_people_ar_calc_with_opening_balance( $bs_start_date, $data, $sql ) {
    global $wpdb;

    // get closest financial year id and start date
    $closest_fy_date = erp_acct_clsbl_get_closest_fn_year_date( $bs_start_date );

    // get opening balance data within that(^) financial year
    $opening_balance = erp_acct_clsbl_customer_ar_opening_balance_by_fn_year_id( $closest_fy_date['id'] );

    $merged = array_merge($data, $opening_balance);
    $result = erp_acct_clsbl_get_formatted_people_balance( $merged );

    // should we go further calculation, check the diff
    if ( ! erp_acct_has_date_diff($bs_start_date, $closest_fy_date['start_date']) ) {
        return $result;
    } else {
        $prev_date_of_bs_start = date( 'Y-m-d', strtotime( '-1 day', strtotime($bs_start_date) ) );
    }

    $query  = $wpdb->get_results( $wpdb->prepare($sql, $closest_fy_date['start_date'], $prev_date_of_bs_start), ARRAY_A );
    $merged = array_merge($result, $query);

    return erp_acct_clsbl_get_formatted_people_balance( $merged );
}

/**
 * Get people account payable calculate with opening balance within financial year date range
 *
 * @param string $bs_start_date
 * @param float $data => account details data on balance sheet date range
 * @param string $sql
 * @param string $type
 *
 * @return array
 */

function erp_acct_clsbl_vendor_ap_calc_with_opening_balance($bs_start_date, $bill_data, $purchase_data, $bill_sql, $purchase_sql) {
    global $wpdb;

    // get closest financial year id and start date
    $closest_fy_date = erp_acct_get_closest_fn_year_date( $bs_start_date );

    // get opening balance data within that(^) financial year
    $opening_balance = erp_acct_vendor_ap_opening_balance_by_fn_year_id( $closest_fy_date['id'] );

    $merged = array_merge($bill_data, $purchase_data, $opening_balance);
    $result = erp_acct_get_formatted_people_balance($merged);

    // should we go further calculation, check the diff
    if ( ! erp_acct_has_date_diff($bs_start_date, $closest_fy_date['start_date']) ) {
        return $result;
    } else {
        $prev_date_of_bs_start = date( 'Y-m-d', strtotime( '-1 day', strtotime($bs_start_date) ) );
    }

    $query1 = $wpdb->get_results( $wpdb->prepare($bill_sql, $closest_fy_date['start_date'], $prev_date_of_bs_start), ARRAY_A );
    $query2 = $wpdb->get_results( $wpdb->prepare($purchase_sql, $closest_fy_date['start_date'], $prev_date_of_bs_start), ARRAY_A );
    $merged = array_merge($result, $query1, $query2);

    return erp_acct_get_formatted_people_balance($merged);
}

/**
 * People accounts receivable from opening balance
 *
 * @param int $id
 *
 * @return void
 */
function erp_acct_clsbl_customer_ar_opening_balance_by_fn_year_id( $id ) {
    global $wpdb;

    $sql = "SELECT ledger_id AS id, SUM( debit - credit ) AS balance
        FROM {$wpdb->prefix}erp_acct_opening_balances
        WHERE financial_year_id = %d AND type = 'people' GROUP BY ledger_id HAVING balance > 0";

    return $wpdb->get_results( $wpdb->prepare($sql, $id), ARRAY_A );
}

/**
 * People accounts payable from opening balance
 *
 * @param int $id
 *
 * @return void
 */
function erp_acct_clsbl_vendor_ap_opening_balance_by_fn_year_id( $id ) {
    global $wpdb;

    $sql = "SELECT ledger_id AS id, SUM( debit - credit ) AS balance
        FROM {$wpdb->prefix}erp_acct_opening_balances
        WHERE financial_year_id = %d AND type = 'people' GROUP BY ledger_id HAVING balance < 0";

    return $wpdb->get_results( $wpdb->prepare($sql, $id), ARRAY_A );
}

/**
 * Accounts receivable array merge
 *
 * @param array $arr1
 * @param array $arr2
 *
 * @return void
 */
function erp_acct_clsbl_get_formatted_people_balance( $arr ) {
    $temp = [];

    foreach ( $arr as $entry ) {
        // get index by id from a multidimensional array
        $index = array_search( $entry['id'], array_column($arr, 'id') );

        if ( ! empty( $temp[$index] ) ) {
            $temp[$index]['balance'] += $entry['balance'];
        } else {
            $temp[] = [
                'id'      => $entry['id'],
                'balance' => $entry['balance']
            ];
        }
    }

    return $temp;
}

function erp_acct_clsbl_sales_tax_agency( $args, $type ) {
    global $wpdb;

    if ( 'payable' === $type ) {
        $having = 'HAVING balance < 0';
    } elseif ( 'receivable' === $type ) {
        $having = 'HAVING balance > 0';
    }

    $sql = "SELECT agency_id, SUM( debit - credit ) AS balance FROM {$wpdb->prefix}erp_acct_tax_agency_details
        WHERE trn_date BETWEEN '%s' AND '%s'
        GROUP BY agency_id {$having}";

    $data = $wpdb->get_results( $wpdb->prepare( $sql, $args['start_date'], $args['end_date'] ) );

    return erp_acct_clsbl_sales_tax_agency_with_opening_balance( $args['start_date'], $data, $sql, $type );
}

/**
 * Get sales tax payable calculate with opening balance within financial year date range
 *
 * @param string $bs_start_date
 * @param float $data => agency details data on trial balance date range
 * @param string $sql
 * @param string $type
 *
 * @return float
 */
function erp_acct_clsbl_sales_tax_agency_with_opening_balance( $bs_start_date, $data, $sql, $type ) {
    global $wpdb;

    // get closest financial year id and start date
    $closest_fy_date = erp_acct_get_closest_fn_year_date( $bs_start_date );

    // get opening balance data within that(^) financial year
    $opening_balance = erp_acct_clsbl_sales_tax_agency_opening_balance_by_fn_year_id( $closest_fy_date['id'], $type );

    $merged = array_merge($data, $opening_balance);
    $result = erp_acct_get_formatted_people_balance( $merged );

    // should we go further calculation, check the diff
    if ( ! erp_acct_has_date_diff($bs_start_date, $closest_fy_date['start_date']) ) {
        return $balance;
    } else {
        $prev_date_of_tb_start = date( 'Y-m-d', strtotime( '-1 day', strtotime($tb_start_date) ) );
    }

    // get agency details data between
    //     `financial year start date`
    // and
    //     `previous date from trial balance start date`
    $agency_details_balance = $wpdb->get_results( $wpdb->prepare($sql, $closest_fy_date['start_date'], $prev_date_of_tb_start) );

    $merged = array_merge($result, $agency_details_balance);
    return erp_acct_clsbl_get_formatted_people_balance( $merged );
}

function erp_acct_clsbl_sales_tax_agency_opening_balance_by_fn_year_id( $id, $type ) {
    global $wpdb;

    if ( 'payable' === $type ) {
        $having = 'HAVING balance < 0';
    } elseif ( 'receivable' === $type ) {
        $having = 'HAVING balance > 0';
    }

    $sql = "SELECT agency_id, SUM( debit - credit ) AS balance
            FROM {$wpdb->prefix}erp_acct_opening_balances
            WHERE type = 'tax_agency' GROUP BY ledger_id {$having}";

    return $wpdb->get_results( $sql, ARRAY_A );
}