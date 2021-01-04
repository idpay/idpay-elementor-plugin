<?php
/**
 * Class IDPay_Action_After_Submit
 * Custom elementor form action after submit to process payment
 */

use Elementor\Controls_Manager;
use Elementor\Settings;

class IDPay_Action_After_Submit extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    public function __construct() {
        if ( is_admin() ) {
            add_action( 'elementor/admin/after_create_settings/' . Settings::PAGE_ID, [ $this, 'register_admin_fields' ], 10 );
        }
    }

    /**
     * Return the action name
     * @return string
     */
    public function get_name() {
        return 'idpay';
    }

    /**
     * Returns the action label
     *
     * @return string
     */
    public function get_label() {
        return __( 'IDPay', 'idpay-elementor' );
    }

    /**
     * Runs the action after submit
     *
     * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public function run( $record, $ajax_handler ) {
        global $wpdb;

        $settings = $record->get( 'form_settings' );

        $api_key = $this->get_global_setting('idpay_api_key');
        $sandbox = $this->get_global_setting('idpay_sandbox') || 'false';
        $currency = $this->get_global_setting('idpay_currency') || 'rial';

        if ( empty( $api_key ) ) {
            $this->show_error( __( 'IDPay settings is not configured.', 'idpay-elementor' ), $ajax_handler );
        }

        // Get submitted Form data
        $raw_fields = $record->get( 'fields' );

        // Normalize the Form Data
        $fields = [];
        foreach ( $raw_fields as $id => $field ) {
            $fields[ $id ] = $field['value'];
        }

        // Process the amount
        if ( empty( $fields[ $settings['idpay_amount_field'] ] )) {
            $this->show_error( __( 'Amount should not be empty.', 'idpay-elementor' ), $ajax_handler );
        }
        $amount = intval( $fields[ $settings['idpay_amount_field'] ] );
        $amount = $amount * ($currency == 'rial' ? 1 : 10);

        // Set all other fields
        $name = !empty( $fields[ $settings['idpay_name_field'] ] ) ? $fields[ $settings['idpay_name_field'] ] : '';
        $phone = !empty( $fields[ $settings['idpay_phone_field'] ] ) ? $fields[ $settings['idpay_phone_field'] ] : '';
        $email = !empty( $fields[ $settings['idpay_email_field'] ] ) ? $fields[ $settings['idpay_email_field'] ] : '';
        $desc = !empty( $fields[ $settings['idpay_desc_field'] ] ) ? $fields[ $settings['idpay_desc_field'] ] : '';
        $order_id = time();

        $row = [
            'order_id' => $order_id,
            'post_id' => sanitize_text_field($_POST['post_id']),
            'trans_id' => '',
            'amount' => $amount,
            'phone' => $phone,
            'description' => $desc,
            'email' => $email,
            'created_at' => time(),
            'status' => 'pending',
            'log' => '',
            'return_url' => $_REQUEST['referrer'],
        ];
        $row_format = [
            '%d',
            '%d',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            "%s",
            "%s",
        ];

        $data = [
            'order_id'	=> $order_id,
            'amount'	=> $amount,
            'name'		=> $name,
            'phone'		=> $phone,
            'mail'		=> $email,
            'desc'		=> $desc,
            'callback'	=> add_query_arg( 'elementor_idpay_action', 'callback', get_home_url() ),
        ];
        $headers = [
            'Content-Type'=> 'application/json',
            'X-API-KEY'	=> $api_key,
            'X-SANDBOX'	=> $sandbox,
        ];
        $args = [
            'body'		=> json_encode( $data ),
            'headers'	=> $headers,
            'timeout'	=> 15,
        ];

        $IDPay = new IDPay_Elementor_Extension;
        $response = $IDPay->call_gateway_endpoint( 'https://api.idpay.ir/v1.1/payment', $args );
        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            $row['status'] = 'failed';
            $row['log'] = $error;
            $wpdb->insert( $wpdb->prefix . $IDPay::IDPAY_TABLE_NAME, $row, $row_format );

            $this->show_error( $error, $ajax_handler );
        }

        $http_status	= wp_remote_retrieve_response_code( $response );
        $result			= wp_remote_retrieve_body( $response );
        $result			= json_decode( $result );

        if ( 201 !== $http_status || empty( $result ) || empty( $result->link ) ) {
            $error = sprintf( '%s (code: %s)', $result->error_message, $result->error_code );
            $row['status'] = 'failed';
            $row['log'] = $error;
            $wpdb->insert( $wpdb->prefix . $IDPay::IDPAY_TABLE_NAME, $row, $row_format );

            $this->show_error( $error, $ajax_handler );
        }

        $row['trans_id'] = $result->id;
        $row['status'] = 'bank';
        $wpdb->insert( $wpdb->prefix . $IDPay::IDPAY_TABLE_NAME, $row, $row_format );

        $ajax_handler->add_response_data( 'redirect_url', $result->link );

    }

    /**
     * Register Settings Section
     *
     * Registers the Action controls
     *
     * @access public
     * @param \Elementor\Widget_Base $widget
     */
    public function register_settings_section( $widget ) {

        $widget->start_controls_section(
            'section_idpay',
            [
                'label' => __( 'IDPay', 'idpay-elementor' ),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'idpay_msg',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => sprintf( __( 'Set your Default Values in the <a href="%1$s" target="_blank">Integrations Settings</a>.', 'idpay-elementor' ), Settings::get_url() . '#tab-integrations' ),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
            ]
        );

        $widget->add_control(
            'idpay_amount_field',
            [
                'label' => __( 'Amount Field ID', 'idpay-elementor' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'idpay_email_field',
            [
                'label' => __( 'Email Field ID', 'idpay-elementor' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'idpay_name_field',
            [
                'label' => __( 'Name Field ID', 'idpay-elementor' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'idpay_phone_field',
            [
                'label' => __( 'Phone Field ID', 'idpay-elementor' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $widget->add_control(
            'idpay_desc_field',
            [
                'label' => __( 'Desc Field ID', 'idpay-elementor' ),
                'type' => Controls_Manager::TEXT,
            ]
        );

        $widget->end_controls_section();

    }

    /**
     * Clears form settings on export
     * @access Public
     * @param array $element
     */
    public function on_export( $element ) {
        unset(
            $element['idpay_api_key'],
            $element['idpay_sandbox'],
            $element['idpay_amount_field'],
            $element['idpay_email_field'],
            $element['idpay_name_field'],
            $element['idpay_phone_field'],
            $element['idpay_desc_field'],
        );
    }

    /**
     * @param Settings $settings
     */
    public function register_admin_fields( Settings $settings ) {

        $settings->add_section( Settings::TAB_INTEGRATIONS, 'idpay', [
            'callback' => function() {
                echo '<hr><h2>' . esc_html__( 'IDPay', 'idpay-elementor' ) . '</h2>';
            },
            'fields' => [
                'idpay_api_key' => [
                    'label' => __( 'IDPay API Key', 'idpay-elementor' ),
                    'field_args' => [
                        'type' => 'text',
                        'desc' => sprintf( __( 'To integrate with our forms you need an <a href="%s" target="_blank">API Key</a>.', 'idpay-elementor' ), 'https://idpay.ir/dashboard/web-services/' ),
                    ],
                ],
                'idpay_sandbox' => [
                    'label' => __( 'Sandbox mode', 'idpay-elementor' ),
                    'field_args' => [
                        'type' => 'select',
                        'default' => 'false',
                        'options' => [
                            'true' => __( 'Yes', 'idpay-elementor' ),
                            'false' => __( 'No', 'idpay-elementor' ),
                        ],
                    ],
                ],
                'idpay_currency' => [
                    'label' => __( 'Default Currency', 'idpay-elementor' ),
                    'field_args' => [
                        'type' => 'select',
                        'default' => 'rial',
                        'options' => [
                            'rial' => __( 'Rial', 'idpay-elementor' ),
                            'toman' => __( 'Toman', 'idpay-elementor' ),
                        ],
                    ],
                ],
            ],
        ] );

    }

    /**
     * @param $name
     *
     * @return bool|mixed|void
     */
    private function get_global_setting( $name ) {
        return get_option( 'elementor_' . $name );
    }

    /**
     * @param $message
     * @param $ajax_handler
     */
    private function show_error( $message, $ajax_handler ) {

        wp_send_json_error( [
            'message' => $message,
            'data' => $ajax_handler->data,
        ] );

    }
}
