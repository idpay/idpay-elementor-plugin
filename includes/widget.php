<?php
/**
 * Elementor IDPay Widget.
 *
 * Elementor widget that inserts the IDPay transaction result.
 */
class Elementor_IDPay_Widget extends \Elementor\Widget_Base {

    /**
     * Retrieve IDPay widget name.
     * @return string Widget name.
     */
    public function get_name() {
        return 'idpay';
    }

    /**
     * Retrieve IDPay widget title.
     * @return string Widget title.
     */
    public function get_title() {
        return __( 'IDPay', 'plugin-name' );
    }

    /**
     * Get widget icon.
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'fa fa-code';
    }

    /**
     * Retrieve the list of categories the IDPay widget belongs to.
     *
     * @return array Widget categories.
     */
    public function get_categories() {
        return [ 'general' ];
    }

    /**
     * Adds different input fields to allow the user to change and customize the widget settings.
     */
    protected function _register_controls() {

        $this->start_controls_section(
            'idpay_section',
            [
                'label' => __( 'IDPay result message', 'idpay-elementor' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'idpay_classes',
            [
                'label' => __( 'Extra classes', 'plugin-name' ),
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $this->end_controls_section();

    }

    /**
     * Render IDPay widget output on the frontend.
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        $classes = $settings['idpay_classes'];

        if( !empty( $_GET['idpay_status'] ) && !empty( $_GET['idpay_message'] ) ){
            $color = $_GET['idpay_status'] == 'failed' ? '#f44336' : '#8BC34A';

            echo sprintf( '<div class="idpay-elementor-widget %s">', $classes );
            echo sprintf( '<b style="color:%s; text-align:center; display: block;">%s</b>', $color, sanitize_text_field( $_GET['idpay_message'] ) );
            echo '</div>';
        }

    }

}
