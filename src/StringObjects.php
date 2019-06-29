<?php

namespace strobj;

class StringObjects {
	protected $obj = null;



	public function __construct( $obj ) {
		$this->obj = $obj;
	}



	public function get( $str ) {

		if( empty( $this->obj ) ) return false;

		$str_exp	= explode( '/', $str );
		$obj		= $this->obj;

		foreach( $str_exp as $obj_name ) {

			if( !isset( $obj->{ $obj_name } ) ) return false;

			$obj = $obj->{ $obj_name };
		}

		return $obj;
	}
}
