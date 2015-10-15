<?php
namespace WeDevs\ERP\Admin;
use WeDevs\ERP\Company;
use WeDevs\ERP\Framework\Traits\Hooker;

/**
 * Admin form handler
 *
 * Handles all the form submission
 */
class Form_Handler {

    use Hooker;

    /**
     * [__construct description]
     */
    public function __construct() {
        $this->action( 'erp_action_create_new_company', 'create_new_company' );
        $this->action( 'admin_init', 'save_settings' );
    }

    function save_settings() {
        if ( ! isset( $_POST['erp_module_status'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['erp_settings'], 'erp_nonce' ) ) {
            return;
        }
        
        $inactive    =  ( isset( $_GET['tab'] ) && $_GET['tab'] == 'inactive' ) ? true : false;
        $modules     = isset( $_POST['modules'] ) ? $_POST['modules'] : array();
        $all_modules = wperp()->modules->get_modules();

        foreach ( $all_modules as $key => $module ) {
            if ( ! in_array( $key, $modules ) ) {
                unset( $all_modules[$key] );
            }
        }

        if ( $inactive ) {
            $active_modules = wperp()->modules->get_active_modules();
            $all_modules    = array_merge( $all_modules, $active_modules );
        }

        update_option( 'erp_modules', $all_modules );
        wp_redirect( $_POST['_wp_http_referer'] );
        exit();
    }

    public function is_valid_input( $array, $key ) {
        if ( ! isset( $array[$key]) || empty( $array[$key] ) || $array[$key] == '-1' ) {
            return false;
        }

        return true;
    }

    /**
     * Create a new company
     *
     * @return void
     */
    public function create_new_company() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'erp-new-company' ) ) {
            wp_die( __( 'Cheating?', 'wp-erp' ) );
        }

        $posted   = array_map( 'strip_tags_deep', $_POST );
        $posted   = array_map( 'trim_deep', $posted );

        $errors   = [];
        $required = [
            'name'    => __( 'Company name', 'wp-erp' ),
            'address' => [
                'country' => __( 'Country', 'wp-erp' )
            ]
        ];

        if ( ! $this->is_valid_input( $posted, 'name' ) ) {
            $errors[] = __( 'Company name is required', 'wp-erp' );
        }

        if ( ! $this->is_valid_input( $posted['address'], 'country' ) ) {
            $errors[] = __( 'Country is required', 'wp-erp' );
        }

        if ( $errors ) {
            var_dump( $errors );
            die();
        }

        $args = [
            'logo'    => isset( $posted['company_logo_id'] ) ? absint( $posted['company_logo_id'] ) : 0,
            'name'    => $posted['name'],
            'address' => [
                'address_1' => $posted['address']['address_1'],
                'address_2' => $posted['address']['address_2'],
                'city'      => $posted['address']['city'],
                'state'     => $posted['address']['state'],
                'zip'       => $posted['address']['zip'],
                'country'   => $posted['address']['country'],
            ],
            'phone'     => $posted['phone'],
            'fax'       => $posted['fax'],
            'mobile'    => $posted['mobile'],
            'website'   => $posted['website'],
            'currency'  => $posted['currency'],
        ];

        $company = new Company();
        $company->update( $args );

        $redirect_to = admin_url( 'admin.php?page=erp-company&action=edit&msg=updated' );
        wp_redirect( $redirect_to );
        exit;
    }
}

new Form_Handler();