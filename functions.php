<?php
/**
 * Create a new URL off of the current one, with updated parameters
 *
 * @param array $array Parameters to be set (empty will remove it)
 * @param array $allowed Parameters to keep (if empty, all are kept)
 * @param array $excluded Parameters to always remove
 * @param string $url URL to base update off of
 *
 * @return mixed
 *
 * @since 0.1
 */
function filters_var_update ( $array = null, $allowed = null, $excluded = null, $url = null ) {
    $array = (array) $array;
    $allowed = (array) $allowed;
    $excluded = (array) $excluded;

    if ( empty( $url ) )
        $url = $_SERVER[ 'REQUEST_URI' ];

    if ( !isset( $_GET ) )
        $get = array();
    else
        $get = $_GET;

    $get = filters_unsanitize( $get );

    foreach ( $get as $key => $val ) {
        if ( is_array( $val ) && empty( $val ) )
            unset( $get[ $key ] );
        elseif ( !is_array( $val ) && strlen( $val ) < 1 )
            unset( $get[ $key ] );
        elseif ( !empty( $allowed ) && !in_array( $key, $allowed ) )
            unset( $get[ $key ] );
    }

    if ( !empty( $excluded ) ) {
        foreach ( $excluded as $exclusion ) {
            if ( isset( $get[ $exclusion ] ) && !in_array( $exclusion, $allowed ) )
                unset( $get[ $exclusion ] );
        }
    }

    if ( !empty( $array ) ) {
        foreach ( $array as $key => $val ) {
            if ( null !== $val || false === strpos( $key, '*' ) ) {
                if ( is_array( $val ) && !empty( $val ) )
                    $get[ $key ] = $val;
                elseif ( !is_array( $val ) && 0 < strlen( $val ) )
                    $get[ $key ] = $val;
                elseif ( isset( $get[ $key ] ) )
                    unset( $get[ $key ] );
            }
            else {
                $key = str_replace( '*', '', $key );

                foreach ( $get as $k => $v ) {
                    if ( false !== strpos( $k, $key ) )
                        unset( $get[ $k ] );
                }
            }
        }
    }

    $url = current( explode( '#', current( explode( '?', $url ) ) ) );

    return $url . '?' . http_build_query( $get );
}

/**
 * Filter input and return sanitized output
 *
 * @param mixed $input The string, array, or object to sanitize
 * @param bool $nested
 *
 * @return array|mixed|object|string|void
 * @since 1.2.0
 */
function filters_sanitize ( $input, $nested = false ) {
    $output = array();

    if ( empty( $input ) )
        $output = $input;
    elseif ( is_object( $input ) ) {
        $input = get_object_vars( $input );

        foreach ( $input as $key => $val ) {
            $output[ filters_sanitize( $key ) ] = filters_sanitize( $val, true );
        }

        $output = (object) $output;
    }
    elseif ( is_array( $input ) ) {
        foreach ( $input as $key => $val ) {
            $output[ filters_sanitize( $key ) ] = filters_sanitize( $val, true );
        }
    }
    else
        $output = esc_sql( $input );

    return $output;
}

/**
 * Filter input and return unsanitized output
 *
 * @param mixed $input The string, array, or object to unsanitize
 * @param bool $nested
 *
 * @return array|mixed|object|string|void
 * @since 1.2.0
 */
function filters_unsanitize ( $input, $nested = false ) {
    $output = array();

    if ( empty( $input ) )
        $output = $input;
    elseif ( is_object( $input ) ) {
        $input = get_object_vars( $input );

        foreach ( $input as $key => $val ) {
            $output[ filters_unsanitize( $key ) ] = filters_unsanitize( $val, true );
        }

        $output = (object) $output;
    }
    elseif ( is_array( $input ) ) {
        foreach ( $input as $key => $val ) {
            $output[ filters_unsanitize( $key ) ] = filters_unsanitize( $val, true );
        }
    }
    else
        $output = stripslashes( $input );

    return $output;
}

/**
 * Return a variable (if exists)
 *
 * @param mixed $var The variable name or URI segment position
 * @param string $type (optional) get|url|post|request|server|session|cookie|constant|global|user|option|site-option|transient|site-transient|cache
 * @param mixed $default (optional) The default value to set if variable doesn't exist
 * @param mixed $allowed (optional) The value(s) allowed
 * @param bool $strict (optional) Only allow values (must not be empty)
 * @param string $context (optional) All returned values are sanitized unless this is set to 'raw'
 *
 * @return mixed The variable (if exists), or default value
 * @since 0.1
 */
