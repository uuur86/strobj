<?php

namespace strobj;

class StringObjects {
	protected $obj = null;



	public function __construct( $obj ) {
		$this->obj = $obj;
	}



	public function get( $str, $default = false ) {

		if( empty( $this->obj ) ) return $default;

		$str_exp	= explode( '/', $str );
		$obj		= $this->obj;

		foreach( $str_exp as $obj_name ) {

			if( !isset( $obj->{ $obj_name } ) ) return $default;

			$obj = $obj->{ $obj_name };
		}

		return $obj;
	}
}
