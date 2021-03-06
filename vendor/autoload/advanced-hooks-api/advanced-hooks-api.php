<?php
/*
Plugin Name: Advanced Hooks API
Plugin URI: https://github.com/Rarst/advanced-hooks-api
Description: Set of (experimental) wrappers that allow to hook more elaborate events without coding intermediary functions.
Author: Andrey "Rarst" Savchenko
Author URI: http://www.rarst.net/
Version:
License Notes: MIT
*/

if ( ! class_exists( 'R_Hook_Handler' ) ) {

/**
 * Handler class for events in hooks.
 */
class R_Hook_Handler {

	private $data;

	/**
	 * @var callback
	 */
	private $callback;

	/**
	 * @param mixed         $data
	 * @param null|callback $callback
	 */
	function __construct( $data, $callback = null ) {

		$this->data     = $data;
		$this->callback = $callback;
	}

	/**
	 * Executes callback with custom arguments, ignores what hook passes.
	 *
	 * @return mixed|null
	 */
	function action() {

		call_user_func_array( $this->callback, $this->data );

		// compatibility with filters
		if ( func_num_args() )
			return func_get_arg( 0 );

		return null;
	}

	/**
	 * Returns custom data to filter.
	 *
	 * @return mixed
	 */
	function filter() {

		return $this->data;
	}

	/**
	 * Searches and replaces data as array or substring, depending on passed filter value.
	 *
	 * @param mixed $input
	 *
	 * @return array|mixed
	 */
	function replace( $input ) {

		if ( is_array( $input ) )
			$input[$this->data['search']] = $this->data['replace'];
		else
			$input = str_replace( $this->data['search'], $this->data['replace'], $input );

		return $input;
	}

	/**
	 * Prepends data to array or string, passed by filter.
	 *
	 * @param mixed $input
	 *
	 * @return array|string
	 */
	function prepend( $input ) {

		if ( is_array( $input ) ) {

			if ( is_array( $this->data ) ) {

				foreach ( array_reverse( $this->data ) as $value ) {

					array_unshift( $input, $value );
				}
			}
			else {

				array_unshift( $input, $this->data );
			}
		}
		else {

			$input = $this->data . $input;
		}

		return $input;
	}


	/**
	 * Appends data to array or string, passed by filter.
	 *
	 * @param mixed $input
	 *
	 * @return array|string
	 */
	function append( $input ) {

		if ( is_array( $input ) ) {

			if ( is_array( $this->data ) ) {

				foreach ( $this->data as $value ) {

					$input[] = $value;
				}
			}
			else {

				$input[] = $this->data;
			}
		}
		else {

			$input .= $this->data;
		}

		return $input;
	}

	/**
	 * Executes callback once and removes it from hook.
	 *
	 * @return mixed
	 */
	function once() {

		remove_filter( current_filter(), array( $this, __FUNCTION__ ), $this->data['priority'] );

		return call_user_func_array( $this->callback, array_slice( func_get_args(), 0, $this->data['accepted_args'] ) );
	}

	/**
	 * Remove matching handlers from specified hook and priority.
	 *
	 * @param string      $tag
	 * @param int         $priority
	 * @param mixed       $data
	 * @param string      $method of handler class
	 * @param string|null $callback
	 */
	static function remove_action( $tag, $priority, $data, $method, $callback = null ) {
		
		global $wp_filter;
		
		foreach ( $wp_filter[$tag][$priority] as $event ) {

			$function = $event['function'];

			/**
			 * @var R_Hook_Handler $object
			 */
			if ( is_array( $function ) && is_a( $object = $function[0], __CLASS__ ) )
					$object->remove_if_match( $tag, $priority, $event['accepted_args'], $data, $method, $callback );
		}
	}

	/**
	 * Unhook this instance from hook if data matches.
	 *
	 * @param string      $tag
	 * @param int         $priority
	 * @param int         $accepted_args
	 * @param mixed       $data
	 * @param string      $method of handler class
	 * @param string|null $callback
	 */
	function remove_if_match( $tag, $priority, $accepted_args, $data, $method, $callback = null ) {

		if ( $data === $this->data && $callback === $this->callback )
			remove_action( $tag, array( $this, $method ), $priority, $accepted_args );
	}
}
    
    
    
	/**
	 * @param string   $tag
	 * @param callback $callback
	 * @param int      $priority
	 * @param null     $args one or more arguments to pass to hooked function.
	 *
	 * @return bool
	 */
	function add_action_with_args( $tag, $callback, $priority = 10, $args = null ) {

		$args = array_slice( func_get_args(), 3 );

		return add_action( $tag, array( new R_Hook_Handler( $args, $callback ), 'action' ), $priority, 1 );
	}

	/**
	 * @param string       $tag
	 * @param callback     $callback
	 * @param int          $priority
	 * @param null         $args
	 */
	function remove_action_with_args( $tag, $callback, $priority = 10, $args = null ) {

		$args = array_slice( func_get_args(), 3 );

		R_Hook_Handler::remove_action( $tag, $priority, $args, 'action', $callback );
	}

	/**
	 * @param string $tag
	 * @param mixed  $return value to override filter with
	 * @param int    $priority
	 *
	 * @return bool
	 */
	function add_filter_return( $tag, $return, $priority = 10 ) {

		return add_filter( $tag, array( new R_Hook_Handler( $return ), 'filter' ), $priority, 0 );
	}

	/**
	 * @param string $tag
	 * @param mixed  $return
	 * @param int    $priority
	 */
	function remove_filter_return( $tag, $return, $priority = 10 ) {

		R_Hook_Handler::remove_action( $tag, $priority, $return, 'filter' );
	}

	/**
	 * @param string $tag
	 * @param mixed  $prepend value to concatenate at start of filtered string or unshift to start of filtered array
	 * @param int    $priority
	 *
	 * @return bool
	 */
	function add_filter_prepend( $tag, $prepend, $priority = 10 ) {

		return add_filter( $tag, array( new R_Hook_Handler( $prepend ), 'prepend' ), $priority, 1 );
	}

	/**
	 * @param string $tag
	 * @param mixed  $prepend
	 * @param int    $priority
	 */
	function remove_filter_prepend( $tag, $prepend, $priority = 10 ) {

		R_Hook_Handler::remove_action( $tag, $priority, $prepend, 'prepend' );
	}

	/**
	 * @param string $tag
	 * @param mixed  $append value to concatenate at end of filtered string or append to end of filtered array
	 * @param int    $priority
	 *
	 * @return bool
	 */
	function add_filter_append( $tag, $append, $priority = 10 ) {

		return add_filter( $tag, array( new R_Hook_Handler( $append ), 'append' ), $priority, 1 );
	}

	/**
	 * @param string $tag
	 * @param mixed  $append
	 * @param int    $priority
	 */
	function remove_filter_append( $tag, $append, $priority = 10 ) {

		R_Hook_Handler::remove_action( $tag, $priority, $append, 'append' );
	}

	/**
	 * @param string $tag
	 * @param string $search  substring to search or array key
	 * @param string $replace string to replace substring or array value with
	 * @param int    $priority
	 *
	 * @return bool
	 */
	function add_filter_replace( $tag, $search, $replace, $priority = 10 ) {

		return add_filter( $tag, array( new R_Hook_Handler( compact( 'search', 'replace' ) ), 'replace' ), $priority );
	}

	/**
	 * @param string $tag
	 * @param string $search
	 * @param string $replace
	 * @param int    $priority
	 */
	function remove_filter_replace( $tag, $search, $replace, $priority = 10 ) {

		R_Hook_Handler::remove_action( $tag, $priority, compact( 'search', 'replace' ), 'replace' );
	}

	/**
	 * Add filter to only run once.
	 *
	 * @param string   $tag
	 * @param callback $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 *
	 * @return bool
	 */
	function add_filter_once( $tag, $callback, $priority = 10, $accepted_args = 1 ) {

		return add_action( $tag, array( new R_Hook_Handler( compact( 'priority', 'accepted_args' ), $callback ), 'once' ), $priority, $accepted_args );
	}

	/**
	 * Remove filter that runs once.
	 *
	 * @param string   $tag
	 * @param callback $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	function remove_filter_once( $tag, $callback, $priority = 10, $accepted_args = 1 ) {

		R_Hook_Handler::remove_action( $tag, $priority, compact( 'priority', 'accepted_args' ), 'once', $callback );
	}

	/**
	 * Add action that only runs once.
	 *
	 * @param string   $tag
	 * @param callback $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 *
	 * @return bool
	 */
	function add_action_once( $tag, $callback, $priority = 10, $accepted_args = 1 ) {

		return add_filter_once( $tag, $callback, $priority, $accepted_args );
	}

	/**
	 * Remove action that runs once.
	 *
	 * @param string   $tag
	 * @param callback $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	function remove_action_once( $tag, $callback, $priority = 10, $accepted_args = 1 ) {

		remove_filter_once( $tag, $callback, $priority, $accepted_args );
	}

	/**
	 * @param array|string       $tags  hook and method name or array of names
	 * @param int                $priority
	 * @param int                $accepted_args
	 * @param bool|string|object $class false for auto, class name or object
	 *
	 * @return bool|void
	 */
	function add_method( $tags, $priority = 10, $accepted_args = 1, $class = false ) {

		if ( empty( $class ) ) {

			list( , $caller) = debug_backtrace();

			if ( empty( $caller['class'] ) )
				return false;

			$class = ( '->' == $caller['type'] ) ? $caller['object'] : $caller['class'];
		}

		if ( ! is_array( $tags ) )
			$tags = array( $tags );

		foreach ( $tags as $tag ) {

			if ( method_exists( $class, $tag ) )
				add_action( $tag, array( $class, $tag ), $priority, $accepted_args );
		}

		return true;
	}
}