function filters_var ( $var = 'last', $type = 'get', $default = null, $allowed = null, $strict = false, $context = 'display' ) {
    $output = $default;

    if ( is_array( $type ) )
        $output = isset( $type[ $var ] ) ? $type[ $var ] : $default;
    elseif ( is_object( $type ) )
        $output = isset( $type->$var ) ? $type->$var : $default;
    else {
        $type = strtolower( (string) $type );

        if ( 'get' == $type && isset( $_GET[ $var ] ) )
            $output = stripslashes_deep( $_GET[ $var ] );
        elseif ( in_array( $type, array( 'url', 'uri' ) ) ) {
            $url = parse_url( get_current_url() );
            $uri = trim( $url[ 'path' ], '/' );
            $uri = array_filter( explode( '/', $uri ) );

            if ( 'first' == $var )
                $var = 0;
            elseif ( 'last' == $var )
                $var = -1;

            if ( is_numeric( $var ) )
                $output = ( $var < 0 ) ? filters_var_raw( count( $uri ) + $var, $uri ) : filters_var_raw( $var, $uri );
        }
        elseif ( 'url-relative' == $type ) {
            $url_raw = get_current_url();
            $prefix = get_bloginfo( 'wpurl' );

            if ( substr( $url_raw, 0, strlen( $prefix ) ) == $prefix )
                $url_raw = substr( $url_raw, strlen( $prefix ) + 1, strlen( $url_raw ) );

            $url = parse_url( $url_raw );
            $uri = trim( $url[ 'path' ], '/' );
            $uri = array_filter( explode( '/', $uri ) );

            if ( 'first' == $var )
                $var = 0;
            elseif ( 'last' == $var )
                $var = -1;

            if ( is_numeric( $var ) )
                $output = ( $var < 0 ) ? filters_var_raw( count( $uri ) + $var, $uri ) : filters_var_raw( $var, $uri );
        }
        elseif ( 'post' == $type && isset( $_POST[ $var ] ) )
            $output = stripslashes_deep( $_POST[ $var ] );
        elseif ( 'request' == $type && isset( $_REQUEST[ $var ] ) )
            $output = stripslashes_deep( $_REQUEST[ $var ] );
        elseif ( 'server' == $type ) {
            if ( isset( $_SERVER[ $var ] ) )
                $output = stripslashes_deep( $_SERVER[ $var ] );
            elseif ( isset( $_SERVER[ strtoupper( $var ) ] ) )
                $output = stripslashes_deep( $_SERVER[ strtoupper( $var ) ] );
        }
        elseif ( 'session' == $type && isset( $_SESSION[ $var ] ) )
            $output = $_SESSION[ $var ];
        elseif ( in_array( $type, array( 'global', 'globals' ) ) && isset( $GLOBALS[ $var ] ) )
            $output = $GLOBALS[ $var ];
        elseif ( 'cookie' == $type && isset( $_COOKIE[ $var ] ) )
            $output = stripslashes_deep( $_COOKIE[ $var ] );
        elseif ( 'constant' == $type && defined( $var ) )
            $output = constant( $var );
        elseif ( 'user' == $type && is_user_logged_in() ) {
            $user = get_userdata( get_current_user_id() );

            if ( isset( $user->{$var} ) )
                $value = $user->{$var};
            else
                $value = get_user_meta( $user->ID, $var );

            if ( is_array( $value ) && !empty( $value ) )
                $output = $value;
            elseif ( !is_array( $value ) && 0 < strlen( $value ) )
                $output = $value;
        }
        elseif ( 'option' == $type )
            $output = get_option( $var, $default );
        elseif ( 'site-option' == $type )
            $output = get_site_option( $var, $default );
        elseif ( 'transient' == $type )
            $output = get_transient( $var );
        elseif ( 'site-transient' == $type )
            $output = get_site_transient( $var );
        elseif ( 'cache' == $type && isset( $GLOBALS[ 'wp_object_cache' ] ) && is_object( $GLOBALS[ 'wp_object_cache' ] ) ) {
            $group = 'default';
            $force = false;

            if ( is_array( $var ) ) {
                if ( isset( $var[ 1 ] ) )
                    $group = $var[ 1 ];

                if ( isset( $var[ 2 ] ) )
                    $force = $var[ 2 ];

                if ( isset( $var[ 0 ] ) )
                    $var = $var[ 0 ];
            }

            $output = wp_cache_get( $var, $group, $force );
        }
        else
            $output = apply_filters( 'filters_var_' . $type, $default, $var, $allowed, $strict, $context );
    }

    if ( null !== $allowed ) {
        if ( is_array( $allowed ) ) {
            if ( !in_array( $output, $allowed ) )
                $output = $default;
        }
        elseif ( $allowed !== $output )
            $output = $default;
    }

    if ( true === $strict && empty( $output ) )
        $output = $default;

    if ( 'raw' != $context )
        $output = filters_sanitize( $output );

    return $output;
}

/**
 * Return a variable's raw value (if exists)
 *
 * @param mixed $var The variable name or URI segment position
 * @param string $type (optional) get|url|post|request|server|session|cookie|constant|user|option|site-option|transient|site-transient|cache
 * @param mixed $default (optional) The default value to set if variable doesn't exist
 * @param mixed $allowed (optional) The value(s) allowed
 * @param bool $strict (optional) Only allow values (must not be empty)
 *
 * @return mixed The variable (if exists), or default value
 * @since 0.1
 */
function filters_var_raw ( $var = 'last', $type = 'get', $default = null, $allowed = null, $strict = false ) {
    return filters_var( $var, $type, $default, $allowed, $strict, 'raw' );
}