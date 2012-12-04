<?php
/**
 * @package Filters\Fields
 */
class FiltersField_Text extends FiltersField {

    /**
     * Field Type Group
     *
     * @var string
     * @since 0.1
     */
    public static $group = 'Text';

    /**
     * Field Type Identifier
     *
     * @var string
     * @since 0.1
     */
    public static $type = 'text';

    /**
     * Field Type Label
     *
     * @var string
     * @since 0.1
     */
    public static $label = 'Plain Text';

    /**
     * Field Type Preparation
     *
     * @var string
     * @since 0.1
     */
    public static $prepare = '%s';

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 0.1
     */
    public function __construct () {

    }

    /**
     * Add options and set defaults to
     *
     *
     * @return array
     * @since 0.1
     */
    public function options () {
        $options = array(
            'output_options' => array(
                'label' => __( 'Output Options', 'filters' ),
                'group' => array(
                    'text_allow_shortcode' => array(
                        'label' => __( 'Allow Shortcodes?', 'filters' ),
                        'default' => 0,
                        'type' => 'boolean',
                        'dependency' => true
                    ),
                    'text_allow_html' => array(
                        'label' => __( 'Allow HTML?', 'filters' ),
                        'default' => 0,
                        'type' => 'boolean',
                        'dependency' => true
                    )
                )
            ),
            'text_allowed_html_tags' => array(
                'label' => __( 'Allowed HTML Tags', 'filters' ),
                'depends-on' => array( 'text_allow_html' => true ),
                'default' => 'strong em a ul ol li b i',
                'type' => 'text'
            ),
            'text_max_length' => array(
                'label' => __( 'Maximum Length', 'filters' ),
                'default' => 255,
                'type' => 'number'
            )/*,
            'text_size' => array(
                'label' => __( 'Field Size', 'filters' ),
                'default' => 'medium',
                'type' => 'pick',
                'data' => array(
                    'small' => __( 'Small', 'filters' ),
                    'medium' => __( 'Medium', 'filters' ),
                    'large' => __( 'Large', 'filters' )
                )
            )*/
        );

        return $options;
    }

    /**
     * Define the current field's schema for DB table storage
     *
     * @param array $options
     *
     * @return array
     * @since 0.1
     */
    public function schema ( $options = null ) {
        $length = (int) filters_var( 'text_max_length', $options, 255, null, true );

        if ( $length < 1 )
            $length = 255;

        $schema = 'VARCHAR(' . $length . ')';

        return $schema;
    }

    /**
     * Change the way the value of the field is displayed with Filters::get
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $pod
     * @param int $id
     *
     * @return mixed|null|string
     * @since 0.1
     */
    public function display ( $value = null, $name = null, $options = null, $pod = null, $id = null ) {
        $value = $this->strip_html( $value, $options );

        if ( 1 == filters_var( 'text_allow_shortcode', $options ) )
            $value = do_shortcode( $value );

        return $value;
    }

    /**
     * Customize output of the form field
     *
     * @param string $name
     * @param mixed $value
     * @param array $options
     * @param array $pod
     * @param int $id
     *
     * @since 0.1
     */
    public function input ( $name, $value = null, $options = null, $pod = null, $id = null ) {
        $options = (array) $options;

        if ( is_array( $value ) )
            $value = implode( ' ', $value );

        filters_view( FILTERS_DIR . 'ui/fields/text.php', compact( array_keys( get_defined_vars() ) ) );
    }

    /**
     * Validate a value before it's saved
     *
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param int $id
     *
     * @param null $params
     * @return array|bool
     * @since 0.1
     */
    public function validate ( &$value, $name = null, $options = null, $fields = null, $pod = null, $id = null, $params = null ) {
        $errors = array();

        $check = $this->pre_save( $value, $id, $name, $options, $fields, $pod, $params );

        if ( is_array( $check ) )
            $errors = $check;
        else {
            if ( 0 < strlen( $value ) && strlen( $check ) < 1 ) {
                if ( 1 == filters_var( 'required', $options ) )
                    $errors[] = __( 'This field is required.', 'filters' );
            }
        }

        if ( !empty( $errors ) )
            return $errors;

        return true;
    }

    /**
     * Change the value or perform actions after validation but before saving to the DB
     *
     * @param mixed $value
     * @param int $id
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     * @param object $params
     *
     * @return mixed|string
     * @since 0.1
     */
    public function pre_save ( $value, $id = null, $name = null, $options = null, $fields = null, $pod = null, $params = null ) {
        $value = $this->strip_html( $value, $options );

        return $value;
    }

    /**
     * Customize the Filters UI manage table column output
     *
     * @param int $id
     * @param mixed $value
     * @param string $name
     * @param array $options
     * @param array $fields
     * @param array $pod
     *
     * @return mixed|string
     * @since 0.1
     */
    public function ui ( $id, $value, $name = null, $options = null, $fields = null, $pod = null ) {
        $value = $this->strip_html( $value, $options );

        if ( 0 == filters_var( 'text_allow_html', $options, 0, null, true ) )
            $value = wp_trim_words( $value );

        return $value;
    }

    /**
     * Strip HTML based on options
     *
     * @param string $value
     * @param array $options
     *
     * @return string
     */
    public function strip_html ( $value, $options = null ) {
        if ( is_array( $value ) )
            $value = @implode( ' ', $value );

        $value = trim( $value );

        if ( empty( $value ) )
            return $value;

        $options = (array) $options;

        if ( 1 == filters_var( 'text_allow_html', $options, 0, null, true ) ) {
            $allowed_html_tags = '';

            if ( 0 < strlen( filters_var( 'text_allowed_html_tags', $options ) ) ) {
                $allowed_html_tags = explode( ' ', trim( filters_var( 'text_allowed_html_tags', $options ) ) );
                $allowed_html_tags = '<' . implode( '><', $allowed_html_tags ) . '>';
            }

            if ( !empty( $allowed_html_tags ) && '<>' != $allowed_html_tags )
                $value = strip_tags( $value, $allowed_html_tags );
        }
        else
            $value = strip_tags( $value );

        return $value;
    }
}
